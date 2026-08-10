<?php

namespace App\Support;

/**
 * Normalizes a registrant's free-text phone number into the 255XXXXXXXXX MSISDN
 * the mGov SMS gateway expects, or null when the number can't be trusted to be
 * Tanzanian.
 *
 * Returning null matters more than it looks: TMSC takes registrations from
 * across the world, `users.phone` is unvalidated free text, and mGov only
 * delivers to Tanzanian networks. Blindly prefixing 255 (which the billing
 * service does, because GePG payment channels are Tanzania-only anyway) would
 * turn a Kenyan "0712 345 678" into a real Tanzanian subscriber's number and
 * send them a stranger's control number. So a number is only accepted when it
 * either carries the 255 country code explicitly, or is a bare local number
 * belonging to a registrant whose country is Tanzania.
 */
class TanzanianPhone
{
    public static function normalize(?string $phone, ?string $country = null): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone ?? '');

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '255')) {
            $local = substr($digits, 3);

            return self::isLocalMobile($local) ? '255'.$local : null;
        }

        // A bare local number carries no country information of its own, so it
        // is only safe to read as Tanzanian when the registrant says they are.
        if ($country !== null && $country !== 'Tanzania') {
            return null;
        }

        $local = str_starts_with($digits, '0') ? substr($digits, 1) : $digits;

        return self::isLocalMobile($local) ? '255'.$local : null;
    }

    /** Tanzanian mobile subscriber numbers are 9 digits and start 6 or 7. */
    private static function isLocalMobile(string $local): bool
    {
        return (bool) preg_match('/^[67][0-9]{8}$/', $local);
    }
}
