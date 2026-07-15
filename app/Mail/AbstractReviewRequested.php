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

class AbstractReviewRequested extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AbstractSubmission $submission, public bool $isRevision = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isRevision
                ? 'Revised TMSC Abstract Awaiting Review'
                : 'New TMSC Abstract Awaiting Review',
        );
    }

    public function content(): Content
    {
        $this->submission->loadMissing(['user', 'subtheme']);

        return new Content(
            view: 'emails.abstract-review-requested',
            text: 'emails.abstract-review-requested-text',
            with: array_merge(ConferenceEmail::data(), [
                'submission' => $this->submission,
                'isRevision' => $this->isRevision,
                'reviewUrl' => route('admin.abstracts.show', $this->submission),
            ]),
        );
    }
}
