<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * The rules tying a registrant's declared identity to the fee category they're
 * allowed to pick.
 *
 * `fee_categories.key` encodes two independent axes — student vs. participant,
 * and East African vs. international — and the price differs on both (a
 * non-East-African participant pays USD 150 where an East African pays
 * TZS 150,000). Neither axis is trustworthy coming from the browser, so both
 * registration and the self-service category edit in profile settings run the
 * selection back through `guard()` before it is stored.
 */
class FeeTier
{
    public const EAST_AFRICA = 'east_africa';

    public const INTERNATIONAL = 'international';

    public static function isEastAfricaCountry(?string $country): bool
    {
        return in_array($country, config('tmsc.east_africa_countries'), true);
    }

    public static function isStudentCategory(?string $key): bool
    {
        return str_starts_with((string) $key, 'student_');
    }

    /**
     * Which regional tier a fee category key belongs to, or null when the key
     * doesn't follow the `*_east_africa` / `*_non_east_africa` convention.
     *
     * Returning null rather than guessing keeps `guard()` from rejecting a
     * legitimate selection if the fee table ever grows a differently-shaped
     * key; `FeeTierTest` asserts every seeded key still resolves, so such a key
     * fails the build instead of quietly skipping the check in production.
     */
    public static function regionOf(?string $key): ?string
    {
        $key = (string) $key;

        if (str_ends_with($key, '_non_east_africa')) {
            return self::INTERNATIONAL;
        }

        if (str_ends_with($key, '_east_africa')) {
            return self::EAST_AFRICA;
        }

        return null;
    }

    public static function regionOfCountry(?string $country): string
    {
        return self::isEastAfricaCountry($country) ? self::EAST_AFRICA : self::INTERNATIONAL;
    }

    /**
     * The standard participant category matching a student category's region —
     * what a registrant is moved onto when their student status is refused.
     *
     * Derived from the region rather than a hardcoded pair, so a rejected East
     * African student lands on the East African participant rate and an
     * international one on the international rate. Returns null when the key
     * doesn't resolve to a region, which the caller must treat as "cannot
     * convert" rather than guessing a price.
     */
    public static function participantEquivalentOf(?string $studentCategory): ?string
    {
        return match (self::regionOf($studentCategory)) {
            self::EAST_AFRICA => 'participant_east_africa',
            self::INTERNATIONAL => 'participant_non_east_africa',
            default => null,
        };
    }

    /**
     * The student category matching a category's region — what an approved
     * claim moves the registrant onto.
     *
     * The mirror of `participantEquivalentOf()`: approval is the only thing that
     * grants the student rate, so this runs when a reviewer accepts a document
     * from someone sitting on a participant rate.
     */
    public static function studentEquivalentOf(?string $category): ?string
    {
        return match (self::regionOf($category)) {
            self::EAST_AFRICA => 'student_east_africa',
            self::INTERNATIONAL => 'student_non_east_africa',
            default => null,
        };
    }

    /**
     * Assert the selected fee category matches both the participant type and
     * the country. Throws a validation error against `fee_category`, which is
     * where both forms render the message.
     *
     * @throws ValidationException
     */
    public static function guard(string $feeCategory, ?string $participantType, ?string $country): void
    {
        if (($participantType === 'student') !== self::isStudentCategory($feeCategory)) {
            throw ValidationException::withMessages([
                'fee_category' => 'Please select the registration category that matches your participant type.',
            ]);
        }

        $categoryRegion = self::regionOf($feeCategory);

        if ($categoryRegion !== null && $categoryRegion !== self::regionOfCountry($country)) {
            throw ValidationException::withMessages([
                'fee_category' => self::isEastAfricaCountry($country)
                    ? 'Participants from East African Community countries must select an East African registration category.'
                    : 'Please select a Non-East African registration category for your country.',
            ]);
        }
    }
}
