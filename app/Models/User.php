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
     * Menu keys shown in the Super Admin permission editor. A null permissions
     * value keeps the role's existing defaults; saved arrays are explicit.
     */
    public const PERMISSION_MENUS = [
        'dashboard' => 'Dashboard',
        'equipment' => 'Equipment',
        'locations' => 'Locations',
        'reports' => 'Reports',
        'issuance' => 'Issuance',
        'maintenance_gallery' => 'PM Gallery',
        'scanner' => 'QR Scanner',
        'support' => 'Support',
        'activity_logs' => 'Activity Logs',
        'database' => 'Backup & Restore',
        'maintenance_cleanup' => 'Checklist Cleanup',
        'maintenance_plan' => 'PM Plan',
    ];

    /** Resources whose add/edit/delete abilities can be assigned separately. */
    public const PERMISSION_RESOURCES = [
        'equipment' => 'Equipment',
        'locations' => 'Locations',
        'offices' => 'Offices',
        'staff' => 'Staff',
        'issuance' => 'Issuance',
        'maintenance_gallery' => 'PM Gallery',
        'checklist' => 'Maintenance Checklist',
        'maintenance_plan' => 'PM Plan',
    ];

    public const PERMISSION_ACTIONS = [
        'add' => 'Add',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ];

    public const ROLE_PERMISSIONS_KEY = 'role_permissions';

    /** Cached for the lifetime of the request so every authorization check
     * reads one role profile instead of querying system_settings repeatedly. */
    protected static ?array $rolePermissionsCache = null;

    /**
     * Baseline permissions are derived from the selected role. A saved
     * permissions JSON value can fine-tune one account, while accounts with
     * no override always follow this role profile.
     */
    public static function defaultPermissionsForRole(string $role): array
    {
        $allMenus = array_keys(self::PERMISSION_MENUS);
        $allActions = array_fill_keys(array_keys(self::PERMISSION_RESOURCES), array_keys(self::PERMISSION_ACTIONS));

        return match ($role) {
            self::ROLE_SUPER_ADMIN => ['menus' => $allMenus, 'actions' => $allActions],
            self::ROLE_ADMIN, self::ROLE_UNIT_HEAD => [
                'menus' => array_values(array_diff($allMenus, ['database', 'maintenance_cleanup'])),
                'actions' => $allActions,
            ],
            self::ROLE_CUSTODIAN => [
                'menus' => ['dashboard', 'equipment', 'locations', 'reports', 'issuance', 'maintenance_gallery', 'scanner', 'support'],
                'actions' => [
                    'equipment' => ['add', 'edit'],
                    'locations' => [],
                    'offices' => [],
                    'staff' => [],
                    'issuance' => ['add', 'edit'],
                    'maintenance_gallery' => ['add', 'edit'],
                    'checklist' => ['edit'],
                ],
            ],
            default => ['menus' => [], 'actions' => []],
        };
    }

    public static function allRolePermissions(): array
    {
        if (static::$rolePermissionsCache !== null) {
            return static::$rolePermissionsCache;
        }

        $profiles = [];
        $raw = SystemSetting::getValue(self::ROLE_PERMISSIONS_KEY);
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $raw = is_array($raw) ? $raw : [];

        foreach (array_keys(self::ROLES) as $role) {
            $defaults = self::defaultPermissionsForRole($role);
            $candidate = is_array($raw[$role] ?? null) ? $raw[$role] : [];
            $menus = array_key_exists('menus', $candidate)
                ? array_values(array_intersect((array) $candidate['menus'], array_keys(self::PERMISSION_MENUS)))
                : $defaults['menus'];
            $actions = $defaults['actions'];

            if (array_key_exists('actions', $candidate) && is_array($candidate['actions'])) {
                foreach (array_keys(self::PERMISSION_RESOURCES) as $resource) {
                    if (array_key_exists($resource, $candidate['actions'])) {
                        $actions[$resource] = array_values(array_intersect(
                            (array) $candidate['actions'][$resource],
                            array_keys(self::PERMISSION_ACTIONS)
                        ));
                    }
                }
            }

            $profiles[$role] = ['menus' => $menus, 'actions' => $actions];
        }

        return static::$rolePermissionsCache = $profiles;
    }

    public static function permissionsForRole(string $role): array
    {
        return static::allRolePermissions()[$role] ?? static::defaultPermissionsForRole($role);
    }

    public static function forgetRolePermissionsCache(): void
    {
        static::$rolePermissionsCache = null;
    }

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
        'permissions',
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
            'permissions' => 'array',
        ];
    }

    public function canMenu(string $menu): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Existing accounts have no custom permissions yet. Keep their current
        // role-based access until a Super Admin explicitly saves a permission set.
        $menus = self::permissionsForRole((string) $this->role)['menus'];

        return in_array($menu, (array) $menus, true);
    }

    public function canAction(string $resource, string $action): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $actions = self::permissionsForRole((string) $this->role)['actions'][$resource] ?? [];

        return in_array($action, (array) $actions, true);
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
