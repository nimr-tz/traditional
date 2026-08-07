<?php

namespace App\Support;

use App\Models\AbstractSubmission;
use App\Models\User;

/**
 * Keeps abstract review blind: an assigned reviewer must never learn who wrote
 * the abstract they are judging.
 *
 * Author identity reaches a reviewer through more routes than is obvious — the
 * submitting-author panel, the co-author list, the review-history timeline
 * (the author is the actor on "submitted" and "resubmitted"), the browse
 * listing, and the uploaded file's original name. All of them are redacted
 * here, in one place, so a new surface can't quietly reintroduce the leak.
 *
 * Admins are deliberately exempt: they make the final decision and need author
 * identity to spot conflicts of interest between author and reviewer.
 *
 * This is single-blind by design. The reverse direction — hiding reviewer
 * identity from the author — is already handled by
 * AbstractSubmission::reviewerLabelFor(), which shows authors "Reviewer A"/"B".
 */
final class BlindReview
{
    /** Whether this viewer must be shown a redacted version of the abstract. */
    public static function appliesTo(User $viewer): bool
    {
        return ! $viewer->isAdmin();
    }

    /**
     * Strip every author-identifying field from a submission payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function redactSubmission(array $payload, AbstractSubmission $abstract): array
    {
        // `authors` is a plain JSON column, so it rides along on every
        // serialised submission — including the browse listing, where it is
        // easy to forget the author is present at all.
        unset($payload['user'], $payload['user_id']);

        // Only rewrite keys the payload actually carries: the listing has no
        // review history, and inventing an empty one would be a lie.
        if (array_key_exists('authors', $payload)) {
            $payload['authors'] = self::redactAuthors($payload['authors'] ?? []);
        }

        if (array_key_exists('review_history', $payload)) {
            $payload['review_history'] = self::redactHistory($payload['review_history'] ?? [], $abstract->user_id);
        }

        // Uploaded files are routinely named after their author
        // ("J_Mwakalinga_poster.pdf"), so the stored original name is withheld
        // and the download itself is left to admins.
        if (array_key_exists('presentation_original_name', $payload)) {
            $payload['presentation_original_name'] = $payload['presentation_original_name'] ? 'Presentation file' : null;
        }

        $payload['is_blinded'] = true;

        return $payload;
    }

    /**
     * Author count and presenter position are kept — they tell a reviewer
     * something real about the work — but names and affiliations are not.
     *
     * @param  array<int, array<string, mixed>>  $authors
     * @return array<int, array<string, mixed>>
     */
    public static function redactAuthors(array $authors): array
    {
        return array_values(array_map(fn (array $author, int $index) => [
            'name' => 'Author '.($index + 1),
            'institution' => null,
            'is_presenter' => (bool) ($author['is_presenter'] ?? false),
        ], $authors, array_keys($authors)));
    }

    /**
     * The timeline stays intact — a reviewer should see that a revision was
     * requested and answered — but each actor becomes their role rather than
     * their name, since the author is the actor on submission and resubmission.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array<int, array<string, mixed>>
     */
    public static function redactHistory(array $history, ?int $authorId): array
    {
        return array_map(function (array $entry) use ($authorId) {
            $actorId = $entry['acted_by'] ?? null;

            $entry['actor'] = $actorId === null
                ? null
                : ['name' => $actorId === $authorId ? 'Author' : 'Organizing committee'];

            unset($entry['acted_by']);

            return $entry;
        }, $history);
    }
}
