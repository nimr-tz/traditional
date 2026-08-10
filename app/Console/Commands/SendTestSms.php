<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsGateway;
use App\Support\TanzanianPhone;
use Illuminate\Console\Command;
use Throwable;

class SendTestSms extends Command
{
    protected $signature = 'sms:test {phone : Tanzanian number, e.g. 0712345678 or 255712345678}
                            {--message= : Override the default test message}';

    protected $description = 'Send a test SMS through the mGov gateway to verify credentials';

    public function handle(SmsGateway $gateway): int
    {
        $msisdn = TanzanianPhone::normalize($this->argument('phone'), 'Tanzania');

        if (! $msisdn) {
            $this->error('Not a recognisable Tanzanian mobile number.');

            return self::FAILURE;
        }

        if (config('sms.sandbox')) {
            $this->warn('SMS_SANDBOX is on — the message will be written to the log, not sent.');
        }

        $message = $this->option('message') ?: 'TMSC 2026: mGov SMS gateway test. No action needed.';

        try {
            $gateway->send($msisdn, $message, ['source' => 'sms:test']);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage().' — see the log for the gateway response.');

            return self::FAILURE;
        }

        $this->info("Accepted for delivery to {$msisdn}.");

        return self::SUCCESS;
    }
}
