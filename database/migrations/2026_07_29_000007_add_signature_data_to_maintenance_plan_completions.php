<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plan_completions', function (Blueprint $table) {
            $table->text('signature_data')->nullable()->after('signature');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plan_completions', function (Blueprint $table) {
            $table->dropColumn('signature_data');
        });
    }
};
