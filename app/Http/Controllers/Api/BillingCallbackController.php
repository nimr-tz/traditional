<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\GepgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Receives the two callbacks the NIMR Billing System sends back once it has
 * relayed a result from GePG (see api.md's `bill-cntrl-num-response-callback`
 * and `bill-cntrl-num-payment-callback` payload shapes).
 *
 * Caveat on api.md: those two documented shapes are the billing system's *own*
 * inbound endpoints, which receive from GePG. What it forwards on to a
 * registered system is different and undocumented — see the note on
 * controlNumberAssigned(). The authority for the outbound shape is the billing
 * system's source: `billing/tasks.py` for the control number and
 * `billing/payment_notifications.py` for the payment.
 *
 * Both are dispatched unauthenticated and keyed by `bill_id`, which only we and
 * the billing system know; see authorizeCallback() for what guards them.
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

        // The billing system sends {req_id, bill_id, cntr_num, bill_print_url} —
        // `cntr_num` (not `cntrl_num`), as an integer, and with no amount at all.
        // api.md documents `cntrl_num` + `bill_amt`, but that describes the
        // billing system's own inbound endpoint, which receives from GePG — not
        // what it forwards on to a registered system. Accept both spellings, the
        // way AJSC does, so this survives either shape.
        $request->validate([
            'bill_id' => ['required', 'string'],
            'cntr_num' => ['required_without:cntrl_num', 'nullable'],
            'cntrl_num' => ['required_without:cntr_num', 'nullable'],
            'bill_amt' => ['sometimes', 'decimal:0,2', 'min:0'],
        ]);

        $controlNumber = trim((string) ($request->input('cntr_num') ?? $request->input('cntrl_num')));

        if ($controlNumber === '') {
            throw ValidationException::withMessages(['cntr_num' => 'A control number is required.']);
        }

        $user = User::where('billing_request_id', $request->bill_id)->first();

        if (! $user) {
            return response()->json(['received' => true, 'note' => 'unknown bill_id']);
        }

        // Only enforced when present — the real callback omits it entirely. The
        // amount that matters is checked on the payment callback, where money
        // actually changes hands.
        if ($request->filled('bill_amt')) {
            $this->assertExpectedAmount('bill_amt', $request->string('bill_amt')->toString(), (string) $user->fee_amount);
        }

        if ($user->control_number === $controlNumber) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        if ($user->control_number) {
            return response()->json([
                'received' => false,
                'message' => 'A different control number is already recorded for this bill.',
            ], 409);
        }

        $user->forceFill([
            'control_number' => $controlNumber,
            'payment_notes' => 'Control number assigned: '.$controlNumber,
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

    /**
     * The billing system dispatches both callbacks with only a Content-Type
     * header — no shared secret, and no way to configure one on its side. An IP
     * allowlist is therefore the only verification available in practice; the
     * token branch is kept for the day NIMR adds header support.
     *
     * Fails open when neither is configured, logging each unverified callback.
     * Failing closed would mean a half-configured deployment silently drops
     * every control number and payment confirmation, which is worse than an
     * unverified callback keyed on a bill_id only we and NIMR know.
     */
    private function authorizeCallback(Request $request): void
    {
        $allowedIps = array_filter(array_map('trim', explode(',', (string) config('billing.callback_allowed_ips'))));
        $expectedToken = (string) config('billing.callback_token');

        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            Log::warning('Billing callback rejected: IP not allowed', ['ip' => $request->ip()]);
            abort(403);
        }

        if ($expectedToken !== '') {
            $provided = (string) $request->header('X-TMSC-Callback-Token');

            if ($provided === '' || ! hash_equals($expectedToken, $provided)) {
                Log::warning('Billing callback rejected: invalid or missing token', ['ip' => $request->ip()]);
                abort(403);
            }
        }

        if ($allowedIps === [] && $expectedToken === '') {
            Log::warning('Billing callback accepted without verification — set BILLING_CALLBACK_ALLOWED_IPS', [
                'ip' => $request->ip(),
            ]);
        }
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
