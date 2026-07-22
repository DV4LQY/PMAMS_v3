<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_maintenance_photos', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->unsignedBigInteger('device_id')->nullable()->change();
            $table->foreign('device_id')
                ->references('id')
                ->on('devices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('device_maintenance_photos', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->unsignedBigInteger('device_id')->nullable(false)->change();
            $table->foreign('device_id')
                ->references('id')
                ->on('devices')
                ->cascadeOnDelete();
        });
    }
};
