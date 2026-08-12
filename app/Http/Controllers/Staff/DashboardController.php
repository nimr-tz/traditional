<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Mail\FeeWaived;
use App\Mail\PaymentConfirmed;
use App\Models\Attendance;
use App\Models\ConferenceSetting;
use App\Models\FeeCategory;
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
            'staffName' => $staff->name,
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
            ],
            // The side panel: who is coming through right now, newest first.
            'arrivals' => Attendance::with(['user:id,name,institution', 'staff:id,name'])
                ->latest('checked_in_at')
                ->limit(30)
                ->get()
                ->map(fn (Attendance $attendance) => [
                    'id' => $attendance->id,
                    'checked_in_at' => $attendance->checked_in_at,
                    'attendance_date' => $attendance->attendance_date?->toDateString(),
                    'is_today' => $attendance->attendance_date?->isToday() ?? false,
                    'name' => $attendance->user?->name,
                    'institution' => $attendance->user?->institution,
                    'recorded_by' => $attendance->staff?->name,
                ]),
        ]);
    }

    /**
     * Register someone who turned up without an account, and start their
     * control number so they can pay from the queue.
     *
     * Staff may do this. What they may not do is make the result paid — see
     * confirmPayment/waive below.
     */
    public function registerWalkIn(Request $request, WalkInRegistrar $registrar): RedirectResponse
    {
        $validated = $request->validate(WalkInRegistrar::rules());

        ['user' => $user, 'billing' => $billing] = $registrar->register($validated);

        return back()->with($billing['status'] === 'ready' ? 'success' : 'info', $billing['status'] === 'ready'
            ? "{$user->name} is registered. Control number {$billing['control_number']} — they can pay it now."
            : "{$user->name} is registered. {$billing['message']}");
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
            return back()->with('error', "{$user->name} has not paid, so there is no badge to print.");
        }

        return $printer->render($user, Auth::user())->stream($printer->filenameFor($user));
    }

    /** Re-issue, or first issue, a control number for someone who still owes. */
    public function issueControlNumber(User $user, WalkInRegistrar $registrar): RedirectResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        $billing = $registrar->startBilling($user);

        return back()->with($billing['status'] === 'ready' ? 'success' : 'info', $billing['control_number']
            ? "Control number for {$user->name}: {$billing['control_number']}"
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
            return back()->with('info', "{$user->name} is already marked as paid.");
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

        return back()->with('success', "Payment confirmed for {$user->name}. Badge code {$user->registration_code}.");
    }

    /** Finance waives a fee. Notes are required — a waiver is the one way in without money. */
    public function waivePayment(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        $validated = $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        if ($user->isPaid()) {
            return back()->with('info', "{$user->name} is already marked as paid.");
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

        return back()->with('success', "Fee waived for {$user->name}. Badge code {$user->registration_code}.");
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
