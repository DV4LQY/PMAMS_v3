<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_maintenance_records', function (Blueprint $table) {
            if (! Schema::hasColumn('device_maintenance_records', 'checklist_data')) {
                $table->json('checklist_data')->nullable()->after('remarks');
            }

            if (! Schema::hasColumn('device_maintenance_records', 'corrective_action')) {
                $table->text('corrective_action')->nullable()->after('checklist_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('device_maintenance_records', function (Blueprint $table) {
            if (Schema::hasColumn('device_maintenance_records', 'corrective_action')) {
                $table->dropColumn('corrective_action');
            }

            if (Schema::hasColumn('device_maintenance_records', 'checklist_data')) {
                $table->dropColumn('checklist_data');
            }
        });
    }
};
