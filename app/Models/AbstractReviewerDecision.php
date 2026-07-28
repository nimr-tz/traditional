<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbstractReviewerDecision extends Model
{
    protected $fillable = [
        'abstract_submission_id',
        'reviewer_id',
        'round',
        'recommendation',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function abstractSubmission(): BelongsTo
    {
        return $this->belongsTo(AbstractSubmission::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AbstractReviewerComment::class)->oldest();
    }
}
