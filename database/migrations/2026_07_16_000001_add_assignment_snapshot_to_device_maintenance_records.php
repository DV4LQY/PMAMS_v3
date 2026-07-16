<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_maintenance_records', function (Blueprint $table) {
            $table->foreignId('staff_id')
                ->nullable()
                ->after('device_id')
                ->constrained('staff')
                ->nullOnDelete();

            $table->foreignId('office_id')
                ->nullable()
                ->after('staff_id')
                ->constrained('offices')
                ->nullOnDelete();

            $table->foreignId('location_id')
                ->nullable()
                ->after('office_id')
                ->constrained('locations')
                ->nullOnDelete();

            $table->index(['maintenance_date', 'location_id'], 'maintenance_date_location_index');
        });
    }

    public function down(): void
    {
        Schema::table('device_maintenance_records', function (Blueprint $table) {
            $table->dropIndex('maintenance_date_location_index');
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('office_id');
            $table->dropConstrainedForeignId('staff_id');
        });
    }
};
