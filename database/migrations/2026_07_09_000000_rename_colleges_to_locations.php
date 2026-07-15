<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('offices') && Schema::hasColumn('offices', 'college_id')) {
            Schema::table('offices', function (Blueprint $table) {
                $table->dropForeign(['college_id']);
                $table->dropUnique(['college_id', 'name']);
            });
        }

        if (Schema::hasTable('colleges') && ! Schema::hasTable('locations')) {
            Schema::rename('colleges', 'locations');
        }

        if (Schema::hasTable('offices') && Schema::hasColumn('offices', 'college_id')) {
            Schema::table('offices', function (Blueprint $table) {
                $table->renameColumn('college_id', 'location_id');
            });
        }

        if (Schema::hasTable('offices') && Schema::hasColumn('offices', 'location_id')) {
            Schema::table('offices', function (Blueprint $table) {
                $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
                $table->unique(['location_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('offices') && Schema::hasColumn('offices', 'location_id')) {
            Schema::table('offices', function (Blueprint $table) {
                $table->dropForeign(['location_id']);
                $table->dropUnique(['location_id', 'name']);
            });
        }

        if (Schema::hasTable('locations') && ! Schema::hasTable('colleges')) {
            Schema::rename('locations', 'colleges');
        }

        if (Schema::hasTable('offices') && Schema::hasColumn('offices', 'location_id')) {
            Schema::table('offices', function (Blueprint $table) {
                $table->renameColumn('location_id', 'college_id');
            });
        }

        if (Schema::hasTable('offices') && Schema::hasColumn('offices', 'college_id')) {
            Schema::table('offices', function (Blueprint $table) {
                $table->foreign('college_id')->references('id')->on('colleges')->cascadeOnDelete();
                $table->unique(['college_id', 'name']);
            });
        }
    }
};
