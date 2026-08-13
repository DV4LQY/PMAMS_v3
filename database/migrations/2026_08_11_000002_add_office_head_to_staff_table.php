<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            // NULL means the staff member is not the designated office
            // representative. A unique composite index allows many NULLs but
            // prevents two designated representatives for the same office.
            $table->boolean('is_office_head')->nullable()->default(null)->after('is_active');
            $table->unique(['office_id', 'is_office_head'], 'staff_one_office_head');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropUnique('staff_one_office_head');
            $table->dropColumn('is_office_head');
        });
    }
};
