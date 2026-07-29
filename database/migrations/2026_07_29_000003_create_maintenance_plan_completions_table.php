<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A previous interrupted run may have created the table before MySQL
        // rejected an automatically generated constraint name. It is safe to
        // remove that empty partial table because this migration is pending.
        Schema::dropIfExists('maintenance_plan_completions');

        Schema::create('maintenance_plan_completions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_plan_schedule_id');
            $table->foreign('maintenance_plan_schedule_id', 'mpc_schedule_fk')
                ->references('id')
                ->on('maintenance_plan_schedules')
                ->cascadeOnDelete();
            $table->date('actual_date');
            $table->string('person_in_charge');
            $table->string('signature');
            $table->text('remarks')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('maintenance_plan_schedule_id', 'maintenance_plan_completion_schedule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_completions');
    }
};
