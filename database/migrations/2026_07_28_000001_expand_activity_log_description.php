<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Checklist summaries can contain every section disposition and condition.
     * The original VARCHAR(255) column truncated those audit descriptions.
     */
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs') || ! Schema::hasColumn('activity_logs', 'description')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->text('description')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_logs') || ! Schema::hasColumn('activity_logs', 'description')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->string('description')->change();
        });
    }
};
