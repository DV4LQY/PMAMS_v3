<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('offices') && ! Schema::hasColumn('offices', 'deleted_at')) {
            Schema::table('offices', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('staff') && ! Schema::hasColumn('staff', 'deleted_at')) {
            Schema::table('staff', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff') && Schema::hasColumn('staff', 'deleted_at')) {
            Schema::table('staff', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('offices') && Schema::hasColumn('offices', 'deleted_at')) {
            Schema::table('offices', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
