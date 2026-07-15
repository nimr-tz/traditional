<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'salutation',
        'email',
        'institution',
        'institution_id',
        'phone',
        'country',
        'is_east_africa',
        'participant_type',
        'student_document_path',
        'student_verification_status',
        'student_verified_at',
        'student_verified_by',
        'student_verification_notes',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_east_africa' => 'boolean',
            'is_admin' => 'boolean',
            'fee_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'student_verified_at' => 'datetime',
        ];
    }

    /**
     * The normalized institution record, when the user picked one from the
     * list rather than typing a custom name via "Other". Named to avoid
     * colliding with the `institution` string column (the free-text /
     * denormalized display name used throughout badges, exports, emails).
     */
    public function institutionRecord(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    public function abstractSubmissions(): HasMany
    {
        return $this->hasMany(AbstractSubmission::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Assign the exact category selected at registration. The active
     * fee_categories row is the source for amount, currency, and the billing
     * mapping key stored on the user.
     */
    public function assignFeeCategory(string $key): void
    {
        $category = FeeCategory::where('key', $key)->where('active', true)->firstOrFail();

        $this->fee_category = $category->key;
        $this->fee_amount = $category->amount;
        $this->currency = $category->currency;
    }

    public function requiresStudentVerification(): bool
    {
        return str_starts_with($this->fee_category ?? '', 'student_');
    }

    public function hasVerifiedStudentStatus(): bool
    {
        return ! $this->requiresStudentVerification()
            || $this->student_verification_status === 'verified';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'verified';
    }

    public function isCheckedIn(): bool
    {
        return $this->attendance()->exists();
    }

    /**
     * Generate the unique code the badge QR encodes, once payment clears.
     */
    public function generateRegistrationCode(): string
    {
        do {
            $code = 'TMSC-'.strtoupper(Str::random(10));
        } while (static::where('registration_code', $code)->exists());

        $this->registration_code = $code;

        return $code;
    }
}
