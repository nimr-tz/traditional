<?php

namespace App\Support;

use App\Models\ConferenceSetting;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Whether new abstracts are still being accepted.
 *
 * The cut-off is `submission_deadline`, stored as a conference setting so
 * organizers can move it from the admin panel without a deploy — which they do:
 * the call-for-abstracts date has been extended before and will be again.
 *
 * This window governs *new* submissions only. Revising an existing abstract
 * stays open after the deadline on purpose — reviewers request revisions well
 * after submissions close, and an author who can't answer them would be stuck
 * holding an abstract nobody can accept. See EnsureAbstractSubmissionIsOpen.
 *
 * Parsed leniently rather than assumed to be Y-m-d: conference settings are
 * free-text rows, and existing installs hold human-entered dates like
 * "13 August 2026" alongside canonical ones. An unparseable value means no
 * deadline — a garbled setting must not shut the call for abstracts.
 */
final class SubmissionWindow
{
    /** End of the last day a new abstract may be submitted, if a deadline is set. */
    public static function deadline(): ?CarbonImmutable
    {
        $value = ConferenceSetting::get('submission_deadline');

        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, config('app.timezone'))->endOfDay();
        } catch (Throwable) {
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

    public static function closedMessage(): string
    {
        $deadline = self::deadline();

        return $deadline === null
            ? 'The call for abstracts is now closed.'
            : 'The deadline for abstract submissions passed on '.$deadline->translatedFormat('j F Y').'.';
    }

    /**
     * The shape the abstracts pages use to describe the window.
     *
     * @return array{deadline: string|null, is_open: bool, closed_message: string|null}
     */
    public static function toArray(): array
    {
        $isOpen = self::isOpen();

        return [
            'deadline' => self::deadline()?->toDateString(),
            'is_open' => $isOpen,
            'closed_message' => $isOpen ? null : self::closedMessage(),
        ];
    }
}
