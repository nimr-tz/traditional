<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Mail\FeeWaived;
use App\Mail\PaymentConfirmed;
use App\Models\Attendance;
use App\Models\ConferenceSetting;
use App\Models\FeeCategory;
use App\Models\Institution;
use App\Models\User;
use App\Services\BadgePrinter;
use App\Services\Sms\SmsNotifier;
use App\Services\WalkInRegistrar;
use App\Support\ConferenceEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The venue desk.
 *
 * Built around one question — who is standing in front of me and what do I do
 * with them — so it opens on a search rather than a roster. Nobody works a door
 * by scrolling four hundred names.
 *
 * Recording attendance stays in the check-in app, which scans badges against
 * /api/checkin/*. Two consoles that could both mark someone present would only
 * disagree about who arrived. What this page adds instead is the paperwork the
 * app is bad at: registering a walk-in, handing them a control number, and —
 * for finance only — settling what they owe.
 */
class DashboardController extends Controller
{
    /** Registrants only; organisers are not people who come through the door. */
    private function registrants(): Builder
    {
        return User::withRole(User::ROLE_USER);
    }

    public function index(Request $request): Response
    {
        $staff = Auth::user();
        $search = trim((string) $request->search);

        // Attendance is per day, so the door's question is "who is here today",
        // not "who has ever attended". Both are shown — the first is the number
        // staff act on, the second is the conference-wide reach.
        $expected = $this->registrants()->whereIn('payment_status', ['verified', 'waived']);
        $expectedCount = (clone $expected)->count();
        $hereToday = (clone $expected)->whereHas('attendance', fn (Builder $query) => $query->today())->count();
        $attendedEver = (clone $expected)->whereHas('attendance')->count();

        return Inertia::render('staff/dashboard', [
            'staffName' => $staff->full_name,
            'canManageFinance' => $staff->canManageFinance(),
            'conferenceName' => ConferenceSetting::get('conference_name'),
            'venue' => ConferenceSetting::get('venue'),
            'filters' => ['search' => $search ?: null],
            'results' => $search !== '' ? $this->search($search) : null,
            'stats' => [
                'expected' => $expectedCount,
                'here_today' => $hereToday,
                'not_arrived_today' => max(0, $expectedCount - $hereToday),
                'attended_ever' => $attendedEver,
            ],
            'deskOptions' => [
                // The desk sees complimentary categories too — media, secretariat
                // and invited guests are registered here, never on the public form.
                'fee_categories' => FeeCategory::query()
                    ->active()
                    ->orderBy('is_complimentary')
                    ->orderBy('amount')
                    ->get(['key', 'label', 'amount', 'currency', 'is_complimentary']),
                'participant_types' => config('tmsc.participant_types'),
                'countries' => config('tmsc.countries'),
                'east_africa_countries' => config('tmsc.east_africa_countries'),
                'institutions' => Institution::where('active', true)->orderBy('sort_order')->get(['id', 'name']),
                'salutations' => config('tmsc.salutations'),
            ],
            // The side panel: who is coming through right now, newest first.
            'arrivals' => Attendance::with(['user:id,name,salutation,institution', 'staff:id,name,salutation'])
                ->latest('checked_in_at')
                ->limit(30)
                ->get()
                ->map(fn (Attendance $attendance) => [
                    'id' => $attendance->id,
                    'checked_in_at' => $attendance->checked_in_at,
                    'attendance_date' => $attendance->attendance_date?->toDateString(),
                    'is_today' => $attendance->attendance_date?->isToday() ?? false,
                    'name' => $attendance->user?->full_name,
                    'institution' => $attendance->user?->institution,
                    'recorded_by' => $attendance->staff?->full_name,
                ]),
        ]);
    }

    /**
     * Everything about one registrant, and every action the desk can take on
     * them, in one place.
     *
     * What a search result opens onto. The desk used to see a handful of
     * buttons crammed into the search row itself; this gives each action room
     * next to the details that justify it — issuing a control number sits next
     * to what is owed, settling a payment sits next to who is allowed to.
     */
    public function show(User $user, BadgePrinter $printer): Response
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        $user->load([
            'attendance' => fn ($query) => $query->orderByDesc('attendance_date'),
            'attendance.staff:id,name,salutation',
            'badgePrints' => fn ($query) => $query->orderByDesc('printed_at'),
            'badgePrints.printedBy:id,name,salutation',
            'verifiedBy:id,name,salutation',
        ]);

        $category = FeeCategory::where('key', $user->fee_category)->first();
        $studentVerifier = $user->student_verified_by
            ? User::find($user->student_verified_by, ['id', 'name', 'salutation'])
            : null;

        return Inertia::render('staff/registrant', [
            'canManageFinance' => Auth::user()->canManageFinance(),
            // Null until they have paid. Showing the badge here costs nothing —
            // preview() does not log a print — and saves printing one just to
            // read a code off it.
            'badge' => $printer->preview($user),
            'person' => [
                'id' => $user->id,
                'name' => $user->name,
                'salutation' => $user->salutation,
                'position_title' => $user->position_title,
                'email' => $user->email,
                'phone' => $user->phone,
                'institution' => $user->institution,
                'participant_type' => $user->participant_type,
                'country' => $user->country,
                'is_east_africa' => $user->is_east_africa,
                'fee_category' => $user->fee_category,
                'fee_category_label' => $category?->label,
                'is_complimentary' => $category?->isComplimentary() ?? false,
                'fee_amount' => $user->fee_amount,
                'currency' => $user->currency,
                'payment_status' => $user->payment_status,
                'is_paid' => $user->isPaid(),
                'control_number' => $user->control_number,
                'paid_at' => $user->paid_at,
                'payment_notes' => $user->payment_notes,
                'payment_verified_by' => $user->verifiedBy?->full_name,
                'registration_code' => $user->registration_code,
                'registered_at' => $user->created_at,
                'requires_student_verification' => $user->requiresStudentVerification(),
                'student_verification_status' => $user->student_verification_status,
                'student_verified_at' => $user->student_verified_at,
                'student_verified_by' => $studentVerifier?->full_name,
                'student_verification_notes' => $user->student_verification_notes,
                'can_print_badge' => $user->canPrintBadge(),
                // Same shape as the search payload, so this reuses StandingBadge
                // and PrintBadgeButton without an adapter.
                'checked_in_at' => $user->attendance->firstWhere(fn (Attendance $a) => $a->attendance_date?->isToday())?->checked_in_at,
                'last_seen_at' => $user->attendance->first()?->checked_in_at,
                'days_attended' => $user->attendance->count(),
                'badges_printed' => $user->badgePrints->count(),
            ],
            'attendance' => $user->attendance->map(fn (Attendance $attendance) => [
                'id' => $attendance->id,
                'date' => $attendance->attendance_date?->toDateString(),
                'checked_in_at' => $attendance->checked_in_at,
                'recorded_by' => $attendance->staff?->full_name,
            ]),
            'badgePrints' => $user->badgePrints->map(fn ($print) => [
                'id' => $print->id,
                'print_number' => $print->print_number,
                'printed_at' => $print->printed_at,
                'printed_by' => $print->printedBy?->full_name,
            ]),
            'salutations' => config('tmsc.salutations'),
        ]);
    }

    /**
     * Fix what the desk got wrong when it registered someone.
     *
     * A mistyped name, a missing title, the wrong institution — caught later,
     * even after the badge is printed. The alternative was registering the
     * person a second time, which leaves a duplicate in every count. Only the
     * fields that appear on the badge or identify the person; category, country
     * and payment state are settled elsewhere and left alone here.
     *
     * The BadgePrintLog is deliberately not touched: it records what was on the
     * card that came off the printer, so a correction here means "reprint", not
     * "rewrite history".
     */
    public function updateDetails(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        $validated = $request->validate([
            'salutation' => ['nullable', 'string', Rule::in(config('tmsc.salutations'))],
            'name' => ['required', 'string', 'max:255'],
            'position_title' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:50'],
            'institution' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = [
            'salutation' => ($validated['salutation'] ?? null) ?: null,
            'name' => $validated['name'],
            'position_title' => filled($validated['position_title'] ?? null) ? $validated['position_title'] : null,
            'phone' => filled($validated['phone'] ?? null) ? $validated['phone'] : null,
            'institution' => filled($validated['institution'] ?? null) ? $validated['institution'] : null,
        ];

        // Retyping the institution by hand breaks any link to a canonical
        // Institution row — drop it rather than leave it pointing at the old one.
        if ($attributes['institution'] !== $user->institution) {
            $attributes['institution_id'] = null;
        }

        $user->update($attributes);

        $note = $user->badgePrints()->exists()
            ? ' Their badge is already printed — reprint it to pick up the change.'
            : '';

        return back()->with('success', "Updated {$user->full_name}'s details.{$note}");
    }

    /**
     * Register someone who turned up without an account, and start their
     * control number so they can pay from the queue.
     *
     * Staff may do this. What they may not do is make the result paid — see
     * confirmPayment/waive below.
     */
    public function registerWalkIn(Request $request, WalkInRegistrar $registrar, BadgePrinter $printer): RedirectResponse
    {
        $validated = $request->validate(WalkInRegistrar::rules($request->input('fee_category')));

        ['user' => $user, 'billing' => $billing] = $registrar->register($validated, $request->user());

        // Whether there is a badge follows from whether they have paid, not from
        // how billing went: someone waived or complimentary is paid and never
        // gets a control number, so keying off the billing status would leave
        // exactly the people whose badge is ready staring at a panel about a
        // control number that will never exist.
        $badge = $printer->preview($user);

        return back()
            ->with('walkIn', [
                'name' => $user->full_name,
                'registrant_id' => $user->id,
                'badge' => $badge,
                'badges_printed' => $user->badgePrints()->count(),
                'control_number' => $billing['control_number'],
                'billing_message' => $billing['message'],
            ])
            ->with($badge || $billing['status'] === 'ready' ? 'success' : 'info', match (true) {
                $badge !== null => "{$user->full_name} is registered. Their badge is ready.",
                $billing['status'] === 'ready' => "{$user->full_name} is registered. Control number {$billing['control_number']} — they can pay it now.",
                default => "{$user->full_name} is registered. {$billing['message']}",
            });
    }

    /**
     * Print a badge at the desk.
     *
     * Reprints are deliberately allowed — people lose badges, and a desk that
     * cannot reissue one just creates a queue. Every print is logged with its
     * number, so a reissue is a known event rather than a quiet duplicate, and
     * the desk is warned before it happens.
     */
    public function printBadge(User $user, BadgePrinter $printer): HttpResponse|RedirectResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        if (! $user->canPrintBadge()) {
            return back()->with('error', "{$user->full_name} has not paid, so there is no badge to print.");
        }

        return $printer->render($user, Auth::user())->stream($printer->filenameFor($user));
    }

    /** Re-issue, or first issue, a control number for someone who still owes. */
    public function issueControlNumber(User $user, WalkInRegistrar $registrar): RedirectResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        $billing = $registrar->startBilling($user);

        return back()->with($billing['status'] === 'ready' ? 'success' : 'info', $billing['control_number']
            ? "Control number for {$user->full_name}: {$billing['control_number']}"
            : $billing['message']);
    }

    /**
     * Finance confirms money received. Mirrors Admin\FinanceController::verify
     * and the check-in app's endpoint so the three cannot drift on what
     * "verified" means.
     */
    public function confirmPayment(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        if ($user->isPaid()) {
            return back()->with('info', "{$user->full_name} is already marked as paid.");
        }

        $user->forceFill([
            'payment_status' => 'verified',
            'paid_at' => now(),
            'payment_verified_by' => $request->user()->id,
            'payment_notes' => $validated['notes'] ?: 'Verified at the venue desk.',
        ]);

        $user->generateRegistrationCode();
        $user->save();

        ConferenceEmail::sendTo($user, new PaymentConfirmed($user));
        app(SmsNotifier::class)->paymentConfirmed($user);

        return back()->with('success', "Payment confirmed for {$user->full_name}. Badge code {$user->registration_code}.");
    }

    /** Finance waives a fee. Notes are required — a waiver is the one way in without money. */
    public function waivePayment(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        $validated = $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        if ($user->isPaid()) {
            return back()->with('info', "{$user->full_name} is already marked as paid.");
        }

        $user->forceFill([
            'payment_status' => 'waived',
            'paid_at' => now(),
            'payment_verified_by' => $request->user()->id,
            'payment_notes' => $validated['notes'],
        ]);

        $user->generateRegistrationCode();
        $user->save();

        ConferenceEmail::sendTo($user, new FeeWaived($user, $validated['notes']));

        return back()->with('success', "Fee waived for {$user->full_name}. Badge code {$user->registration_code}.");
    }

    /**
     * Find whoever is at the desk.
     *
     * Unfiltered by payment: the person standing there has to be findable even
     * when the answer is that they still owe money — that is precisely the case
     * the desk needs to act on.
     *
     * @return array<int, array<string, mixed>>
     */
    private function search(string $term): array
    {
        return $this->registrants()
            ->with('attendance:id,user_id,attendance_date,checked_in_at')
            ->withCount('badgePrints')
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('institution', 'like', "%{$term}%")
                ->orWhere('registration_code', 'like', "%{$term}%")
                ->orWhere('control_number', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'salutation' => $user->salutation,
                'email' => $user->email,
                'phone' => $user->phone,
                'institution' => $user->institution,
                'is_paid' => $user->isPaid(),
                'payment_status' => $user->payment_status,
                'fee_amount' => $user->fee_amount,
                'currency' => $user->currency,
                'control_number' => $user->control_number,
                'registration_code' => $user->registration_code,
                // Today decides whether the desk lets them through; the day
                // count tells staff whether this is a returning attendee.
                'checked_in_at' => $user->attendance->firstWhere(fn ($a) => $a->attendance_date?->isToday())?->checked_in_at,
                'days_attended' => $user->attendance->count(),
                'last_seen_at' => $user->attendance->sortByDesc('checked_in_at')->first()?->checked_in_at,
                // Drives the reprint warning: the desk is told how many badges
                // this person already has before it prints another.
                'can_print_badge' => $user->canPrintBadge(),
                'badges_printed' => $user->badge_prints_count ?? 0,
            ])
            ->all();
    }
}
