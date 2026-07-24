<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    | Four roles:
    |   - super_admin: unrestricted system owner with access to every module,
    |     including user and role management.
    |   - admin: full access — manages colleges/offices/staff structure,
    |     devices, reports, and can view the activity log. User-account
    |     management is reserved for the Super Admin.
    |   - custodian: a restricted "basic user" account. Can manage devices
    |     and issue/return them to staff, and browse the college/office/staff
    |     directory (read-only). Cannot: create user accounts, delete any
    |     record, use the bulk-add ("auto-form") feature, or view activity
    |     logs — per the client's specified restrictions.
    |   - unit_head: a single designated signatory. Only one account may
    |     hold this role at a time (enforced in UserController). Their name
    |     is automatically pulled into generated PDF reports as the
    |     certifying signatory — see the PDF report generation code for
    |     where this is used.
    |
    | Label is intentionally centralized here — if the client wants a
    | different display name later, only the ROLES array below changes.
    */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_CUSTODIAN = 'custodian';
    public const ROLE_UNIT_HEAD = 'unit_head';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_CUSTODIAN => 'Custodian',
        self::ROLE_UNIT_HEAD => 'Unit Head',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_UNIT_HEAD], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isCustodian(): bool
    {
        return $this->role === self::ROLE_CUSTODIAN;
    }

    public function isUnitHead(): bool
    {
        return $this->role === self::ROLE_UNIT_HEAD;
    }

    /**
     * The single current Unit Head, if one exists. Used to auto-populate
     * the certifying signatory on generated PDF reports.
     */
    public static function currentUnitHead(): ?self
    {
        return self::where('role', self::ROLE_UNIT_HEAD)->first();
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst((string) $this->role);
    }
}
