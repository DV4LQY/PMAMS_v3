<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plan_schedules', function (Blueprint $table) {
            $table->date('schedule_month_from')->nullable()->after('scheduled_date');
            $table->date('schedule_month_to')->nullable()->after('schedule_month_from');
        });

        DB::table('maintenance_plan_schedules')->select(['id', 'scheduled_date'])->orderBy('id')->each(function ($row) {
            $date = Carbon::parse($row->scheduled_date);
            DB::table('maintenance_plan_schedules')->where('id', $row->id)->update([
                'schedule_month_from' => $date->copy()->startOfMonth()->toDateString(),
                'schedule_month_to' => $date->copy()->startOfMonth()->toDateString(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plan_schedules', function (Blueprint $table) {
            $table->dropColumn(['schedule_month_from', 'schedule_month_to']);
        });
    }
};
