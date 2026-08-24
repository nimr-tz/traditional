<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\SandboxBillingPurger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Super-admin billing maintenance for production, where there is no shell to
 * run `php artisan billing:purge-sandbox` from. Same service backs both, so the
 * endpoint and the command can never disagree about what a record deserves.
 */
class BillingMaintenanceController extends Controller
{
    public function __construct(private SandboxBillingPurger $purger) {}

    /**
     * Report — and optionally clear — the control numbers and simulated
     * payments left behind by sandbox mode.
     *
     * Defaults to a dry run: writing requires an explicit `dry_run=false`, so
     * a bare POST can only ever report. Refuses outright while billing is still
     * in sandbox mode, because clearing a control number before the real
     * gateway is configured strands the registrant with no way to get a valid
     * one — the same guard the console command applies.
     */
    public function purgeSandbox(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dry_run' => ['sometimes', 'boolean'],
            'allow_sandbox' => ['sometimes', 'boolean'],
        ]);

        $dryRun = (bool) ($data['dry_run'] ?? true);
        $allowSandbox = (bool) ($data['allow_sandbox'] ?? false);

        if (config('billing.sandbox') && ! $allowSandbox) {
            return response()->json([
                'applied' => false,
                'sandbox_mode' => true,
                'message' => 'Billing is still in sandbox mode (BILLING_SANDBOX=true). Clearing control numbers '.
                    'now would strand registrants: the replacement flow needs the real gateway configured before '.
                    'it can issue a valid number. Set BILLING_SANDBOX=false with real credentials first, or pass '.
                    'allow_sandbox=true to override.',
            ], 409);
        }

        $plan = $this->purger->plan();

        if ($plan->isEmpty()) {
            return response()->json([
                'applied' => false,
                'dry_run' => $dryRun,
                'count' => 0,
                'records' => [],
                'message' => 'No sandbox billing artifacts found — nothing to do.',
            ]);
        }

        if ($dryRun) {
            return response()->json([
                'applied' => false,
                'dry_run' => true,
                'count' => $plan->count(),
                'records' => $this->present($plan),
                'message' => 'Dry run — no changes written. Re-send with dry_run=false to apply.',
            ]);
        }

        $records = $this->present($plan);

        Log::warning('Sandbox billing purge invoked from the admin endpoint', [
            'actor_id' => Auth::id(),
            'count' => $plan->count(),
        ]);

        $this->purger->purge($plan, Auth::id());

        return response()->json([
            'applied' => true,
            'dry_run' => false,
            'count' => count($records),
            'records' => $records,
            'message' => 'Sandbox billing artifacts cleared. Affected registrants must request a fresh '.
                'control number from the payment page.',
        ]);
    }

    /**
     * @param  Collection<int, array{user: User, action: string, attendance: int, certificates: int}>  $plan
     * @return array<int, array<string, mixed>>
     */
    private function present(Collection $plan): array
    {
        return $plan->map(fn (array $row) => [
            'id' => $row['user']->id,
            'name' => $row['user']->full_name,
            'email' => $row['user']->email,
            'payment_status' => $row['user']->payment_status,
            'control_number' => $row['user']->control_number,
            'billing_request_id' => $row['user']->billing_request_id,
            'registration_code' => $row['user']->registration_code,
            'action' => $row['action'],
            'action_label' => $this->purger->describe($row['action']),
            // A revoked payment may already have been used at the door or on a
            // certificate. Those records are left untouched deliberately — a
            // human decides what to do — but they must not go unnoticed.
            'attendance_count' => $row['attendance'],
            'certificate_count' => $row['certificates'],
            'needs_manual_review' => $row['action'] === SandboxBillingPurger::ACTION_REVOKE
                && ($row['attendance'] > 0 || $row['certificates'] > 0),
        ])->values()->all();
    }
}
