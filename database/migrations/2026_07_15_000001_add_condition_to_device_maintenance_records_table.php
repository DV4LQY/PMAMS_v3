<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('device_maintenance_records', 'condition')) {
            Schema::table('device_maintenance_records', function (Blueprint $table) {
                $table->string('condition')->nullable()->after('maintenance_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('device_maintenance_records', 'condition')) {
            Schema::table('device_maintenance_records', function (Blueprint $table) {
                $table->dropColumn('condition');
            });
        }
    }
};
