<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationEmailChange extends Model
{
    protected $fillable = [
        'user_id',
        'previous_email',
        'new_email',
        'changed_by',
        'changed_by_name',
        'changed_by_email',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
