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

class StudentVerificationApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Student status verified');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.student-verification-approved',
            text: 'emails.student-verification-approved-text',
            with: array_merge(ConferenceEmail::data($this->user), [
                'paymentUrl' => route('payment.show'),
            ]),
        );
    }
}
