<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('devices', 'deleted_at')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devices', 'deleted_at')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
