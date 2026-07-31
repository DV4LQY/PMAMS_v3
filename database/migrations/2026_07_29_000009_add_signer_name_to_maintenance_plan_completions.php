<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plan_completions', function (Blueprint $table) {
            $table->string('signer_name')->nullable()->after('person_in_charge');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plan_completions', function (Blueprint $table) {
            $table->dropColumn('signer_name');
        });
    }
};
