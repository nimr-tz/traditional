<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Builder;
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

    public const ROLE_FINANCE = 'finance';

    public const ROLES = [
        self::ROLE_USER,
        self::ROLE_REVIEWER,
        self::ROLE_STAFF,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
        self::ROLE_FINANCE,
    ];

    /** Roles that can decide on abstract submissions and their presentations. */
    public const ABSTRACT_REVIEWER_ROLES = [self::ROLE_REVIEWER, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];

    /** Roles that can administer registrations, students, and conference settings. */
    public const ADMIN_ROLES = [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];

    /** Roles that can verify/reject/waive registrant payments. */
    public const FINANCE_ROLES = [self::ROLE_FINANCE, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];

    /** Roles that can sign in to the check-in app and record attendance. */
    public const CHECKIN_ROLES = [self::ROLE_STAFF, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];

    /**
     * Registration (and any other bare `new User(...)`) never sets `role`
     * explicitly — it relies on this in-memory default so it's reliably
     * populated the moment the model exists, not just after a DB round-trip
     * (the `booted()` hook below needs it synchronously, right at creation).
     */
    protected $attributes = [
        'role' => self::ROLE_USER,
    ];

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

    /**
     * A user creation event always seeds the matching `user_roles` row for
     * their initial `role`, so the pivot table (the source of truth for
     * authorization) never starts out empty for a freshly created account.
     */
    protected static function booted(): void
    {
        static::created(function (self $user) {
            $user->roleAssignments()->firstOrCreate(['role' => $user->role]);
        });
    }

    /** The full set of roles this user holds — not just their primary `role`. */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->relationLoaded('roleAssignments')
            ? $this->roleAssignments->pluck('role')->all()
            : $this->roleAssignments()->pluck('role')->all();
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    /** @param  string[]  $roles */
    public function hasAnyRole(array $roles): bool
    {
        return (bool) array_intersect($roles, $this->roles());
    }

    /** Users whose assigned roles include any of the given role(s). */
    public function scopeWithRole(Builder $query, string|array $roles): Builder
    {
        return $query->whereHas('roleAssignments', fn (Builder $q) => $q->whereIn('role', (array) $roles));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    /** Full conference administration access (registrations, students, settings). */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(self::ADMIN_ROLES);
    }

    public function canReviewAbstracts(): bool
    {
        return $this->hasAnyRole(self::ABSTRACT_REVIEWER_ROLES);
    }

    public function canUseCheckinApp(): bool
    {
        return $this->hasAnyRole(self::CHECKIN_ROLES);
    }

    public function canManageFinance(): bool
    {
        return $this->hasAnyRole(self::FINANCE_ROLES);
    }

    /**
     * Which of the user's assigned roles they're currently viewing the site
     * as — a cosmetic preference (nav/home dashboard) only. It never grants
     * or restricts access: every route their full role set allows stays
     * reachable no matter which role is "active". Falls back to their
     * primary `role` when unset or no longer one of their assigned roles.
     */
    public function activeRole(): string
    {
        if ($this->active_role && $this->hasRole($this->active_role)) {
            return $this->active_role;
        }

        return $this->role;
    }

    public static function homeRouteForRole(string $role): string
    {
        return match ($role) {
            self::ROLE_SUPER_ADMIN => route('admin.settings.edit', absolute: false),
            self::ROLE_ADMIN, self::ROLE_REVIEWER => route('admin.dashboard', absolute: false),
            self::ROLE_FINANCE => route('admin.finance.dashboard', absolute: false),
            default => route('dashboard', absolute: false),
        };
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

    /** The finance user who verified/rejected/waived this registrant's payment. */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'payment_verified_by');
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
        return in_array($this->payment_status, ['verified', 'waived'], true);
    }

    public function hasSandboxBillingRequest(): bool
    {
        return str_starts_with($this->billing_request_id ?? '', 'SANDBOX-');
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
