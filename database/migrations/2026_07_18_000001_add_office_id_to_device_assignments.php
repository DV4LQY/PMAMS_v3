<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_assignments') || Schema::hasColumn('device_assignments', 'office_id')) {
            return;
        }

        Schema::table('device_assignments', function (Blueprint $table) {
            $table->foreignId('office_id')
                ->nullable()
                ->after('location_id')
                ->constrained('offices')
                ->nullOnDelete();

            $table->index(['office_id', 'returned_at'], 'assignments_office_returned_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('device_assignments') || ! Schema::hasColumn('device_assignments', 'office_id')) {
            return;
        }

        Schema::table('device_assignments', function (Blueprint $table) {
            $table->dropIndex('assignments_office_returned_index');
            $table->dropConstrainedForeignId('office_id');
        });
    }
};
