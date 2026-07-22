<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('devices', 'status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE devices MODIFY status ENUM('available', 'issued', 'repair', 'retired', 'not_in_use') NOT NULL DEFAULT 'available'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('devices', 'status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE devices SET status = 'available' WHERE status = 'not_in_use'");
            DB::statement("ALTER TABLE devices MODIFY status ENUM('available', 'issued', 'repair', 'retired') NOT NULL DEFAULT 'available'");
        }
    }
};
