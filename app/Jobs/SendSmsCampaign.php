<?php

namespace App\Jobs;

use App\Models\SmsCampaign;
use App\Models\SmsCampaignRecipient;
use App\Services\Sms\SmsGateway;
use App\Support\SmsText;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers one SMS campaign to its pre-resolved recipient rows. Mirrors
 * App\Jobs\SendEmailCampaign: the recipient list (already filtered to usable
 * Tanzanian numbers) is materialised when the campaign is created, not
 * recomputed here, and one failed send must not abort the run.
 */
class SendSmsCampaign implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $campaignId) {}

    public function handle(SmsGateway $gateway): void
    {
        $campaign = SmsCampaign::find($this->campaignId);

        if (! $campaign || $campaign->status === 'sent') {
            return;
        }

        $campaign->forceFill(['status' => 'sending'])->save();

        $campaign->recipients()
            ->where('status', 'pending')
            ->chunkById(100, function ($recipients) use ($campaign, $gateway) {
                foreach ($recipients as $recipient) {
                    $this->deliver($campaign, $recipient, $gateway);
                }
            });

        $campaign->forceFill([
            'status' => 'sent',
            'sent_count' => $campaign->recipients()->where('status', 'sent')->count(),
            'failed_count' => $campaign->recipients()->where('status', 'failed')->count(),
            'completed_at' => now(),
        ])->save();
    }

    private function deliver(SmsCampaign $campaign, SmsCampaignRecipient $recipient, SmsGateway $gateway): void
    {
        try {
            $gateway->send($recipient->phone, SmsText::personalise($campaign->message, $recipient->name), [
                'sms_campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
            ]);

            $recipient->forceFill(['status' => 'sent', 'error' => null, 'sent_at' => now()])->save();
        } catch (Throwable $exception) {
            // A rejected number or a transient gateway failure is expected at
            // this scale; record it against the row so an admin can see
            // exactly who missed out, rather than losing the whole campaign.
            $recipient->forceFill([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ])->save();

            Log::warning('SMS campaign message failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
