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

class StudentVerificationSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $student, public bool $isReplacement = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isReplacement
                ? 'Replacement Student Document Awaiting Verification'
                : 'Student Status Awaiting Verification',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.student-verification-submitted',
            text: 'emails.student-verification-submitted-text',
            with: array_merge(ConferenceEmail::data(), [
                'student' => $this->student,
                'isReplacement' => $this->isReplacement,
                'reviewUrl' => route('admin.students.index', ['status' => 'pending', 'search' => $this->student->email]),
            ]),
        );
    }
}
