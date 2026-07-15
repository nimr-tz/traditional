<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbstractSubmission extends Model
{
    protected $fillable = [
        'user_id', 'subtheme_id', 'title', 'authors', 'abstract_text',
        'presentation_type', 'status', 'reviewer_id', 'decision_notes',
        'revision_requested_at', 'resubmitted_at', 'decided_at',
    ];

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

    public function reviewHistory(): HasMany
    {
        return $this->hasMany(AbstractReviewHistory::class)->oldest();
    }

    public function presenter(): ?array
    {
        return collect($this->authors)->firstWhere('is_presenter', true) ?? $this->authors[0] ?? null;
    }
}
