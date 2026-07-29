<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('scheduled_date');
            $table->string('title')->default('Preventive Maintenance');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'office_id', 'scheduled_date'], 'maintenance_plan_target_date_index');
            $table->index(['assigned_user_id', 'scheduled_date'], 'maintenance_plan_assignee_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_schedules');
    }
};
