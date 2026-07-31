<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Signature pads are optional for completion records. Keep the
        // existing VARCHAR(255) type while allowing an empty signature.
        DB::statement('ALTER TABLE `maintenance_plan_completions` MODIFY `signature` VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE `maintenance_plan_completions` SET `signature` = '' WHERE `signature` IS NULL");
        DB::statement('ALTER TABLE `maintenance_plan_completions` MODIFY `signature` VARCHAR(255) NOT NULL');
    }
};
