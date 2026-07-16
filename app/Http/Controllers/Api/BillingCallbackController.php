<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\GepgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Receives the two callbacks the NIMR Billing System sends back once it has
 * relayed a result from GePG (see api.md's `bill-cntrl-num-response-callback`
 * and `bill-cntrl-num-payment-callback` payload shapes — api.md is the
 * source of truth here, not AJSC's controller, which has drifted). api.md
 * states callback endpoints are unauthenticated; both are keyed by `bill_id`,
 * which only we and the billing system know.
 */
class BillingCallbackController extends Controller
{
    public function __construct(private GepgService $gepg) {}

    /**
     * A control number has been assigned to a previously submitted bill.
     */
    public function controlNumberAssigned(Request $request): JsonResponse
    {
        $request->validate([
            'bill_id' => 'required|string',
            'cntrl_num' => 'required|string',
        ]);

        $user = User::where('billing_request_id', $request->bill_id)->first();

        if (! $user) {
            return response()->json(['received' => true, 'note' => 'unknown bill_id']);
        }

        $user->forceFill([
            'control_number' => $request->cntrl_num,
            'payment_notes' => 'Control number assigned: '.$request->cntrl_num,
        ])->save();

        $this->gepg->notifyControlNumberIssued($user);

        return response()->json(['received' => true]);
    }

    /**
     * The bill has been paid.
     */
    public function paymentReceived(Request $request): JsonResponse
    {
        $request->validate([
            'bill_id' => 'required|string',
            'paid_amt' => 'required',
            'paid_ccy' => 'required|string',
        ]);

        $user = User::where('billing_request_id', $request->bill_id)->first();

        if (! $user) {
            return response()->json(['received' => true, 'note' => 'unknown bill_id']);
        }

        $this->gepg->confirmPayment($user);

        $user->forceFill([
            'payment_notes' => "Verified via NIMR Billing. Paid: {$request->paid_amt} {$request->paid_ccy}",
        ])->save();

        return response()->json(['received' => true]);
    }

    /**
     * Dev-only convenience endpoint to simulate a successful payment, since
     * live billing credentials for TMSC aren't provisioned yet.
     */
    public function simulate(User $user): RedirectResponse
    {
        abort_unless(config('billing.sandbox') && ! app()->isProduction(), 404);
        abort_if(! $user->control_number, 422, 'This user has no control number yet.');

        $this->gepg->confirmPayment($user);

        return back()->with('success', 'Sandbox payment simulated — status is now Paid.');
    }
}
