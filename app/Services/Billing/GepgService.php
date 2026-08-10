<?php

namespace App\Services\Billing;

use App\Jobs\AssignSandboxControlNumber;
use App\Mail\ControlNumberIssued;
use App\Mail\PaymentConfirmed;
use App\Models\FeeCategory;
use App\Models\User;
use App\Services\Sms\SmsNotifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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
        if (config('billing.sandbox') && app()->isProduction()) {
            // Sandbox mints a fake, locally-generated control number — real bank/mobile-money
            // GePG channels correctly reject it as invalid. Refuse outright rather than let a
            // real registrant walk away with a number that can never be paid; this throws
            // uncaught (like the missing-revenue-source case below) so the existing critical
            // error alerting pages ops instead of failing silently.
            Log::critical('Refused to issue a sandbox control number in production — billing is not configured for this environment', [
                'user_id' => $user->id,
            ]);

            throw new RuntimeException('Payments are not available yet. Please contact the organizers.');
        }

        if (! $user->hasVerifiedStudentStatus()) {
            throw new RuntimeException('Your student status must be verified before a control number can be issued.');
        }

        $canReplaceSandboxBillingRequest = ! config('billing.sandbox')
            && $user->hasSandboxBillingRequest();

        if ($user->billing_request_id && ! $canReplaceSandboxBillingRequest) {
            if ($user->control_number) {
                return ['success' => true, 'billing_request_id' => $user->billing_request_id];
            }

            $requestedAt = $user->billing_requested_at ?? $user->updated_at;
            $retryAfter = max(1, (int) config('billing.request_retry_minutes', 10));

            if ($requestedAt && $requestedAt->gt(now()->subMinutes($retryAfter))) {
                return ['success' => true, 'billing_request_id' => $user->billing_request_id];
            }

            Log::warning('Retrying stale TMSC billing request', [
                'user_id' => $user->id,
                'previous_bill_id' => $user->billing_request_id,
                'requested_at' => $requestedAt?->toIso8601String(),
            ]);
        }

        if (config('billing.sandbox')) {
            $billingRequestId = 'SANDBOX-'.Str::upper(Str::random(12));

            $user->forceFill([
                'billing_request_id' => $billingRequestId,
                'payment_method' => 'gepg',
                'payment_status' => 'submitted',
                'control_number' => null,
                'billing_requested_at' => now(),
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

        try {
            $response = Http::withHeaders(['Authorization' => 'Api-Key '.config('billing.api_key')])
                ->acceptJson()
                ->connectTimeout((int) config('billing.connect_timeout', 5))
                ->timeout((int) config('billing.request_timeout', 20))
                ->retry(2, 500, throw: false)
                ->post(rtrim(config('billing.system_url'), '/').'/api/bill-submission/', $payload);
        } catch (Throwable $exception) {
            Log::error('NIMR Billing connection failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'user_id' => $user->id,
            ]);

            throw new RuntimeException('The billing system is temporarily unreachable. Please try again shortly.');
        }

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
            'billing_requested_at' => now(),
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
        app(SmsNotifier::class)->controlNumberIssued($user);
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
        app(SmsNotifier::class)->paymentConfirmed($user);
    }

    /**
     * Fetch an invoice or receipt PDF for a registrant's bill (see api.md's
     * `/api/billing/bills/{bill_id}/invoice/` and `.../receipt/`). Readiness rules
     * mirror api.md's 409 cases: an invoice needs a control number, a receipt needs
     * a recorded payment. In sandbox mode there is no real bill to fetch from, so
     * we render the same document locally with dompdf instead of calling out.
     *
     * @return array{success: bool, body?: string, content_type?: string, filename?: string, message?: string}
     */
    public function fetchBillDocument(User $user, string $documentType): array
    {
        if (! $user->billing_request_id) {
            return ['success' => false, 'message' => 'No billing ID found for this registrant.'];
        }

        if ($documentType === 'invoice' && ! $user->control_number) {
            return ['success' => false, 'message' => 'The invoice is not ready yet — no control number has been assigned.'];
        }

        if ($documentType === 'receipt' && ! in_array($user->payment_status, ['verified', 'waived'], true)) {
            return ['success' => false, 'message' => 'The receipt is only available after payment has been verified.'];
        }

        if (config('billing.sandbox')) {
            return $this->renderSandboxDocument($user, $documentType);
        }

        $billId = $user->billing_request_id;

        $response = Http::withHeaders(['Authorization' => 'Api-Key '.config('billing.api_key')])
            ->get(rtrim(config('billing.system_url'), '/')."/api/billing/bills/{$billId}/{$documentType}/");

        if ($response->status() === 404) {
            return ['success' => false, 'message' => 'Bill not found.'];
        }

        if ($response->status() === 409) {
            $reason = $response->json('reason');

            return ['success' => false, 'message' => $reason === 'missing_control_number'
                ? 'The invoice is not ready yet — no control number has been assigned.'
                : 'The receipt is not ready yet — no payment has been recorded.'];
        }

        if (! $response->successful()) {
            Log::error('NIMR Billing document download failed', [
                'status' => $response->status(),
                'bill_id' => $billId,
                'document_type' => $documentType,
            ]);

            return ['success' => false, 'message' => 'Unable to download the document right now.'];
        }

        return [
            'success' => true,
            'body' => $response->body(),
            'content_type' => 'application/pdf',
            'filename' => "{$documentType}-{$billId}.pdf",
        ];
    }

    /**
     * Sandbox stand-in for the real NIMR document endpoints: renders the same
     * invoice/receipt shape locally with dompdf so finance and registrants can
     * use the feature end-to-end before real credentials are provisioned.
     *
     * @return array{success: bool, body: string, content_type: string, filename: string}
     */
    private function renderSandboxDocument(User $user, string $documentType): array
    {
        $categoryLabel = FeeCategory::query()->where('key', $user->fee_category)->value('label') ?? $user->fee_category;

        $pdf = Pdf::loadView("pdf.{$documentType}", [
            'user' => $user,
            'categoryLabel' => $categoryLabel,
            'payeeName' => config('billing.payee_name'),
        ])->setPaper('a4');

        return [
            'success' => true,
            'body' => $pdf->output(),
            'content_type' => 'application/pdf',
            'filename' => "{$documentType}-{$user->billing_request_id}.pdf",
        ];
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
