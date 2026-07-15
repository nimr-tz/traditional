<?php

namespace App\Mail;

use App\Models\FeeCategory;
use App\Models\User;
use App\Support\ConferenceEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'TMSC Registration Received',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-confirmed',
            text: 'emails.registration-confirmed-text',
            with: array_merge(ConferenceEmail::data($this->user), [
                'user' => $this->user,
                'dashboardUrl' => url('/dashboard'),
                'feeCategoryLabel' => FeeCategory::query()
                    ->where('key', $this->user->fee_category)
                    ->value('label') ?? $this->user->fee_category,
            ]),
        );
    }
}
