<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbstractReviewerComment extends Model
{
    protected $fillable = [
        'abstract_reviewer_decision_id',
        'section',
        'body',
        'addressed_at',
    ];

    protected function casts(): array
    {
        return [
            'addressed_at' => 'datetime',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(AbstractReviewerDecision::class, 'abstract_reviewer_decision_id');
    }

    public function isAddressed(): bool
    {
        return $this->addressed_at !== null;
    }
}
