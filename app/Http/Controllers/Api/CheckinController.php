<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\FeeWaived;
use App\Mail\PaymentConfirmed;
use App\Models\Attendance;
use App\Models\FeeCategory;
use App\Models\Institution;
use App\Models\User;
use App\Services\Sms\SmsNotifier;
use App\Services\WalkInRegistrar;
use App\Support\ConferenceEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    /**
     * Register a walk-in attendee, charge them the real fee, and start a GePG
     * control number for them.
     *
     * This used to mark the walk-in `verified` and mint a badge code on the
     * spot, which handed out paid-for entry to anyone who reached the desk and
     * left finance with a registrant carrying no fee category at all — invisible
     * to every revenue total. A walk-in now lands `pending` like any other
     * registrant and gets their badge when GePG confirms the payment.
     */
    public function register(Request $request, WalkInRegistrar $registrar): JsonResponse
    {
        $validated = $request->validate(WalkInRegistrar::rules($request->input('fee_category')));

        ['user' => $user, 'billing' => $billing] = $registrar->register($validated, $request->user());

        return response()->json([
            'message' => 'Attendee registered. They can pay with the control number below.',
            'user' => $this->registrantPayload($user),
            'billing' => $billing,
        ], 201);
    }

    /**
     * Kick off a control number for a registrant who does not have one, and
     * report back in the terms the desk needs.
     *
     * A walk-in student is the awkward case: they can pick a student rate, but
     * GePG will not issue against it until someone has seen their student ID,
     * and there is no document upload at the desk. Rather than block the
     * registration, we complete it and hand the staff member the reason.
     */
    public function requestControlNumber(Request $request, User $user, WalkInRegistrar $registrar): JsonResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        $billing = $registrar->startBilling($user);

        return response()->json([
            'user' => $this->registrantPayload($user->fresh()),
            'billing' => $billing,
        ]);
    }

    /**
     * Scan a badge QR code (its registration_code) and check the attendee in.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $user = User::where('registration_code', self::registrationCodeFrom($request->code))->first();

        if (! $user) {
            return response()->json(['message' => 'No registrant found for this code.'], 404);
        }

        return $this->checkIn($user, $request);
    }

    /**
     * Pull the registration code out of whatever the camera read.
     *
     * Badges now encode a verification URL so an ordinary phone camera can open
     * a public page confirming the holder. This app reads the same square, so it
     * takes the code off the end of that URL — while still accepting a bare code
     * from badges printed before the change, and from anyone typing one in.
     */
    public static function registrationCodeFrom(string $scanned): string
    {
        $scanned = trim($scanned);

        if (! str_contains($scanned, '/')) {
            return $scanned;
        }

        $path = parse_url($scanned, PHP_URL_PATH) ?: $scanned;

        return rawurldecode(basename(rtrim($path, '/')));
    }

    /**
     * Fallback for lost/unscannable badges — look up by name or email.
     *
     * Deliberately unfiltered by payment status. This used to return only paid
     * registrants, which made the desk's most common visitor — someone who
     * registered online and never paid — invisible: staff could not find them,
     * could not re-register them (the email is taken), and could not check them
     * in. Everyone is findable now, and the payment state comes back with them
     * so the staff member can see what the person needs.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2']);

        $users = User::withRole(User::ROLE_USER)
            ->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->q}%")
                    ->orWhere('email', 'like', "%{$request->q}%");
            })
            ->limit(10)
            ->get();

        return response()->json([
            'results' => $users->map(fn (User $user) => $this->registrantPayload($user))->all(),
        ]);
    }

    /**
     * Finance confirms a payment at the desk — the bank/mobile receipt is in
     * front of them but the GePG callback has not landed, or the registrant
     * paid by a route that needs a human to sign it off.
     *
     * Mirrors Admin\FinanceController::verify so the two consoles cannot drift
     * into disagreeing about what "verified" means.
     */
    public function verifyPayment(Request $request, User $user): JsonResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        if ($user->isPaid()) {
            return response()->json([
                'message' => "{$user->name} is already marked as paid.",
                'user' => $this->registrantPayload($user),
            ]);
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

        return response()->json([
            'message' => "Payment confirmed for {$user->name}.",
            'user' => $this->registrantPayload($user),
        ]);
    }

    /**
     * Finance waives a fee at the desk. Notes are required: a waiver is the one
     * way someone gets a badge without money, so the reason is the audit trail.
     */
    public function waivePayment(Request $request, User $user): JsonResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        $validated = $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        if ($user->isPaid()) {
            return response()->json([
                'message' => "{$user->name} is already marked as paid.",
                'user' => $this->registrantPayload($user),
            ]);
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

        return response()->json([
            'message' => "Fee waived for {$user->name}.",
            'user' => $this->registrantPayload($user),
        ]);
    }

    /** The single shape the desk sees a registrant in, wherever they surfaced from. */
    private function registrantPayload(User $user): array
    {
        return [
            ...$user->only([
                'id', 'name', 'email', 'phone', 'institution', 'participant_type',
                'registration_code', 'fee_category', 'fee_amount', 'currency',
                'payment_status', 'control_number', 'student_verification_status',
            ]),
            'is_paid' => $user->isPaid(),
            'is_checked_in' => $user->isCheckedIn(),
            // The door cares about today; the total is for context.
            'is_checked_in_today' => $user->isCheckedInToday(),
            'days_attended' => $user->attendance()->count(),
        ];
    }

    /**
     * Manually check in a user found via lookup() rather than a QR scan.
     */
    public function checkInById(Request $request, User $user): JsonResponse
    {
        return $this->checkIn($user, $request);
    }

    public function recent(): JsonResponse
    {
        $attendances = Attendance::with(['user:id,name,institution', 'staff:id,name'])
            ->latest('checked_in_at')
            ->limit(25)
            ->get();

        return response()->json(['attendances' => $attendances]);
    }

    private function checkIn(User $user, Request $request): JsonResponse
    {
        if (! $user->isPaid()) {
            // 422 carries the registrant back with it so the app can offer the
            // way out — a control number for staff, settle for finance —
            // instead of dead-ending on an error string.
            return response()->json([
                'message' => "{$user->name} has not paid yet — cannot check in.",
                'user' => $this->registrantPayload($user),
            ], 422);
        }

        // Scoped to today, not to all time. This used to look for any record at
        // all, so anyone scanned on the first morning was refused on every
        // later day of the conference and their return went unrecorded.
        $existing = $user->attendanceToday();

        if ($existing) {
            return response()->json([
                'already_checked_in' => true,
                'checked_in_at' => $existing->checked_in_at,
                'days_attended' => $user->attendance()->count(),
                'user' => $this->registrantPayload($user),
            ]);
        }

        $attendance = $user->attendance()->create([
            'checked_in_at' => now(),
            'checked_in_by' => $request->user()->id,
        ]);

        return response()->json([
            'already_checked_in' => false,
            'checked_in_at' => $attendance->checked_in_at,
            'days_attended' => $user->attendance()->count(),
            'user' => $this->registrantPayload($user),
        ]);
    }

    /**
     * The fee categories the desk can register someone into, so the app does
     * not carry its own copy of the price list.
     */
    public function feeCategories(): JsonResponse
    {
        return response()->json([
            'fee_categories' => FeeCategory::query()
                ->active()
                ->orderBy('is_complimentary')
                ->orderBy('amount')
                ->get(['key', 'label', 'amount', 'currency', 'is_complimentary']),
            'participant_types' => config('tmsc.participant_types'),
            'countries' => config('tmsc.countries'),
            'east_africa_countries' => config('tmsc.east_africa_countries'),
            'institutions' => Institution::where('active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }
}
