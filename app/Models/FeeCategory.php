<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FeeCategory extends Model
{
    protected $fillable = ['key', 'label', 'amount', 'currency', 'active', 'sort_order', 'is_complimentary'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'active' => 'boolean',
            'is_complimentary' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * The categories anyone signing up on the public site may choose.
     *
     * Complimentary categories are excluded deliberately: media, secretariat and
     * invited guests attend free by role, and a self-service form that offered
     * those options would let anybody claim free entry by picking one.
     * They are granted at the venue desk instead.
     */
    public function scopeSelectableByPublic(Builder $query): Builder
    {
        return $query->active()->where('is_complimentary', false);
    }

    /** Attends by role rather than by fee, so there is nothing to bill. */
    public function isComplimentary(): bool
    {
        return (bool) $this->is_complimentary;
    }
}
