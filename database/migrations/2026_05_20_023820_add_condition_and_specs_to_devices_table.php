<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (! Schema::hasColumn('devices', 'condition')) {
                $table->string('condition')->default('serviceable')->after('status');
            }

            if (! Schema::hasColumn('devices', 'specs')) {
                $table->json('specs')->nullable()->after('condition');
            }
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'condition')) {
                $table->dropColumn('condition');
            }

            if (Schema::hasColumn('devices', 'specs')) {
                $table->dropColumn('specs');
            }
        });
    }
};