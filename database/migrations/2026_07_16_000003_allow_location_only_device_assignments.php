<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('device_assignments', 'location_id')) {
                $table->foreignId('location_id')
                    ->nullable()
                    ->after('staff_id')
                    ->constrained('locations')
                    ->nullOnDelete();

                $table->index(['location_id', 'returned_at'], 'assignments_location_returned_index');
            }
        });

        if (Schema::hasColumn('device_assignments', 'staff_id')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE device_assignments MODIFY staff_id BIGINT UNSIGNED NULL');
            } else {
                Schema::table('device_assignments', function (Blueprint $table) {
                    $table->foreignId('staff_id')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        DB::table('device_assignments')->whereNull('staff_id')->delete();

        Schema::table('device_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('device_assignments', 'location_id')) {
                $table->dropIndex('assignments_location_returned_index');
                $table->dropConstrainedForeignId('location_id');
            }
        });

        if (Schema::hasColumn('device_assignments', 'staff_id')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE device_assignments MODIFY staff_id BIGINT UNSIGNED NOT NULL');
            } else {
                Schema::table('device_assignments', function (Blueprint $table) {
                    $table->foreignId('staff_id')->nullable(false)->change();
                });
            }
        }
    }
};
