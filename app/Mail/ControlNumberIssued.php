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

class ControlNumberIssued extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your TMSC Control Number Is Ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.control-number-issued',
            text: 'emails.control-number-issued-text',
            with: array_merge(ConferenceEmail::data($this->user), [
                'user' => $this->user,
                'gepgPayeeName' => config('billing.payee_name'),
            ]),
        );
    }
}
