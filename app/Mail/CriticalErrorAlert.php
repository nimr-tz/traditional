<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CriticalErrorAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $context) {}

    public function envelope(): Envelope
    {
        $exception = class_basename($this->context['exception']['class'] ?? 'Exception');
        $route = $this->context['request']['route'] ?? $this->context['request']['path'] ?? 'console';

        return new Envelope(
            subject: sprintf('[TMSC] Critical error: %s on %s', $exception, $route),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.critical-error-alert',
            text: 'emails.critical-error-alert-text',
            with: ['context' => $this->context],
        );
    }
}
