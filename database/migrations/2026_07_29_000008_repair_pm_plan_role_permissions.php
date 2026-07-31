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

        $changed = false;
        foreach ([User::ROLE_ADMIN, User::ROLE_UNIT_HEAD] as $role) {
            if (! is_array($permissions[$role] ?? null) || ! array_key_exists('menus', $permissions[$role])) {
                continue;
            }

            // This repairs profiles created before PM Plan was introduced.
            // Super Admin can still remove the menu later in the role editor.
            if (! in_array('maintenance_plan', (array) $permissions[$role]['menus'], true)) {
                $permissions[$role]['menus'][] = 'maintenance_plan';
                $changed = true;
            }
        }

        if ($changed) {
            $setting->update(['value' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        // Do not remove a permission that may have been intentionally changed
        // after this repair ran.
    }
};
