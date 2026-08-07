<?php

namespace App\Support;

use App\Models\ConferenceSetting;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * How long presenters may keep changing their uploaded presentation.
 *
 * Presentations are not reviewed — whatever the presenter has uploaded when
 * this window closes is what gets presented. The cut-off is stored as a
 * conference setting so organizers can move it without a deploy, and is parsed
 * leniently because those settings are free-text rows holding a mix of formats.
 */
final class PresentationWindow
{
    /** End of the last day a presentation may be uploaded or replaced. */
    public static function deadline(): ?CarbonImmutable
    {
        $value = ConferenceSetting::get('presentation_deadline');

        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, config('app.timezone'))->endOfDay();
        } catch (Throwable) {
            // A garbled setting must not lock every presenter out of uploading.
            return null;
        }
    }

    public static function hasClosed(): bool
    {
        $deadline = self::deadline();

        return $deadline !== null && CarbonImmutable::now()->greaterThan($deadline);
    }

    public static function isOpen(): bool
    {
        return ! self::hasClosed();
    }

    /** @return array{deadline: string|null, is_open: bool, closed_message: string|null} */
    public static function toArray(): array
    {
        $deadline = self::deadline();
        $isOpen = self::isOpen();

        return [
            'deadline' => $deadline?->toDateString(),
            'is_open' => $isOpen,
            'closed_message' => $isOpen
                ? null
                : 'The deadline for presentation files passed on '.$deadline?->translatedFormat('j F Y').'. The file already uploaded is the one that will be presented.',
        ];
    }
}
