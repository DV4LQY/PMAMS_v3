<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $setting = SystemSetting::query()->where('key', User::ROLE_PERMISSIONS_KEY)->first();

        if (! $setting) {
            return;
        }

        $permissions = json_decode((string) $setting->value, true);
        if (! is_array($permissions)) {
            return;
        }

        $defaults = User::defaultPermissionsForRole(User::ROLE_CUSTODIAN);
        $current = is_array($permissions[User::ROLE_CUSTODIAN] ?? null)
            ? $permissions[User::ROLE_CUSTODIAN]
            : [];

        $menus = array_values(array_unique(array_merge(
            (array) ($current['menus'] ?? []),
            $defaults['menus']
        )));
        $actions = (array) ($current['actions'] ?? []);
        foreach ($defaults['actions'] as $resource => $allowedActions) {
            $actions[$resource] = array_values(array_unique(array_merge(
                (array) ($actions[$resource] ?? []),
                $allowedActions
            )));
        }

        $permissions[User::ROLE_CUSTODIAN] = User::sanitizePermissionsForRole(
            User::ROLE_CUSTODIAN,
            ['menus' => $menus, 'actions' => $actions]
        );

        $setting->update(['value' => json_encode($permissions, JSON_THROW_ON_ERROR)]);
        User::forgetRolePermissionsCache();
    }

    public function down(): void
    {
        // Keep role permissions intact if this migration is rolled back.
    }
};
