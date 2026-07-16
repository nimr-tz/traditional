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

class PresentationApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AbstractSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your TMSC Presentation Has Been Approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.presentation-approved',
            text: 'emails.presentation-approved-text',
            with: array_merge(ConferenceEmail::data($this->submission->user), [
                'submission' => $this->submission,
            ]),
        );
    }
}
