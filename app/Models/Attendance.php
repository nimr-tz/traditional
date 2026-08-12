<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One record per registrant per day, holding the time they were scanned.
 *
 * The per-day grain is the point: attendance used to be a single record for the
 * whole conference, so anyone scanned on the first morning was invisible on
 * every day after. A unique index on (user_id, attendance_date) enforces it in
 * the database rather than trusting each caller to check first.
 */
class Attendance extends Model
{
    protected $fillable = ['user_id', 'attendance_date', 'checked_in_at', 'checked_in_by'];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'checked_in_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Derived from the scan time so no caller has to remember it, and the
        // two can never disagree.
        static::creating(function (Attendance $attendance) {
            $attendance->attendance_date ??= ($attendance->checked_in_at ?? now())->toDateString();
        });
    }

    public function scopeOn(Builder $query, mixed $date): Builder
    {
        return $query->whereDate('attendance_date', $date);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('attendance_date', today());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
