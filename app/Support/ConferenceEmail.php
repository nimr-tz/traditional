<?php

namespace App\Support;

use App\Models\ConferenceSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Vite;

final class ConferenceEmail
{
    /**
     * Send a transactional email to a registrant, skipping silently when there
     * is no address to send it to.
     *
     * Walk-ins registered at the venue desk may have no email at all — in the
     * room a phone number is the reliable contact, and `users.email` is nullable
     * for exactly that reason. Every send on the payment path has to tolerate
     * its absence: `Mail::to(null)` throws, and a throw here would abandon a
     * confirmed payment halfway. The SMS alongside still reaches them.
     */
    public static function sendTo(?object $recipient, Mailable $mailable): void
    {
        $address = $recipient->email ?? null;

        if (blank($address)) {
            return;
        }

        Mail::to($address)->send($mailable);
    }

    /**
     * Shared brand and recipient data for every transactional email.
     *
     * @return array{conferenceName: string, logoUrl: string, recipientName: string}
     */
    public static function data(?object $recipient = null): array
    {
        return [
            'conferenceName' => ConferenceSetting::get('conference_name', config('app.name')),
            'logoUrl' => Vite::asset('resources/images/nimr-logo.png'),
            'recipientName' => self::recipientName($recipient),
        ];
    }

    private static function recipientName(?object $recipient): string
    {
        if (! $recipient) {
            return 'Participant';
        }

        return trim(implode(' ', array_filter([
            $recipient->salutation ?? null,
            $recipient->name ?? null,
        ]))) ?: 'Participant';
    }
}
