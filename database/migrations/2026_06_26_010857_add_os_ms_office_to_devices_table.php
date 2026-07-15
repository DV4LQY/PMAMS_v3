<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('os_version')->nullable()->after('status');
            $table->string('os_license')->nullable()->after('os_version');
            $table->string('ms_office_version')->nullable()->after('os_license');
            $table->string('ms_office_license')->nullable()->after('ms_office_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['os_version', 'os_license', 'ms_office_version', 'ms_office_license']);
        });
    }
};
