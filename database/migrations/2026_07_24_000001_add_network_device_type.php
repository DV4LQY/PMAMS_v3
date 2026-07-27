<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        DB::table('device_types')->updateOrInsert(
            ['slug' => 'network-device'],
            [
                'name' => 'Network Device',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        $type = DB::table('device_types')->where('slug', 'network-device')->first();

        if ($type && ! DB::table('devices')->where('device_type_id', $type->id)->exists()) {
            DB::table('device_types')->where('id', $type->id)->delete();
        }
    }
};
