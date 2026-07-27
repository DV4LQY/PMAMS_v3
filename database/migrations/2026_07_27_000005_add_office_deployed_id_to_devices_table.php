<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('office_deployed_id')
                ->nullable()
                ->after('location_deployed_id')
                ->constrained('offices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['office_deployed_id']);
            $table->dropColumn('office_deployed_id');
        });
    }
};
