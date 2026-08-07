<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The named segments an admin can send a campaign to.
 *
 * Every segment is restricted to accounts holding the plain `user` role —
 * announcements go to conference participants, never to the staff, reviewer,
 * and admin accounts that share the users table. That restriction lives here
 * rather than in the controller so no segment can accidentally omit it.
 */
final class EmailAudience
{
    public const SEGMENTS = [
        'all' => 'All registrants',
        'paid' => 'Paid registrants (including waived)',
        'unpaid' => 'Unpaid registrants',
        'awaiting_payment' => 'Awaiting payment (control number issued)',
        'not_started_payment' => 'Not started payment',
        'abstract_authors' => 'Registrants who submitted an abstract',
        'accepted_authors' => 'Authors of accepted abstracts',
        'no_abstract' => 'Registrants without an abstract',
        'students_pending_verification' => 'Students awaiting verification',
        'checked_in' => 'Checked in at the venue',
        'by_country' => 'Registrants from a country',
        'by_institution' => 'Registrants from an institution',
    ];

    /** Segments that need a `value` (which country / which institution). */
    public const PARAMETERISED = ['by_country', 'by_institution'];

    public static function isValid(string $segment): bool
    {
        return array_key_exists($segment, self::SEGMENTS);
    }

    public static function needsValue(string $segment): bool
    {
        return in_array($segment, self::PARAMETERISED, true);
    }

    /**
     * A human-readable description of the segment, snapshotted onto the
     * campaign so a past send still explains itself after the underlying data
     * has moved on.
     */
    public static function label(string $segment, ?string $value = null): string
    {
        $base = self::SEGMENTS[$segment] ?? $segment;

        return self::needsValue($segment) && $value ? "{$base}: {$value}" : $base;
    }

    /**
     * Recipients for a segment.
     *
     * Unverified accounts are deliberately included: someone who registered but
     * never confirmed their address is often exactly who a "you haven't
     * finished registering" reminder is for. Accounts with no email address
     * can't exist (it's required and unique), so no null guard is needed.
     */
    public static function query(string $segment, ?string $value = null): Builder
    {
        $query = User::query()->withRole(User::ROLE_USER);

        return match ($segment) {
            'all' => $query,
            'paid' => $query->whereIn('payment_status', ['verified', 'waived']),
            'unpaid' => $query->whereNotIn('payment_status', ['verified', 'waived']),
            'awaiting_payment' => $query->where('payment_status', 'submitted'),
            'not_started_payment' => $query->where('payment_status', 'pending'),
            'abstract_authors' => $query->whereHas('abstractSubmissions'),
            'accepted_authors' => $query->whereHas(
                'abstractSubmissions',
                fn (Builder $q) => $q->where('status', 'accepted')
            ),
            'no_abstract' => $query->whereDoesntHave('abstractSubmissions'),
            'students_pending_verification' => $query
                ->whereIn('fee_category', ['student_east_africa', 'student_non_east_africa'])
                ->where('student_verification_status', 'pending'),
            'checked_in' => $query->whereHas('attendance'),
            'by_country' => $query->where('country', $value),
            'by_institution' => $query->where('institution', $value),
        };
    }

    public static function count(string $segment, ?string $value = null): int
    {
        return self::query($segment, $value)->count();
    }

    /**
     * The distinct values available for the parameterised segments, so the
     * compose form can offer real choices instead of free text that silently
     * matches nobody.
     *
     * @return array{countries: list<string>, institutions: list<string>}
     */
    public static function options(): array
    {
        $distinct = fn (string $column) => User::query()
            ->withRole(User::ROLE_USER)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values()
            ->all();

        return [
            'countries' => $distinct('country'),
            'institutions' => $distinct('institution'),
        ];
    }
}
