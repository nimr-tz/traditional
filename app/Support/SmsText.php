<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Merging a recipient's name into a campaign message.
 *
 * Personalisation reaches the gateway through the back door, so both hazards
 * that apply to message text apply here too. A name carrying an accent would
 * switch the whole message to UCS-2 and collapse the per-part budget from 160
 * characters to 70 — a two-part announcement silently becoming five, for every
 * recipient whose name happens to be "José". Names are therefore flattened to
 * the 7-bit alphabet before they go anywhere near a message.
 */
final class SmsText
{
    /** Stands in for a name that can't be used — reads naturally after "Hi" or "Dear". */
    public const FALLBACK_NAME = 'there';

    public const PLACEHOLDER = ':name';

    /**
     * The name as it should appear in a message: first name only, plain ASCII.
     *
     * Registrants type their names into a web form, so this has to cope with
     * "JOHN DOE" (caps lock left on, common enough to be worth handling) and
     * with names that flatten to nothing at all.
     */
    public static function displayName(?string $fullName): string
    {
        $first = trim(Str::before(trim((string) $fullName), ' '));

        if ($first === '') {
            return self::FALLBACK_NAME;
        }

        // Only reached for names typed entirely in capitals; anything with
        // deliberate internal capitals ("McMillan") is left exactly as given.
        if ($first === mb_strtoupper($first)) {
            $first = Str::title(mb_strtolower($first));
        }

        $ascii = trim(preg_replace('/[^\x20-\x7E]+/', '', Str::ascii($first)));

        return $ascii === '' ? self::FALLBACK_NAME : $ascii;
    }

    /** Replace the :name placeholder in a composed message. */
    public static function personalise(string $message, ?string $recipientName): string
    {
        return str_replace(self::PLACEHOLDER, self::displayName($recipientName), $message);
    }

    public static function hasPlaceholder(string $message): bool
    {
        return str_contains($message, self::PLACEHOLDER);
    }
}
