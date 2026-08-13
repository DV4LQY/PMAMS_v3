<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_attention_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_month');
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('high_count')->default(0);
            $table->unsignedInteger('medium_count')->default(0);
            $table->unsignedInteger('low_count')->default(0);
            $table->unsignedInteger('ai_recommended_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->string('engine_mode', 20)->default('hybrid');
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            // One authoritative, updateable point per calendar month. Older
            // months remain immutable historical points once the month ends.
            $table->unique('snapshot_month');
            $table->index('captured_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_attention_snapshots');
    }
};
