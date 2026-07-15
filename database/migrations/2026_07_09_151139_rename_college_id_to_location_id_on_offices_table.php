<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offices')
            && Schema::hasColumn('offices', 'college_id')
            && ! Schema::hasColumn('offices', 'location_id')) {

            try {
                Schema::table('offices', function (Blueprint $table) {
                    $table->dropForeign(['college_id']);
                });
            } catch (Throwable $e) {
                // Foreign key may not exist. Continue safely.
            }

            Schema::table('offices', function (Blueprint $table) {
                $table->renameColumn('college_id', 'location_id');
            });

            try {
                Schema::table('offices', function (Blueprint $table) {
                    $table->foreign('location_id')
                        ->references('id')
                        ->on('locations')
                        ->cascadeOnDelete();
                });
            } catch (Throwable $e) {
                // Avoid breaking migration if FK already exists or local DB differs.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('offices')
            && Schema::hasColumn('offices', 'location_id')
            && ! Schema::hasColumn('offices', 'college_id')) {

            try {
                Schema::table('offices', function (Blueprint $table) {
                    $table->dropForeign(['location_id']);
                });
            } catch (Throwable $e) {
                // Foreign key may not exist. Continue safely.
            }

            Schema::table('offices', function (Blueprint $table) {
                $table->renameColumn('location_id', 'college_id');
            });

            try {
                Schema::table('offices', function (Blueprint $table) {
                    $table->foreign('college_id')
                        ->references('id')
                        ->on('colleges')
                        ->cascadeOnDelete();
                });
            } catch (Throwable $e) {
                // Avoid breaking rollback if colleges table does not exist.
            }
        }
    }
};
