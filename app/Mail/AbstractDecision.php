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

class AbstractDecision extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AbstractSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->submission->status) {
                'accepted' => 'Your TMSC Abstract Has Been Accepted',
                'revision_requested' => 'Revision Requested for Your TMSC Abstract',
                default => 'TMSC Abstract Decision',
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.abstract-decision',
            text: 'emails.abstract-decision-text',
            with: array_merge(ConferenceEmail::data($this->submission->user), [
                'submission' => $this->submission,
                'submissionsUrl' => route('abstracts.index'),
            ]),
        );
    }
}
