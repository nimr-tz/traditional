<?php

namespace App\Support;

use App\Models\ConferenceSetting;
use Illuminate\Support\Facades\Vite;

final class ConferenceEmail
{
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
