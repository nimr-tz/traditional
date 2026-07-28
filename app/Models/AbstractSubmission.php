<?php

namespace App\Models;

use App\Support\PresentationWindow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbstractSubmission extends Model
{
    protected $fillable = [
        'user_id', 'subtheme_id', 'title', 'authors',
        'background', 'objective', 'methods', 'results', 'conclusion',
        'presentation_type', 'status', 'reviewer_id', 'decision_notes',
        'revision_requested_at', 'resubmitted_at', 'decided_at',
        'reviewer_one_id', 'reviewer_two_id', 'review_round',
    ];

    public const SECTIONS = ['background', 'objective', 'methods', 'results', 'conclusion'];

    /**
     * Allowed file types and size cap per presentation_type, shared by the
     * upload validation rules and the requirements shown to the presenter.
     */
    public const PRESENTATION_REQUIREMENTS = [
        'oral' => ['extensions' => ['pdf', 'ppt', 'pptx'], 'max_kb' => 51200],
        'poster' => ['extensions' => ['pdf', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'], 'max_kb' => 20480],
    ];

    public function wordCount(): int
    {
        $combined = collect(self::SECTIONS)->map(fn (string $section) => (string) $this->{$section})->implode(' ');

        return str_word_count($combined);
    }

    public function presentationRequirements(): array
    {
        return self::PRESENTATION_REQUIREMENTS[$this->presentation_type];
    }

    /**
     * Only the accepted submission's own presenter may upload, and only while
     * the presentation window is open.
     *
     * Presentations are not reviewed: an upload is final in the sense that
     * nobody approves it, but the presenter may keep replacing it right up to
     * the deadline — whatever is on file when the window closes is what gets
     * presented.
     */
    public function canUploadPresentation(): bool
    {
        return $this->status === 'accepted' && PresentationWindow::isOpen();
    }

    public function hasPresentation(): bool
    {
        return $this->presentation_file !== null;
    }

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'revision_requested_at' => 'datetime',
            'resubmitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subtheme(): BelongsTo
    {
        return $this->belongsTo(Subtheme::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewerOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_one_id');
    }

    public function reviewerTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_two_id');
    }

    public function reviewHistory(): HasMany
    {
        return $this->hasMany(AbstractReviewHistory::class)->oldest();
    }

    /** Every recommendation ever recorded, across all review rounds. */
    public function reviewerDecisions(): HasMany
    {
        return $this->hasMany(AbstractReviewerDecision::class);
    }

    /**
     * Only the recommendations that count right now.
     *
     * Superseded rounds are kept (so a reviewer can re-read their own earlier
     * comments and the audit trail survives), which means every check about
     * whether review is complete must scope to the current round or it will
     * count stale opinions.
     */
    public function currentReviewerDecisions(): HasMany
    {
        return $this->reviewerDecisions()->where('round', $this->review_round ?? 1);
    }

    /** Recommendations from earlier rounds, newest round first. */
    public function supersededReviewerDecisions(): HasMany
    {
        return $this->reviewerDecisions()
            ->where('round', '<', $this->review_round ?? 1)
            ->orderByDesc('round');
    }

    /** Rounds beyond the first mean this abstract has been revised. */
    public function isReReview(): bool
    {
        return ($this->review_round ?? 1) > 1;
    }

    /** How many times this abstract has come back for another look. */
    public function completedReviewRounds(): int
    {
        return max(0, ($this->review_round ?? 1) - 1);
    }

    public function presenter(): ?array
    {
        return collect($this->authors)->firstWhere('is_presenter', true) ?? $this->authors[0] ?? null;
    }

    public function hasReviewersAssigned(): bool
    {
        return $this->reviewer_one_id !== null && $this->reviewer_two_id !== null;
    }

    public function isAssignedReviewer(User $user): bool
    {
        return $user->id === $this->reviewer_one_id || $user->id === $this->reviewer_two_id;
    }

    public function bothReviewersDecided(): bool
    {
        return $this->hasReviewersAssigned()
            && $this->currentReviewerDecisions()
                ->whereIn('reviewer_id', [$this->reviewer_one_id, $this->reviewer_two_id])
                ->count() === 2;
    }

    /**
     * Anonymised label ("Reviewer A"/"Reviewer B") shown to the author, who
     * shouldn't see which named reviewer left which comment.
     */
    public function reviewerLabelFor(int $reviewerId): ?string
    {
        return match ($reviewerId) {
            $this->reviewer_one_id => 'Reviewer A',
            $this->reviewer_two_id => 'Reviewer B',
            default => null,
        };
    }
}
