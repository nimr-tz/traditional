<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subtheme extends Model
{
    protected $fillable = ['title', 'description', 'active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function abstractSubmissions(): HasMany
    {
        return $this->hasMany(AbstractSubmission::class);
    }
}
