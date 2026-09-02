<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure older restored databases have the settings used by the
     * scheduler. Existing values are preserved, so this is safe to run on
     * installations that already have a configured backup schedule.
     */
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $defaults = [
            'database_backup_frequency' => 'monthly',
            'database_backup_day' => '1',
            'database_backup_weekday' => '1',
            'database_backup_time' => '02:00',
            'database_backup_last_slot' => '',
        ];

        foreach ($defaults as $key => $value) {
            if (DB::table('system_settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('system_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep user-configured schedule data during rollback.
    }
};
