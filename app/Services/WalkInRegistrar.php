<?php

namespace App\Services;

use App\Models\FeeCategory;
use App\Models\User;
use App\Services\Billing\GepgService;
use App\Support\FeeTier;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Registering someone who turned up at the venue, and getting them something to
 * pay with.
 *
 * Shared by the check-in app (Api\CheckinController) and the web desk
 * (Staff\DashboardController) so the two cannot drift into disagreeing about
 * what a walk-in costs or when they get a badge. The rules that must hold
 * wherever the desk is standing:
 *
 *  - A walk-in is charged the real fee for their tier. The desk is a faster way
 *    to register, not a cheaper one.
 *  - They land unpaid. No badge is minted until the payment is verified or
 *    waived, so nobody walks in on entry that was never paid for.
 *  - Billing is a GePG control number, which they can settle by bank or mobile
 *    without leaving the queue.
 */
class WalkInRegistrar
{
    public function __construct(private GepgService $gepg) {}

    /**
     * The fields a walk-in registration needs, wherever it is being typed.
     *
     * Name, phone and institution identify the person; `country` and
     * `fee_category` set the price and are checked against each other by
     * FeeTier::guard.
     *
     * Email is the one optional field. Plenty of attendees reach the desk
     * without an address they can recall or spell, and demanding one produces
     * invented addresses that bounce and pollute every campaign audience. The
     * phone number carries the control number instead, by SMS.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50'],
            'institution' => ['required', 'string', 'max:255'],
            'participant_type' => ['required', Rule::in(array_keys(config('tmsc.participant_types')))],
            'country' => ['required', Rule::in(config('tmsc.countries'))],
            'fee_category' => ['required', Rule::exists('fee_categories', 'key')->where('active', true)],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated  name, email, phone, institution,
     *                                           participant_type, country, fee_category
     * @return array{user: User, billing: array{status: string, control_number: string|null, message: string}}
     */
    public function register(array $validated): array
    {
        $category = FeeCategory::where('key', $validated['fee_category'])->active()->firstOrFail();

        // Tier rules only govern paid categories — they exist to stop someone
        // paying an East African rate from abroad. A complimentary category has
        // no region and no student/participant split to police.
        if (! $category->isComplimentary()) {
            FeeTier::guard($validated['fee_category'], $validated['participant_type'], $validated['country']);
        }

        $user = new User([
            ...Arr::except($validated, 'fee_category'),
            // Blank rather than absent when the desk skipped it, so the unique
            // index sees a NULL instead of an empty string it would collide on
            // the second time.
            'email' => filled($validated['email'] ?? null) ? $validated['email'] : null,
            'password' => Str::password(32),
        ]);

        // Nobody is going to click a link in an inbox they are standing away
        // from, and a walk-in with no address could never verify at all.
        $user->email_verified_at = now();
        $user->is_east_africa = FeeTier::isEastAfricaCountry($validated['country']);
        $user->assignFeeCategory($validated['fee_category']);
        $user->save();

        if ($category->isComplimentary()) {
            return ['user' => $this->admitComplimentary($user, $category), 'billing' => [
                'status' => 'complimentary',
                'control_number' => null,
                'message' => "Registered as {$category->label}. No fee is due — their badge is ready.",
            ]];
        }

        // Billing first: it moves the registrant to `submitted` and may attach a
        // control number, and the desk needs the state it left behind, not the
        // one from a moment earlier.
        $billing = $this->startBilling($user);

        return ['user' => $user->fresh(), 'billing' => $billing];
    }

    /**
     * Admit someone who attends by role: no bill, no control number, badge now.
     *
     * Recorded as `waived` rather than `verified` because no money arrived —
     * the finance dashboard counts waived separately from realised revenue, so
     * a room full of media and secretariat cannot inflate what was collected.
     */
    private function admitComplimentary(User $user, FeeCategory $category): User
    {
        $user->forceFill([
            'payment_status' => 'waived',
            'paid_at' => now(),
            'payment_notes' => "Complimentary registration: {$category->label}. No fee due.",
        ]);

        if (! $user->registration_code) {
            $user->generateRegistrationCode();
        }

        $user->save();

        return $user->fresh();
    }

    /**
     * Kick off a control number for someone who does not have one, and report
     * back in the terms the desk needs.
     *
     * A walk-in student is the awkward case: they can pick a student rate, but
     * GePG will not issue against it until someone has seen their student ID,
     * and there is no document upload at the desk. Rather than block the
     * registration, we complete it and hand over the reason.
     *
     * @return array{status: string, control_number: string|null, message: string}
     */
    public function startBilling(User $user): array
    {
        if ($user->isPaid()) {
            return [
                'status' => 'paid',
                'control_number' => $user->control_number,
                'message' => 'This registration is already settled.',
            ];
        }

        if (! config('billing.enabled')) {
            return [
                'status' => 'unavailable',
                'control_number' => null,
                'message' => 'The payment portal is closed, so no control number could be issued.',
            ];
        }

        try {
            $this->gepg->requestControlNumber($user);
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
}
