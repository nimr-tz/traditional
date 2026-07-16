<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministratorAccessChange extends Model
{
    protected $fillable = [
        'target_user_id',
        'target_name',
        'target_email',
        'changed_by',
        'changed_by_name',
        'changed_by_email',
        'action',
        'role',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
