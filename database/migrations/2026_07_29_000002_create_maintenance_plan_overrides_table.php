<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plan_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_plan_schedule_id')
                ->constrained('maintenance_plan_schedules')
                ->cascadeOnDelete();
            $table->date('override_date');
            $table->text('reason');
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['maintenance_plan_schedule_id', 'override_date'], 'maintenance_plan_override_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_overrides');
    }
};
