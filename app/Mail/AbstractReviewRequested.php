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
            // Framed as a re-review, not a new one: the reviewer already knows
            // this abstract and will be judging it against their own earlier
            // comments rather than reading it cold.
            subject: $this->isRevision
                ? 'TMSC Abstract Awaiting Re-review'
                : 'New TMSC Abstract Awaiting Review',
        );
    }

    public function content(): Content
    {
        // Blind review: the author relation is deliberately not loaded — this
        // email goes to reviewers and must not carry the author's identity.
        $this->submission->loadMissing(['subtheme']);

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
