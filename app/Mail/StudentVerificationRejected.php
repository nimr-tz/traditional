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

class StudentVerificationRejected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your TMSC registration category has been updated');
    }

    public function content(): Content
    {
        $categoryLabel = FeeCategory::query()
            ->where('key', $this->user->fee_category)
            ->value('label') ?? $this->user->fee_category;

        return new Content(
            view: 'emails.student-verification-rejected',
            text: 'emails.student-verification-rejected-text',
            with: array_merge(ConferenceEmail::data($this->user), [
                'notes' => $this->user->student_verification_notes,
                'paymentUrl' => route('payment.show'),
                'categoryLabel' => $categoryLabel,
                // A number already in their hands is honoured at the amount it
                // was raised for, so the mail must not claim it was cancelled.
                'keptControlNumber' => $this->user->control_number,
                'feeAmount' => $this->user->fee_amount !== null
                    ? $this->user->currency.' '.number_format((float) $this->user->fee_amount, 2)
                    : null,
            ]),
        );
    }
}
