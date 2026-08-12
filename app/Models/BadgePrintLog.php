<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgePrintLog extends Model
{
    protected $fillable = [
        'user_id',
        'printed_by',
        'print_number',
        'printed_name',
        'printed_institution',
        'printed_category',
        'registration_code',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
        ];
    }

    /**
     * Record a badge coming off the printer.
     *
     * The print number is derived from what is already on file, so the first
     * badge is 1 and every reissue counts up from there — that number is what
     * the desk sees before it reprints.
     */
    public static function record(User $user, ?User $printedBy, ?string $name = null, ?string $institution = null, ?string $category = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'printed_by' => $printedBy?->id,
            'print_number' => self::where('user_id', $user->id)->count() + 1,
            'printed_name' => $name ?? $user->name,
            'printed_institution' => $institution ?? $user->institution,
            'printed_category' => $category,
            'registration_code' => $user->registration_code,
            'printed_at' => now(),
        ]);
    }

    public function isReprint(): bool
    {
        return $this->print_number > 1;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
