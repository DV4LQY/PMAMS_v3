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
        Schema::table('maintenance_plan_overrides', function (Blueprint $table) {
            $table->date('override_month_from')->nullable()->after('override_date');
            $table->date('override_month_to')->nullable()->after('override_month_from');
        });

        DB::table('maintenance_plan_overrides')->select(['id', 'override_date'])->orderBy('id')->each(function ($row) {
            $date = Carbon::parse($row->override_date);
            DB::table('maintenance_plan_overrides')->where('id', $row->id)->update([
                'override_month_from' => $date->copy()->startOfMonth()->toDateString(),
                'override_month_to' => $date->copy()->startOfMonth()->toDateString(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plan_overrides', function (Blueprint $table) {
            $table->dropColumn(['override_month_from', 'override_month_to']);
        });
    }
};
