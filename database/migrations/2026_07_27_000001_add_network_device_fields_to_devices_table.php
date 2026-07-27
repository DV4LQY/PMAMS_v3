<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('network_device_type', 50)->nullable()->after('model');
            $table->string('location_deployed', 255)->nullable()->after('network_device_type');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['network_device_type', 'location_deployed']);
        });
    }
};
