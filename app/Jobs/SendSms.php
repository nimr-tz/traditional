<?php

namespace App\Jobs;

use App\Services\Sms\SmsGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 300];

    /**
     * @param  string  $msisdn  Recipient in 255XXXXXXXXX form.
     * @param  array<string, mixed>  $context  Logged alongside failures so a
     *                                         missing SMS can be traced back to
     *                                         the registrant and event.
     */
    public function __construct(
        public string $msisdn,
        public string $message,
        public array $context = [],
    ) {}

    public function handle(SmsGateway $gateway): void
    {
        $gateway->send($this->msisdn, $this->message, $this->context);
    }
}
