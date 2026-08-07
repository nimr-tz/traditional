<?php

namespace App\Jobs;

use App\Mail\CampaignMessage;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers one campaign to its pre-resolved recipient rows.
 *
 * The recipient list is materialised when the campaign is created, not
 * recomputed here: a segment like "unpaid registrants" shrinks as people pay,
 * so resolving it at delivery time would silently skip people the admin was
 * told they were emailing.
 *
 * One failed address must not abort the run, so each send is isolated and its
 * outcome recorded against that recipient.
 */
class SendEmailCampaign implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        $campaign = EmailCampaign::find($this->campaignId);

        if (! $campaign || $campaign->status === 'sent') {
            return;
        }

        $campaign->forceFill(['status' => 'sending'])->save();

        $campaign->recipients()
            ->where('status', 'pending')
            ->chunkById(100, function ($recipients) use ($campaign) {
                foreach ($recipients as $recipient) {
                    $this->deliver($campaign, $recipient);
                }
            });

        $campaign->forceFill([
            'status' => 'sent',
            'sent_count' => $campaign->recipients()->where('status', 'sent')->count(),
            'failed_count' => $campaign->recipients()->where('status', 'failed')->count(),
            'completed_at' => now(),
        ])->save();
    }

    private function deliver(EmailCampaign $campaign, EmailCampaignRecipient $recipient): void
    {
        try {
            Mail::to($recipient->email)->send(new CampaignMessage($campaign, $recipient->name));

            $recipient->forceFill(['status' => 'sent', 'error' => null, 'sent_at' => now()])->save();
        } catch (Throwable $exception) {
            // A bad address or a transient SMTP failure is expected at this
            // scale; record it against the row so an admin can see exactly who
            // missed out and retry, rather than losing the whole campaign.
            $recipient->forceFill([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ])->save();

            Log::warning('Campaign email failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
