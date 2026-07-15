<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbstractReviewHistory extends Model
{
    protected $fillable = [
        'abstract_submission_id',
        'acted_by',
        'action',
        'from_status',
        'to_status',
        'notes',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AbstractSubmission::class, 'abstract_submission_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
