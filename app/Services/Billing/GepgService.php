<?php

namespace App\Services\Billing;

use App\Jobs\AssignSandboxControlNumber;
use App\Mail\ControlNumberIssued;
use App\Mail\PaymentConfirmed;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Wraps bill submission to the NIMR Billing System (see api.md at the repo
 * root — the payload shapes here follow that spec, not AJSC's implementation,
 * which has drifted from it over time). Real credentials for TMSC are not
 * provisioned yet (NIMR finance owns those, separate from AJSC's), so while
 * config('billing.sandbox') is true this mimics the async control-number
 * assignment without calling the real API.
 */
class GepgService
{
    /**
     * Kick off a control number request via POST /api/bill-submission/.
     * Returns immediately with the request accepted; the control number
     * itself is assigned asynchronously via a callback (see
     * BillingCallbackController) — or, in sandbox mode, via
     * AssignSandboxControlNumber.
     */
    public function requestControlNumber(User $user): array
    {
        if (! $user->hasVerifiedStudentStatus()) {
            throw new RuntimeException('Your student status must be verified before a control number can be issued.');
        }

        if (config('billing.sandbox')) {
            $billingRequestId = 'SANDBOX-'.Str::upper(Str::random(12));

            $user->forceFill([
                'billing_request_id' => $billingRequestId,
                'payment_method' => 'gepg',
                'payment_status' => 'submitted',
                'control_number' => null,
            ])->save();

            AssignSandboxControlNumber::dispatch($user->id)->delay(now()->addSeconds(5));

            return ['success' => true, 'billing_request_id' => $billingRequestId];
        }

        $mapping = config("billing.mapping.{$user->fee_category}");

        if (! $mapping || ! $mapping['rev_src_id']) {
            throw new RuntimeException(
                "No NIMR billing revenue_source configured for fee category [{$user->fee_category}]. ".
                'Ask NIMR finance to register a RevenueSourceItem for this category and set its ID in .env.'
            );
        }

        $payload = [
            'sys_code' => config('billing.system_code'),
            'bill_dept' => config('billing.bill_dept'),
            'description' => $this->sanitize($mapping['description'], 45),
            'revenue_source' => $mapping['rev_src_id'],
            'qty' => 1,
            'currency' => $user->currency,
            'amount' => $user->fee_amount,
            'customer' => [
                'first_name' => $this->sanitize($user->first_name ?: $user->name),
                'middle_name' => $this->sanitize($user->middle_name ?: 'no middle name'),
                'last_name' => $this->sanitize($user->last_name ?: $user->first_name ?: $user->name),
                'cell_num' => $this->formatPhone($user->phone),
                'email' => $user->email,
            ],
        ];

        $response = Http::withHeaders(['Authorization' => 'Api-Key '.config('billing.api_key')])
            ->post(rtrim(config('billing.system_url'), '/').'/api/bill-submission/', $payload);

        if (! $response->successful()) {
            Log::error('NIMR Billing bill-submission failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'user_id' => $user->id,
            ]);

            throw new RuntimeException('The billing system did not accept the request. Please try again shortly.');
        }

        $billId = $response->json('bill_id');

        $user->forceFill([
            'billing_request_id' => $billId,
            'payment_method' => 'gepg',
            'payment_status' => 'submitted',
            'control_number' => null,
        ])->save();

        return ['success' => true, 'billing_request_id' => $billId];
    }

    public function generateControlNumber(): string
    {
        do {
            $number = (string) random_int(900000000000, 999999999999);
        } while (User::where('control_number', $number)->exists());

        return $number;
    }

    /**
     * Notify a registrant their control number is ready to pay — called once
     * from the sandbox job or the real control-number-assigned callback,
     * whichever path assigned it.
     */
    public function notifyControlNumberIssued(User $user): void
    {
        Mail::to($user->email)->send(new ControlNumberIssued($user));
    }

    /**
     * Mark a registrant as paid — called from the GePG payment callback or
     * the local sandbox simulation endpoint. Idempotent:
     * safe to call more than once for the same user.
     */
    public function confirmPayment(User $user): void
    {
        if ($user->payment_status === 'verified') {
            return;
        }

        $user->payment_status = 'verified';
        $user->paid_at = now();
        $user->generateRegistrationCode();
        $user->save();

        Mail::to($user->email)->send(new PaymentConfirmed($user));
    }

    /**
     * Strip characters the billing system's GePG XML bridge chokes on
     * (accents, apostrophes, etc.) — matches NREIMS's own sanitization.
     */
    private function sanitize(string $value, int $maxLength = 0): string
    {
        $clean = trim(preg_replace('/[^A-Za-z0-9 ]/', '', $value));

        return $maxLength > 0 ? substr($clean, 0, $maxLength) : $clean;
    }

    /**
     * Normalize to 255XXXXXXXXX (12 digits): strip non-digits, take the last
     * 9 digits, prepend the Tanzania country code — matches how the billing
     * system's `cell_num` is used elsewhere (mobile money / GePG channels
     * are Tanzania-only regardless of the registrant's own country).
     */
    private function formatPhone(?string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone ?? '');
        $local = str_pad(substr($digits, -9), 9, '0', STR_PAD_LEFT);

        return '255'.$local;
    }
}
