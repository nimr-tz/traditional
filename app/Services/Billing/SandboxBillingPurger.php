<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Clears the billing artifacts left behind by sandbox mode once the real NIMR
 * Billing / GePG gateway is live.
 *
 * While config('billing.sandbox') was true, AssignSandboxControlNumber minted
 * locally-generated control numbers and the sandbox/simulate endpoint could
 * flip a registrant to "verified" without any money moving. Both are keyed by
 * a `SANDBOX-` billing_request_id. Left in place after cutover they are worse
 * than useless: a fake control number is rejected at every bank / mobile-money
 * GePG channel, and a fake "verified" inflates finance's realized revenue and
 * issues a badge for a registration nobody paid for.
 *
 * Shared by the `billing:purge-sandbox` command and the super-admin endpoint in
 * Admin\BillingMaintenanceController so the two can never disagree about what a
 * given record deserves.
 */
class SandboxBillingPurger
{
    public const ACTION_STRIP_IDS = 'strip_ids';

    public const ACTION_REVOKE = 'revoke_simulated_payment';

    public const ACTION_RESET = 'reset_to_pending';

    /**
     * Build the list of affected registrants and what each one deserves,
     * without touching anything.
     *
     * @return Collection<int, array{user: User, action: string, attendance: int, certificates: int}>
     */
    public function plan(): Collection
    {
        return User::query()
            ->where('billing_request_id', 'like', 'SANDBOX-%')
            ->withCount(['attendance', 'certificates'])
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => [
                'user' => $user,
                'action' => $this->classify($user),
                'attendance' => (int) $user->attendance_count,
                'certificates' => (int) $user->certificates_count,
            ]);
    }

    /**
     * Apply a plan produced by plan(). Runs in a single transaction so a
     * partial purge can't leave half the cohort in an inconsistent state.
     *
     * @param  Collection<int, array{user: User, action: string, attendance: int, certificates: int}>  $plan
     */
    public function purge(Collection $plan, ?int $actorId = null): void
    {
        DB::transaction(function () use ($plan, $actorId) {
            foreach ($plan as $row) {
                $this->apply($row['user'], $row['action'], $actorId);
            }
        });
    }

    public function describe(string $action): string
    {
        return match ($action) {
            self::ACTION_STRIP_IDS => 'Keep finance decision, drop sandbox IDs',
            self::ACTION_REVOKE => 'REVOKE simulated payment + badge code',
            self::ACTION_RESET => 'Clear fake control number, reset to pending',
        };
    }

    /**
     * Decide what a given sandbox record deserves. The discriminator for a
     * fabricated payment is `payment_verified_by`: the sandbox simulate
     * endpoint never sets it, whereas a human at finance verifying or waiving
     * a fee always does. A finance decision is a real business decision and
     * must survive this cleanup — only the bogus billing identifiers go.
     */
    private function classify(User $user): string
    {
        if ($user->payment_verified_by !== null) {
            return self::ACTION_STRIP_IDS;
        }

        if (in_array($user->payment_status, ['verified', 'waived'], true)) {
            return self::ACTION_REVOKE;
        }

        return self::ACTION_RESET;
    }

    private function apply(User $user, string $action, ?int $actorId): void
    {
        $previous = [
            'payment_status' => $user->payment_status,
            'control_number' => $user->control_number,
            'billing_request_id' => $user->billing_request_id,
            'registration_code' => $user->registration_code,
        ];

        $changes = [
            'billing_request_id' => null,
            'billing_requested_at' => null,
            'control_number' => null,
        ];

        if ($action === self::ACTION_REVOKE) {
            $changes += [
                'payment_status' => 'pending',
                'paid_at' => null,
                'registration_code' => null,
                'payment_transaction_id' => null,
                'payment_received_amount' => null,
                'payment_received_currency' => null,
                'payment_notes' => 'Sandbox-simulated payment revoked at gateway cutover — no funds were received.',
            ];
        }

        if ($action === self::ACTION_RESET) {
            $changes += [
                'payment_status' => 'pending',
                'payment_notes' => 'Sandbox control number cleared at gateway cutover — request a new one.',
            ];
        }

        $user->forceFill($changes)->save();

        Log::warning('Purged sandbox billing artifacts', [
            'user_id' => $user->id,
            'action' => $action,
            'actor_id' => $actorId,
            'previous' => $previous,
        ]);
    }
}
