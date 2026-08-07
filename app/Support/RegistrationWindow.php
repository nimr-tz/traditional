<?php

namespace App\Support;

use App\Models\ConferenceSetting;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Whether new registrations are currently being accepted.
 *
 * Two independent controls, both stored as conference settings so organizers
 * can change them from the admin panel without a deploy:
 *
 *  - `registration_deadline` (Y-m-d, optional) — registration closes at the end
 *    of that day in the app timezone. Unset means no date-based cutoff.
 *  - `registration_closed` ('1'/'0') — a manual switch to close (or hold open)
 *    immediately, regardless of the date.
 *
 * Both the public register routes and the register page's own copy read this,
 * so the door and the sign on it can't disagree.
 */
class RegistrationWindow
{
    public static function isClosedManually(): bool
    {
        return ConferenceSetting::get('registration_closed') === '1';
    }

    /**
     * End of the last day registrations are accepted, if a deadline is set.
     *
     * Parsed leniently rather than assumed to be Y-m-d: conference settings are
     * free-text rows and existing installs hold human-entered dates like
     * "6 August 2026" alongside canonical ones. An unparseable value means no
     * deadline — a garbled setting must not take the public register page down.
     */
    public static function deadline(): ?CarbonImmutable
    {
        $value = ConferenceSetting::get('registration_deadline');

        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, config('app.timezone'))->endOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    public static function hasPassedDeadline(): bool
    {
        $deadline = self::deadline();

        return $deadline !== null && CarbonImmutable::now()->greaterThan($deadline);
    }

    public static function isOpen(): bool
    {
        return ! self::isClosedManually() && ! self::hasPassedDeadline();
    }

    public static function closedMessage(): string
    {
        if (self::isClosedManually()) {
            return 'Registration for this conference is now closed.';
        }

        $deadline = self::deadline();

        return $deadline === null
            ? 'Registration for this conference is now closed.'
            : 'Registration closed on '.$deadline->translatedFormat('j F Y').'.';
    }

    /**
     * The shape the register page and welcome page use to describe the window.
     *
     * @return array{is_open: bool, deadline: string|null, closed_message: string|null}
     */
    public static function toArray(): array
    {
        $isOpen = self::isOpen();

        return [
            'is_open' => $isOpen,
            'deadline' => self::deadline()?->toDateString(),
            'closed_message' => $isOpen ? null : self::closedMessage(),
        ];
    }
}
