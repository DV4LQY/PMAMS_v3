<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('devices') || Schema::hasColumn('devices', 'part_of_property_number')) {
            return;
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->string('part_of_property_number', 50)
                ->nullable()
                ->after('property_number');

            $table->index('part_of_property_number', 'devices_part_property_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('devices') || ! Schema::hasColumn('devices', 'part_of_property_number')) {
            return;
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('devices_part_property_index');
            $table->dropColumn('part_of_property_number');
        });
    }
};
