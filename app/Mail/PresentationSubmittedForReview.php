<?php

namespace App\Mail;

use App\Models\AbstractSubmission;
use App\Support\ConferenceEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PresentationSubmittedForReview extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AbstractSubmission $submission, public bool $isReplacement = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isReplacement ? 'Replacement TMSC Presentation Awaiting Review' : 'New TMSC Presentation Awaiting Review',
        );
    }

    public function content(): Content
    {
        $this->submission->loadMissing('user');

        return new Content(
            view: 'emails.presentation-submitted-for-review',
            text: 'emails.presentation-submitted-for-review-text',
            with: array_merge(ConferenceEmail::data(), [
                'submission' => $this->submission,
                'isReplacement' => $this->isReplacement,
                'reviewUrl' => route('admin.abstracts.show', $this->submission),
            ]),
        );
    }
}
