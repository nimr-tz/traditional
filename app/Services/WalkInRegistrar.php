<?php

namespace App\Services;

use App\Mail\FeeWaived;
use App\Models\FeeCategory;
use App\Models\Institution;
use App\Models\User;
use App\Services\Billing\GepgService;
use App\Support\ConferenceEmail;
use App\Support\FeeTier;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
 *  - Waiving a fee is a finance decision. Any other desk role can register a
 *    walk-in and hand out a control number, but only `register()` acting on
 *    behalf of someone with `canManageFinance()` may skip billing entirely.
 */
class WalkInRegistrar
{
    public function __construct(private GepgService $gepg) {}

    /**
     * The fields a walk-in registration needs, wherever it is being typed.
     *
     * Name and phone identify the person; `institution_id` picks from the same
     * list the public registration form offers (`other` plus `institution_other`
     * for free text), so the desk cannot spell the same institution three
     * different ways across the register. `country` and `fee_category` set the
     * price and are checked against each other by FeeTier::guard.
     *
     * Email is the one optional field. Plenty of attendees reach the desk
     * without an address they can recall or spell, and demanding one produces
     * invented addresses that bounce and pollute every campaign audience. The
     * phone number carries the control number instead, by SMS.
     *
     * `country` is the other optional one, but only when the category picked
     * is complimentary: it exists solely to pick a fee tier, and a
     * complimentary category has no tier to pick. Pass the fee category the
     * desk has (or is about to have) selected so this can be decided before
     * validation runs — the desk should never be made to answer a question
     * that cannot change what they owe.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?string $feeCategory = null): array
    {
        $isComplimentary = $feeCategory
            ? (bool) FeeCategory::where('key', $feeCategory)->value('is_complimentary')
            : false;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50'],
            'institution_id' => [
                'required', 'string',
                function ($attribute, $value, $fail) {
                    if ($value === 'other') {
                        return;
                    }
                    if (! Institution::where('id', $value)->where('active', true)->exists()) {
                        $fail('Please select a valid institution.');
                    }
                },
            ],
            'institution_other' => ['required_if:institution_id,other', 'nullable', 'string', 'max:255'],
            'participant_type' => ['required', Rule::in(array_keys(config('tmsc.participant_types')))],
            'country' => [$isComplimentary ? 'nullable' : 'required', Rule::in(config('tmsc.countries'))],
            'fee_category' => ['required', Rule::exists('fee_categories', 'key')->where('active', true)],
            // Staff eyeballed a student ID at the desk — the only way a walk-in
            // student is ever verified, since there is no document upload here.
            'student_verified_in_person' => ['sometimes', 'boolean'],
            // Finance-only: skip billing and admit free. Validated for shape
            // here; `register()` re-checks who is asking before honouring it.
            'waive' => ['sometimes', 'boolean'],
            'waive_notes' => ['required_if:waive,true', 'nullable', 'string', 'max:1000'],
        ];
    }

    private const NON_USER_FIELDS = [
        'fee_category', 'institution_id', 'institution_other',
        'student_verified_in_person', 'waive', 'waive_notes',
    ];

    /**
     * @param  array<string, mixed>  $validated  name, email, phone, institution_id,
     *                                           institution_other, participant_type,
     *                                           country, fee_category, and optionally
     *                                           student_verified_in_person, waive, waive_notes
     * @param  User  $staff  whoever is standing at the desk doing the registering —
     *                       recorded against a student verification or a waiver, and
     *                       checked before a waiver is honoured
     * @return array{user: User, billing: array{status: string, control_number: string|null, message: string}}
     */
    public function register(array $validated, User $staff): array
    {
        $category = FeeCategory::where('key', $validated['fee_category'])->active()->firstOrFail();

        $wantsWaiver = (bool) ($validated['waive'] ?? false);

        if ($wantsWaiver && ! $staff->canManageFinance()) {
            throw ValidationException::withMessages([
                'waive' => 'Only finance can waive a fee.',
            ]);
        }

        // Tier rules only govern paid categories — they exist to stop someone
        // paying an East African rate from abroad. A complimentary category has
        // no region and no student/participant split to police.
        if (! $category->isComplimentary()) {
            FeeTier::guard($validated['fee_category'], $validated['participant_type'], $validated['country'] ?? null);
        }

        if ($validated['institution_id'] === 'other') {
            $institutionId = null;
            $institutionName = $validated['institution_other'];
        } else {
            $institution = Institution::findOrFail($validated['institution_id']);
            $institutionId = $institution->id;
            $institutionName = $institution->name;
        }

        $user = new User([
            ...Arr::except($validated, self::NON_USER_FIELDS),
            'institution' => $institutionName,
            'institution_id' => $institutionId,
            // Blank rather than absent when the desk skipped it, so the unique
            // index sees a NULL instead of an empty string it would collide on
            // the second time.
            'email' => filled($validated['email'] ?? null) ? $validated['email'] : null,
            'password' => Str::password(32),
        ]);

        // Nobody is going to click a link in an inbox they are standing away
        // from, and a walk-in with no address could never verify at all.
        $user->email_verified_at = now();
        $user->is_east_africa = FeeTier::isEastAfricaCountry($validated['country'] ?? null);
        $user->assignFeeCategory($validated['fee_category']);
        $user->save();

        // A student ID checked in person at the desk is the walk-in equivalent
        // of the document review admins do for online registrants — there is no
        // upload here, so this is the only way a walk-in student is ever
        // verified. Without it they still register, but billing stays blocked
        // until they verify online, same as before.
        if ($user->requiresStudentVerification() && ($validated['student_verified_in_person'] ?? false)) {
            $user->forceFill([
                'student_verification_status' => 'verified',
                'student_verified_at' => now(),
                'student_verified_by' => $staff->id,
                'student_verification_notes' => "Student ID checked in person at the venue desk by {$staff->name}.",
            ])->save();
        }

        if ($category->isComplimentary()) {
            return ['user' => $this->admitComplimentary($user, $category), 'billing' => [
                'status' => 'complimentary',
                'control_number' => null,
                'message' => "Registered as {$category->label}. No fee is due — their badge is ready.",
            ]];
        }

        if ($wantsWaiver) {
            return ['user' => $this->admitWaived($user, $staff, $validated['waive_notes']), 'billing' => [
                'status' => 'waived',
                'control_number' => null,
                'message' => 'Fee waived. No control number is issued — their badge is ready.',
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
     * a room full of media and invited guests cannot inflate what was collected.
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
     * Admit someone finance has decided not to bill: no bill, no control
     * number, badge now — the same outcome as a complimentary category, but
     * decided per person on an otherwise-paying category rather than baked
     * into the category itself. Notes are required, same as any other waiver:
     * this is the one way in without money, so the reason is the audit trail.
     */
    private function admitWaived(User $user, User $staff, string $notes): User
    {
        $user->forceFill([
            'payment_status' => 'waived',
            'paid_at' => now(),
            'payment_verified_by' => $staff->id,
            'payment_notes' => $notes,
        ]);

        $user->generateRegistrationCode();
        $user->save();

        ConferenceEmail::sendTo($user, new FeeWaived($user, $notes));

        return $user->fresh();
    }

    /**
     * Kick off a control number for someone who does not have one, and report
     * back in the terms the desk needs.
     *
     * A walk-in student who was not verified in person at registration (see
     * `register()`) is the awkward case: they can pick a student rate, but GePG
     * will not issue against it until someone has seen their student ID.
     * Rather than block the registration, we complete it and hand over the
     * reason — they can still verify online before paying.
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
