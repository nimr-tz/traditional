<?php

namespace App\Support;

use App\Models\ConferenceSetting;

/**
 * Whether new registrations are currently being accepted.
 *
 * One control, stored as a conference setting so organizers can change it from
 * the admin panel without a deploy: `registration_closed` ('1'/'0') shuts or
 * reopens the door immediately.
 *
 * There is deliberately no date-based cutoff. Registration stays open until
 * somebody closes it on purpose, so a date nobody revisited can't turn new
 * sign-ups away while the conference is still taking them.
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

    public static function isOpen(): bool
    {
        return ! self::isClosedManually();
    }

    public static function closedMessage(): string
    {
        return 'Registration for this conference is now closed.';
    }

    /**
     * The shape the register page and welcome page use to describe the window.
     *
     * @return array{is_open: bool, closed_message: string|null}
     */
    public static function toArray(): array
    {
        $isOpen = self::isOpen();

        return [
            'is_open' => $isOpen,
            'closed_message' => $isOpen ? null : self::closedMessage(),
        ];
    }
}
