<?php

namespace App\Support;

use App\Models\ConferenceSetting;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * When certificates become available.
 *
 * Without a gate a certificate of participation could be downloaded the moment
 * someone was scanned through the door on the first morning — a certificate for
 * a conference that had not happened yet. They unlock partway through the final
 * day instead, once attending actually means something.
 *
 * Stored as a conference setting so organisers can move it on the day from the
 * admin panel, which they will: sessions overrun.
 */
final class CertificateWindow
{
    /** Fallback when no explicit time is configured: 2pm on the conference's closing day. */
    private const DEFAULT_HOUR = '14:00';

    public static function releaseAt(): ?CarbonImmutable
    {
        $explicit = ConferenceSetting::get('certificate_release_at');

        if (filled($explicit)) {
            try {
                return CarbonImmutable::parse($explicit, config('app.timezone'));
            } catch (Throwable) {
                // Fall through to the conference date rather than letting a
                // garbled setting hand out certificates early.
            }
        }

        $closingDay = ConferenceSetting::get('end_date') ?: ConferenceSetting::get('start_date');

        if (blank($closingDay)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($closingDay.' '.self::DEFAULT_HOUR, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * With no conference date and no explicit time there is nothing to wait for,
     * so certificates stay available — the same "a garbled setting must not take
     * the feature down" rule the other windows follow.
     */
    public static function isOpen(): bool
    {
        $releaseAt = self::releaseAt();

        return $releaseAt === null || CarbonImmutable::now()->greaterThanOrEqualTo($releaseAt);
    }

    public static function pendingMessage(): string
    {
        $releaseAt = self::releaseAt();

        return $releaseAt === null
            ? 'Certificates are not available yet.'
            : 'Certificates unlock on '.$releaseAt->translatedFormat('j F Y \a\t H:i').'.';
    }

    /**
     * @return array{is_open: bool, release_at: string|null, pending_message: string|null}
     */
    public static function toArray(): array
    {
        $isOpen = self::isOpen();

        return [
            'is_open' => $isOpen,
            'release_at' => self::releaseAt()?->toIso8601String(),
            'pending_message' => $isOpen ? null : self::pendingMessage(),
        ];
    }
}
