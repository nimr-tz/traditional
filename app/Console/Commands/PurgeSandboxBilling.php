<?php

namespace App\Console\Commands;

use App\Services\Billing\SandboxBillingPurger;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Console front-end for SandboxBillingPurger — see that class for what the
 * cleanup does and why. The same service backs the super-admin endpoint in
 * Admin\BillingMaintenanceController, for production where there is no shell.
 */
class PurgeSandboxBilling extends Command
{
    use ConfirmableTrait;

    protected $signature = 'billing:purge-sandbox
                            {--dry-run : Report what would change without writing anything}
                            {--allow-sandbox : Run even while billing.sandbox is still true}
                            {--force : Skip the confirmation prompt in production}';

    protected $description = 'Clear sandbox-issued control numbers and simulated payments after the real gateway goes live';

    public function handle(SandboxBillingPurger $purger): int
    {
        if (config('billing.sandbox') && ! $this->option('allow-sandbox')) {
            $this->error('Billing is still in sandbox mode (BILLING_SANDBOX=true).');
            $this->line('Clearing control numbers now would strand registrants: the replacement flow needs the');
            $this->line('real gateway configured before it can issue a valid number. Set BILLING_SANDBOX=false');
            $this->line('with real credentials first, or pass --allow-sandbox to override.');

            return self::FAILURE;
        }

        $plan = $purger->plan();

        if ($plan->isEmpty()) {
            $this->info('No sandbox billing artifacts found — nothing to do.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Status', 'Control number', 'Bill ID', 'Action'],
            $plan->map(fn (array $row) => [
                $row['user']->id,
                $row['user']->name,
                $row['user']->email,
                $row['user']->payment_status,
                $row['user']->control_number ?? '—',
                $row['user']->billing_request_id,
                $purger->describe($row['action']),
            ])->all(),
        );

        foreach ($plan as $row) {
            if ($row['action'] === SandboxBillingPurger::ACTION_REVOKE
                && ($row['attendance'] > 0 || $row['certificates'] > 0)) {
                $this->warn(
                    "User {$row['user']->id} ({$row['user']->email}) has {$row['attendance']} attendance record(s) ".
                    "and {$row['certificates']} certificate(s) issued against a simulated payment — review manually."
                );
            }
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run — no changes written.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $purger->purge($plan);

        $this->info("Cleared sandbox billing artifacts for {$plan->count()} registrant(s).");
        $this->line('Affected registrants must request a fresh control number from the payment page.');

        return self::SUCCESS;
    }
}
