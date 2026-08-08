<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StudentVerificationApproved;
use App\Mail\StudentVerificationRejected;
use App\Models\FeeCategory;
use App\Models\User;
use App\Support\FeeTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentVerificationController extends Controller
{
    private const STUDENT_FEE_CATEGORIES = [
        'student_east_africa',
        'student_non_east_africa',
    ];

    /**
     * Everyone who has ever been through student verification — not just those
     * currently on a student category.
     *
     * Refusing the rate moves the registrant onto a participant category, so
     * scoping this to student categories alone made the row disappear the
     * instant it was acted on, taking the decision and its notes with it.
     */
    private function studentVerificationScope(): Builder
    {
        return User::query()
            ->withRole(User::ROLE_USER)
            ->where(fn ($query) => $query
                ->whereIn('fee_category', self::STUDENT_FEE_CATEGORIES)
                ->orWhereNotNull('student_verification_status')
                ->orWhereNotNull('student_document_path'));
    }

    public function index(Request $request): Response
    {
        $students = $this->studentVerificationScope()
            ->when($request->status && $request->status !== 'all', fn ($query) => $query
                ->where('student_verification_status', $request->status))
            ->when($request->search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $studentQuery = $this->studentVerificationScope();

        return Inertia::render('admin/students/index', [
            'students' => $students,
            'filters' => $request->only(['status', 'search']),
            'stats' => [
                'pending' => (clone $studentQuery)->where('student_verification_status', 'pending')->count(),
                'verified' => (clone $studentQuery)->where('student_verification_status', 'verified')->count(),
                'rejected' => (clone $studentQuery)->where('student_verification_status', 'rejected')->count(),
                'total' => (clone $studentQuery)->count(),
            ],
            // Rejecting sets a non-student participant type, and the reviewer
            // picks which one.
            'participantTypes' => config('tmsc.participant_types'),
        ]);
    }

    public function document(User $user): StreamedResponse
    {
        // Keyed on the claim, not the current rate: a refused registrant sits on
        // a participant category, and their fresh document still has to be
        // openable for the re-review.
        abort_unless($user->hasStudentRateApplication() && $user->student_document_path, 404);

        $extension = pathinfo($user->student_document_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $user->student_document_path,
            'student-document-'.$user->id.'.'.$extension,
        );
    }

    /**
     * Approve the claim — and grant the student rate, since approval is the only
     * thing that does.
     *
     * Someone re-applying after a refusal is on a participant rate and paying
     * it quite legitimately, so accepting their new document is what moves them
     * down to the student rate. For a first-time applicant the category is
     * already a student one and this changes nothing.
     */
    public function verify(Request $request, User $user): RedirectResponse
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        abort_unless(
            $user->hasStudentRateApplication()
                && $user->student_document_path
                && $user->student_verification_status === 'pending',
            422,
        );

        // Granting the rate lowers what they owe. Doing that after they have
        // paid the participant rate creates an overpayment, which is a refund
        // decision rather than a click.
        abort_if(
            $user->isPaid() && ! $user->requiresStudentVerification(),
            422,
            "{$user->name} has already paid the participant rate. Ask finance how to handle the difference before granting the student rate.",
        );

        $movedTo = null;
        $voidedControlNumber = null;

        if (! $user->requiresStudentVerification()) {
            $studentCategory = FeeTier::studentEquivalentOf($user->fee_category);

            abort_if(
                $studentCategory === null || ! FeeCategory::where('key', $studentCategory)->where('active', true)->exists(),
                422,
                "No active student category matches {$user->fee_category}, so the student rate cannot be granted. Ask a super admin to check Conference Settings.",
            );

            $movedTo = $studentCategory;
            $voidedControlNumber = $user->control_number;

            $user->participant_type = 'student';
            $user->assignFeeCategory($studentCategory);

            // Any bill still outstanding was raised at the participant amount.
            if ($user->billing_request_id || $user->control_number) {
                $user->forceFill([
                    'payment_status' => 'pending',
                    'billing_request_id' => null,
                    'control_number' => null,
                    'payment_notes' => "Student rate granted on review; moved to {$studentCategory}. Control number {$voidedControlNumber} voided.",
                ]);
            }
        }

        $user->forceFill([
            'student_verification_status' => 'verified',
            'student_verified_at' => now(),
            'student_verified_by' => Auth::id(),
            'student_verification_notes' => $request->notes,
        ])->save();

        Mail::to($user->email)->send(new StudentVerificationApproved($user));

        if (! $movedTo) {
            return back()->with('success', "Student status for {$user->name} has been verified.");
        }

        $message = "{$user->name} has been verified and moved to the student rate.";

        return back()->with('success', $voidedControlNumber
            ? $message." Control number {$voidedControlNumber} was voided — they must request a new one."
            : $message);
    }

    /**
     * Refuse the student rate and move the registrant onto the standard
     * participant rate for their region.
     *
     * The conversion is the system's job, not the registrant's. Left to them it
     * could not be done at all: `Settings\RegistrationController` refuses a
     * category change once a control number has been requested, and no admin
     * route can change a fee category — so a plain rejection stranded people
     * who could neither pay the student rate nor leave it.
     *
     * `participant_type` moves too. A registrant sitting on `student` with a
     * participant category fails `FeeTier::guard` for good, which would break
     * their own settings page the next time they opened it.
     */
    public function reject(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
            'participant_type' => [
                'required',
                Rule::in(array_keys(config('tmsc.participant_types'))),
                // A student participant_type would put them straight back into
                // the tier they were just refused.
                Rule::notIn(['student']),
            ],
        ]);

        abort_unless(
            $user->hasStudentRateApplication()
                && $user->student_document_path
                && $user->student_verification_status === 'pending',
            422,
        );

        // Someone re-applying after an earlier refusal is already on the
        // participant rate. There is nothing to convert — record the decision
        // and leave their rate and any bill of theirs alone.
        $alreadyOnParticipantRate = ! $user->requiresStudentVerification();

        // Refusing the rate raises what they owe. Doing that to someone who has
        // already paid would leave them short with a valid badge, and would
        // overstate realised revenue by the difference. Finance has to unwind
        // the payment first.
        abort_if(
            $user->isPaid() && ! $alreadyOnParticipantRate,
            422,
            "{$user->name} has already paid at the student rate. Ask finance to reset the billing before refusing the rate, so the fee change does not understate what they owe.",
        );

        $keptControlNumber = null;

        if (! $alreadyOnParticipantRate) {
            $participantCategory = FeeTier::participantEquivalentOf($user->fee_category);

            abort_if(
                $participantCategory === null || ! FeeCategory::where('key', $participantCategory)->where('active', true)->exists(),
                422,
                "No active standard participant category matches {$user->fee_category}, so the fee cannot be converted. Ask a super admin to check Conference Settings.",
            );

            $previousCategory = $user->fee_category;
            $studentAmount = $user->fee_amount;
            $studentCurrency = $user->currency;
            $existingControlNumber = $keptControlNumber = $user->control_number;

            $user->participant_type = $validated['participant_type'];
            $user->assignFeeCategory($participantCategory);

            // A control number already in the registrant's hands is honoured
            // rather than cancelled — organisers would rather absorb the
            // difference than send someone away to pay again.
            //
            // The category moves but the amount does not: the bill they hold is
            // for the student amount and that is all that will ever arrive, so
            // recording the participant amount against them would overstate
            // realised revenue by the difference on every finance total. What is
            // stored stays equal to what is owed.
            if ($existingControlNumber) {
                $user->forceFill([
                    'fee_amount' => $studentAmount,
                    'currency' => $studentCurrency,
                    'payment_notes' => "Student rate refused; moved from {$previousCategory} to {$participantCategory}. Control number {$existingControlNumber} remains valid.",
                ]);
            }
        }

        $user->forceFill([
            'student_verification_status' => 'rejected',
            'student_verified_at' => now(),
            'student_verified_by' => Auth::id(),
            'student_verification_notes' => $validated['notes'],
        ]);

        $user->save();

        Mail::to($user->email)->send(new StudentVerificationRejected($user));

        if ($alreadyOnParticipantRate) {
            return back()->with('success', "The student rate has been refused again for {$user->name}. They stay on the participant rate and have been notified.");
        }

        $message = "{$user->name} has been moved to the standard participant rate and notified.";

        return back()->with('success', $keptControlNumber
            ? $message." Control number {$keptControlNumber} remains valid."
            : $message);
    }

    /**
     * Undo a decision and put the student back in the queue. Both verify and
     * reject only act on `pending`, so a misclick is otherwise permanent —
     * this is the way back to `pending`, not a third way to decide. The real
     * decision is then made through the normal buttons, so the student gets
     * the right mail with the right notes.
     *
     * Deliberately silent: the student hears about the re-decision, not about
     * the queue mechanics. The note we leave behind is internal (the payment
     * page only surfaces notes on a `rejected` status).
     *
     * It reopens the *review*, not the rate. A refusal moved the registrant onto
     * a participant rate, and they keep it while the question is open — the
     * student rate is granted by approval, so undoing the decision cannot hand
     * it back on its own. Keyed on the claim rather than the current rate, or a
     * refusal (which leaves them on a participant category) could not be undone
     * at all.
     */
    public function reopen(Request $request, User $user): RedirectResponse
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        abort_unless(
            $user->hasStudentRateApplication()
                && in_array($user->student_verification_status, ['verified', 'rejected'], true),
            422,
        );

        $previousStatus = $user->student_verification_status;
        $wasOnStudentRate = $user->requiresStudentVerification();
        $admin = Auth::user();

        $user->forceFill([
            'student_verification_status' => 'pending',
            'student_verified_at' => null,
            'student_verified_by' => null,
            'student_verification_notes' => $request->notes
                ?: "Reopened by {$admin->name} (previously {$previousStatus}).",
        ])->save();

        // Two different situations. Undoing an approval leaves them on a student
        // rate they can no longer be billed for, and any control number already
        // issued at it stands until finance resets the billing. Undoing a refusal
        // leaves the correct participant rate in place, so there is nothing to
        // warn about.
        if ($wasOnStudentRate && $user->control_number) {
            return back()->with('info', "{$user->name} is back in the review queue. Control number {$user->control_number} was already issued at the student rate — ask finance to reset billing if it should be voided.");
        }

        return back()->with('success', $wasOnStudentRate
            ? "{$user->name} is back in the review queue and cannot be billed until the review is decided."
            : "{$user->name} is back in the review queue. They stay on the participant rate and can still pay it.");
    }
}
