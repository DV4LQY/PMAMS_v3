<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Custodians may manage PM Plans, but marking equipment as checked is an
     * Admin/Unit Head/Super Admin responsibility. Remove the legacy checklist
     * edit permission from the shared Custodian profile while preserving all
     * other role-based menu and action selections.
     */
    public function up(): void
    {
        $setting = SystemSetting::query()
            ->where('key', User::ROLE_PERMISSIONS_KEY)
            ->first();

        if (! $setting) {
            return;
        }

        $permissions = json_decode((string) $setting->value, true);
        if (! is_array($permissions)) {
            return;
        }

        $custodian = is_array($permissions[User::ROLE_CUSTODIAN] ?? null)
            ? $permissions[User::ROLE_CUSTODIAN]
            : User::defaultPermissionsForRole(User::ROLE_CUSTODIAN);

        $custodian['actions']['checklist'] = [];
        $permissions[User::ROLE_CUSTODIAN] = User::sanitizePermissionsForRole(
            User::ROLE_CUSTODIAN,
            $custodian
        );

        $setting->update(['value' => json_encode($permissions, JSON_THROW_ON_ERROR)]);
        User::forgetRolePermissionsCache();
    }

    public function down(): void
    {
        // Do not re-grant checklist marking on rollback; permissions may have
        // been intentionally changed through the Role-based menu editor.
    }
};
