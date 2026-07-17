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

    public const ROLE_USER = 'user';

    public const ROLE_REVIEWER = 'reviewer';

    public const ROLE_STAFF = 'staff';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLES = [
        self::ROLE_USER,
        self::ROLE_REVIEWER,
        self::ROLE_STAFF,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    /** Roles that can decide on abstract submissions and their presentations. */
    public const ABSTRACT_REVIEWER_ROLES = [self::ROLE_REVIEWER, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];

    /** Roles that can administer registrations, students, and conference settings. */
    public const ADMIN_ROLES = [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];

    /** Roles that can sign in to the check-in app and record attendance. */
    public const CHECKIN_ROLES = [self::ROLE_STAFF, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];

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
            'fee_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'billing_requested_at' => 'datetime',
            'payment_received_amount' => 'decimal:2',
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

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** Full conference administration access (registrations, students, settings). */
    public function isAdmin(): bool
    {
        return in_array($this->role, self::ADMIN_ROLES, true);
    }

    public function canReviewAbstracts(): bool
    {
        return in_array($this->role, self::ABSTRACT_REVIEWER_ROLES, true);
    }

    public function canUseCheckinApp(): bool
    {
        return in_array($this->role, self::CHECKIN_ROLES, true);
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
