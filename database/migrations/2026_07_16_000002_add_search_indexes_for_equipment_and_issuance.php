<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->index(['status', 'condition', 'device_type_id'], 'devices_status_condition_type_index');
            $table->index('serial_number', 'devices_serial_number_index');
            $table->index('computer_name', 'devices_computer_name_index');
        });

        Schema::table('device_assignments', function (Blueprint $table) {
            $table->index(['returned_at', 'issued_at'], 'assignments_returned_issued_index');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->index(['is_active', 'last_name', 'first_name'], 'staff_active_name_index');
            $table->index('email', 'staff_email_index');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropIndex('staff_email_index');
            $table->dropIndex('staff_active_name_index');
        });

        Schema::table('device_assignments', function (Blueprint $table) {
            $table->dropIndex('assignments_returned_issued_index');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('devices_computer_name_index');
            $table->dropIndex('devices_serial_number_index');
            $table->dropIndex('devices_status_condition_type_index');
        });
    }
};
