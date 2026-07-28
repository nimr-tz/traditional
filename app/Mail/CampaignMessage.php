<?php

namespace App\Mail;

use App\Models\EmailCampaign;
use App\Support\ConferenceEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An admin-composed announcement.
 *
 * Deliberately NOT ShouldQueue, unlike every transactional mailable here: it is
 * sent from inside SendEmailCampaign, which is itself queued. Queueing again
 * per recipient would fan out thousands of jobs and — worse — make the send
 * return before delivery is known, so the per-recipient outcome recorded
 * against the campaign would be a lie.
 */
class CampaignMessage extends Mailable
{
    use SerializesModels;

    public function __construct(
        public EmailCampaign $campaign,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign',
            text: 'emails.campaign-text',
            with: array_merge(ConferenceEmail::data(), [
                'recipientName' => $this->recipientName,
                'subject' => $this->campaign->subject,
                // Author-entered plain text. Blade escapes it on output and the
                // paragraph split below is the only formatting applied, so a
                // campaign body can never inject markup into the email.
                'paragraphs' => preg_split('/\R{2,}/', trim($this->campaign->body)) ?: [],
            ]),
        );
    }
}
