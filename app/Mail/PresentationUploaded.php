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

class PresentationUploaded extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AbstractSubmission $submission, public bool $isReplacement = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isReplacement ? 'TMSC Replacement Presentation Received' : 'TMSC Presentation Received',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.presentation-uploaded',
            text: 'emails.presentation-uploaded-text',
            with: array_merge(ConferenceEmail::data($this->submission->user), [
                'submission' => $this->submission,
                'isReplacement' => $this->isReplacement,
            ]),
        );
    }
}
