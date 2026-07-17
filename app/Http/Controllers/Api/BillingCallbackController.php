<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\GepgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $this->authorizeCallback($request);

        $request->validate([
            'bill_id' => 'required|string',
            'cntrl_num' => 'required|string',
            'bill_amt' => 'required|decimal:0,2|min:0',
        ]);

        $user = User::where('billing_request_id', $request->bill_id)->first();

        if (! $user) {
            return response()->json(['received' => true, 'note' => 'unknown bill_id']);
        }

        $this->assertExpectedAmount('bill_amt', $request->string('bill_amt')->toString(), (string) $user->fee_amount);

        if ($user->control_number === $request->cntrl_num) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        if ($user->control_number) {
            return response()->json([
                'received' => false,
                'message' => 'A different control number is already recorded for this bill.',
            ], 409);
        }

        $user->forceFill([
            'control_number' => $request->cntrl_num,
            'payment_notes' => 'Control number assigned: '.$request->cntrl_num,
        ])->save();

        $this->gepg->notifyControlNumberIssued($user);

        return response()->json(['received' => true, 'duplicate' => false]);
    }

    /**
     * The bill has been paid.
     */
    public function paymentReceived(Request $request): JsonResponse
    {
        $this->authorizeCallback($request);

        $request->validate([
            'bill_id' => 'required|string',
            'trx_id' => 'required|string|max:255',
            'bill_amt' => 'required|decimal:0,2|min:0',
            'paid_amt' => 'required|decimal:0,2|min:0',
            'paid_ccy' => 'required|string',
        ]);

        $user = User::where('billing_request_id', $request->bill_id)->first();

        if (! $user) {
            return response()->json(['received' => true, 'note' => 'unknown bill_id']);
        }

        if ($user->payment_transaction_id === $request->trx_id) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        if (User::where('payment_transaction_id', $request->trx_id)->exists()) {
            return response()->json([
                'received' => false,
                'message' => 'The payment transaction is already attached to another bill.',
            ], 409);
        }

        $currency = strtoupper($request->string('paid_ccy')->toString());
        if ($currency !== strtoupper((string) $user->currency)) {
            throw ValidationException::withMessages([
                'paid_ccy' => "Expected {$user->currency} for this bill.",
            ]);
        }

        $this->assertExpectedAmount('bill_amt', $request->string('bill_amt')->toString(), (string) $user->fee_amount);
        $this->assertExpectedAmount('paid_amt', $request->string('paid_amt')->toString(), (string) $user->fee_amount);

        $this->gepg->confirmPayment($user);

        $user->forceFill([
            'payment_transaction_id' => $request->trx_id,
            'payment_received_amount' => $request->paid_amt,
            'payment_received_currency' => $currency,
            'payment_notes' => "Verified via NIMR Billing. Paid: {$request->paid_amt} {$request->paid_ccy}",
        ])->save();

        return response()->json(['received' => true, 'duplicate' => false]);
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

    private function authorizeCallback(Request $request): void
    {
        $expected = (string) config('billing.callback_token');
        if ($expected === '') {
            return;
        }

        $provided = (string) $request->header('X-TMSC-Callback-Token');
        abort_unless($provided !== '' && hash_equals($expected, $provided), 403);
    }

    private function assertExpectedAmount(string $field, string $actual, string $expected): void
    {
        if ($this->decimalToCents($actual) !== $this->decimalToCents($expected)) {
            throw ValidationException::withMessages([
                $field => "Amount does not match the expected bill amount of {$expected}.",
            ]);
        }
    }

    private function decimalToCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim($amount), 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
