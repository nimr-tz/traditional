<?php

namespace App\Services\Sms;

use App\Jobs\SendSms;
use App\Models\AbstractSubmission;
use App\Models\User;
use App\Support\TanzanianPhone;
use Illuminate\Support\Str;

/**
 * The conference's outbound SMS vocabulary: one method per thing we tell a
 * registrant. Callers sit next to the matching Mail::to(...) line and never
 * need to know whether the registrant is reachable by SMS, whether the event is
 * switched on, or how the text is built — every send is best-effort and silently
 * skipped when it can't happen, because email remains the primary channel and no
 * SMS problem should ever break a registration or a payment callback.
 *
 * Adding a message (a payment reminder, a programme change) is a template in
 * config/sms.php, a key in config('sms.events'), and one method here.
 */
class SmsNotifier
{
    public function registered(User $user): void
    {
        $this->sendToUser($user, 'registered', [
            ':name' => $this->firstName($user),
            ':url' => $this->url(),
        ]);
    }

    public function controlNumberIssued(User $user): void
    {
        if (! $user->control_number) {
            return;
        }

        $this->sendToUser($user, 'control_number', [
            ':control_number' => $user->control_number,
            ':currency' => $user->currency,
            ':amount' => $this->amount($user),
        ]);
    }

    public function paymentConfirmed(User $user): void
    {
        $this->sendToUser($user, 'payment_confirmed', []);
    }

    public function abstractDecision(AbstractSubmission $abstract): void
    {
        // Only the three terminal-ish decisions have a template; anything else
        // (a status the review flow adds later) stays email-only until someone
        // writes wording for it.
        $template = match ($abstract->status) {
            'accepted' => 'abstract_accepted',
            'rejected' => 'abstract_rejected',
            'revision_requested' => 'abstract_revision_requested',
            default => null,
        };

        if (! $template || ! $abstract->user) {
            return;
        }

        $this->sendToUser($abstract->user, $template, [
            ':url' => $this->url(),
        ], eventKey: 'abstract_decision');
    }

    /**
     * @param  array<string, string>  $replacements
     * @param  string|null  $eventKey  config('sms.events') key, when it differs
     *                                 from the template name.
     */
    private function sendToUser(User $user, string $template, array $replacements, ?string $eventKey = null): void
    {
        if (! config('sms.enabled') || ! config('sms.events.'.($eventKey ?? $template))) {
            return;
        }

        $msisdn = TanzanianPhone::normalize($user->phone, $user->country);

        if (! $msisdn) {
            return;
        }

        $message = strtr((string) config("sms.templates.{$template}"), $replacements);

        SendSms::dispatch($msisdn, $message, [
            'user_id' => $user->id,
            'template' => $template,
        ]);
    }

    private function firstName(User $user): string
    {
        return $user->first_name ?: Str::before($user->name, ' ');
    }

    private function amount(User $user): string
    {
        return number_format((float) $user->fee_amount, 0);
    }

    private function url(): string
    {
        return Str::of((string) config('app.url'))->after('://')->rtrim('/')->toString();
    }
}
