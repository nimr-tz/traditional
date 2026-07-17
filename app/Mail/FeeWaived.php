<?php

namespace App\Mail;

use App\Models\User;
use App\Support\ConferenceEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeeWaived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public ?string $notes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'TMSC Registration Fee Waived');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.fee-waived',
            text: 'emails.fee-waived-text',
            with: array_merge(ConferenceEmail::data($this->user), [
                'notes' => $this->notes,
                'user' => $this->user,
            ]),
        );
    }
}
