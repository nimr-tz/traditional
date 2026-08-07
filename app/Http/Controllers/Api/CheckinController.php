<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\FeeWaived;
use App\Mail\PaymentConfirmed;
use App\Models\Attendance;
use App\Models\FeeCategory;
use App\Models\User;
use App\Services\Billing\GepgService;
use App\Support\FeeTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

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
    public function register(Request $request, GepgService $gepg): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50'],
            'institution' => ['nullable', 'string', 'max:255'],
            'participant_type' => ['required', Rule::in(array_keys(config('tmsc.participant_types')))],
            'country' => ['required', Rule::in(config('tmsc.countries'))],
            'fee_category' => ['required', Rule::exists('fee_categories', 'key')->where('active', true)],
        ]);

        // The same tier rules the public form runs: the desk is a faster path to
        // registration, not a cheaper one.
        FeeTier::guard($validated['fee_category'], $validated['participant_type'], $validated['country']);

        $user = new User([
            ...Arr::except($validated, 'fee_category'),
            'password' => Str::password(32),
        ]);
        $user->email_verified_at = now();
        $user->is_east_africa = FeeTier::isEastAfricaCountry($validated['country']);
        $user->assignFeeCategory($validated['fee_category']);
        $user->save();

        // Billing first: it moves the registrant to `submitted` and may attach a
        // control number, and the desk needs to see the state it left behind,
        // not the one from a moment earlier.
        $billing = $this->startBilling($user, $gepg);

        return response()->json([
            'message' => 'Attendee registered. They can pay with the control number below.',
            'user' => $this->registrantPayload($user->fresh()),
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
    public function requestControlNumber(Request $request, User $user, GepgService $gepg): JsonResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        if ($user->isPaid()) {
            return response()->json([
                'message' => "{$user->name} has already paid.",
                'user' => $this->registrantPayload($user),
                'billing' => ['status' => 'paid', 'control_number' => $user->control_number],
            ]);
        }

        return response()->json([
            'user' => $this->registrantPayload($user->fresh()),
            'billing' => $this->startBilling($user, $gepg),
        ]);
    }

    /** @return array{status: string, control_number: string|null, message: string} */
    private function startBilling(User $user, GepgService $gepg): array
    {
        if (! config('billing.enabled')) {
            return [
                'status' => 'unavailable',
                'control_number' => null,
                'message' => 'The payment portal is closed, so no control number could be issued.',
            ];
        }

        try {
            $gepg->requestControlNumber($user);
        } catch (RuntimeException $exception) {
            return [
                'status' => 'blocked',
                'control_number' => null,
                'message' => $exception->getMessage(),
            ];
        }

        $user->refresh();

        return $user->control_number
            ? [
                'status' => 'ready',
                'control_number' => $user->control_number,
                'message' => 'Give the attendee this control number to pay.',
            ]
            : [
                'status' => 'pending',
                'control_number' => null,
                'message' => 'The control number is being generated. Search for this attendee again in a moment.',
            ];
    }

    /**
     * Scan a badge QR code (its registration_code) and check the attendee in.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $user = User::where('registration_code', $request->code)->first();

        if (! $user) {
            return response()->json(['message' => 'No registrant found for this code.'], 404);
        }

        return $this->checkIn($user, $request);
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

        Mail::to($user->email)->send(new PaymentConfirmed($user));

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

        Mail::to($user->email)->send(new FeeWaived($user, $validated['notes']));

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

        $existing = $user->attendance()->first();

        if ($existing) {
            return response()->json([
                'already_checked_in' => true,
                'checked_in_at' => $existing->checked_in_at,
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
                ->where('active', true)
                ->orderBy('amount')
                ->get(['key', 'label', 'amount', 'currency']),
            'participant_types' => config('tmsc.participant_types'),
            'countries' => config('tmsc.countries'),
            'east_africa_countries' => config('tmsc.east_africa_countries'),
        ]);
    }
}
