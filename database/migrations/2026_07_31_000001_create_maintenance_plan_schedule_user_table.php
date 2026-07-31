<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plan_schedule_user', function (Blueprint $table) {
            $table->unsignedBigInteger('maintenance_plan_schedule_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->primary(['maintenance_plan_schedule_id', 'user_id']);
            $table->index('user_id');
            $table->foreign('maintenance_plan_schedule_id', 'mpsu_schedule_fk')
                ->references('id')
                ->on('maintenance_plan_schedules')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'mpsu_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        // Preserve schedules created before multi-assignment was introduced.
        $legacyAssignments = DB::table('maintenance_plan_schedules')
            ->whereNotNull('assigned_user_id')
            ->get(['id', 'assigned_user_id']);

        foreach ($legacyAssignments as $assignment) {
            DB::table('maintenance_plan_schedule_user')->insert([
                'maintenance_plan_schedule_id' => $assignment->id,
                'user_id' => $assignment->assigned_user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_schedule_user');
    }
};
