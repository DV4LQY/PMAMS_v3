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

        $permissions = is_array($setting->value)
            ? $setting->value
            : json_decode((string) $setting->value, true);

        if (! is_array($permissions)) {
            return;
        }

        foreach ([User::ROLE_ADMIN, User::ROLE_UNIT_HEAD] as $role) {
            if (! is_array($permissions[$role] ?? null)) {
                continue;
            }

            $menus = array_values((array) ($permissions[$role]['menus'] ?? []));
            if (! in_array('maintenance_plan', $menus, true)) {
                $menus[] = 'maintenance_plan';
                $permissions[$role]['menus'] = $menus;
            }
        }

        $setting->update(['value' => json_encode($permissions)]);
    }

    public function down(): void
    {
        $setting = SystemSetting::query()->where('key', User::ROLE_PERMISSIONS_KEY)->first();
        if (! $setting) {
            return;
        }

        $permissions = json_decode((string) $setting->value, true);
        if (! is_array($permissions)) {
            return;
        }

        foreach ([User::ROLE_ADMIN, User::ROLE_UNIT_HEAD] as $role) {
            if (is_array($permissions[$role]['menus'] ?? null)) {
                $permissions[$role]['menus'] = array_values(array_diff($permissions[$role]['menus'], ['maintenance_plan']));
            }
        }

        $setting->update(['value' => json_encode($permissions)]);
    }
};
