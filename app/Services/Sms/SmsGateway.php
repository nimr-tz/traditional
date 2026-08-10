<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Transport for the eGA mGov SMS gateway (see "SMS Documentation/code-example.php"
 * for the reference implementation this follows).
 *
 * Only responsible for getting one message onto the wire. Deciding who gets an
 * SMS and what it says belongs to SmsNotifier; retries and queueing belong to
 * App\Jobs\SendSms.
 */
class SmsGateway
{
    /**
     * @param  string  $msisdn  Recipient in 255XXXXXXXXX form — see App\Support\TanzanianPhone.
     *
     * @throws RuntimeException when the gateway is unreachable or rejects the message.
     */
    public function send(string $msisdn, string $message, array $context = []): void
    {
        $payload = [
            'recipients' => $msisdn,
            'message' => $message,
            // Local time, no milliseconds: 2026-08-08 14:03:22. eGA's own example
            // (SMS Documentation/code-example.php) uses H:i:s.v, but the live
            // gateway rejects that with "Wrong datetime format, allowed format
            // 'yyyy-mm-dd hh:mm:ss'" — verified against it on 2026-08-08.
            'datetime' => now()->format('Y-m-d H:i:s'),
            'mobileServiceId' => (string) config('sms.mobile_service_id'),
            'senderId' => (string) config('sms.sender_id'),
            'messageId' => (string) Str::uuid(),
        ];

        if (config('sms.sandbox')) {
            Log::info('SMS (sandbox — not sent)', $context + [
                'recipient' => $msisdn,
                'message' => $message,
            ]);

            return;
        }

        // The signature covers the exact bytes on the wire, so the encoded body
        // is built once here and posted verbatim rather than re-encoded by the
        // HTTP client — any difference in escaping would invalidate the hash.
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $hash = base64_encode(hash_hmac('sha256', $body, (string) config('sms.api_key'), true));

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'hash' => $hash,
                'sysId' => (string) config('sms.system_id'),
            ])
                ->connectTimeout((int) config('sms.connect_timeout', 5))
                ->timeout((int) config('sms.request_timeout', 20))
                ->withBody($body, 'application/json')
                ->post((string) config('sms.url'));
        } catch (Throwable $exception) {
            Log::error('mGov SMS connection failed', $context + [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('The SMS gateway is unreachable.', previous: $exception);
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::error('mGov SMS rejected', $context + [
                'status' => $response->status(),
                'status_code' => $response->json('statusCode'),
                'status_message' => $response->json('statusMessage'),
            ]);

            throw new RuntimeException('The SMS gateway rejected the message.');
        }
    }
}
