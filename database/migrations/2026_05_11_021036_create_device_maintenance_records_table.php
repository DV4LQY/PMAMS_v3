<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_maintenance_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_id')
                ->constrained('devices')
                ->cascadeOnDelete();

            $table->date('maintenance_date');
            $table->string('maintenance_type')->default('Checked');
            $table->text('remarks')->nullable();

            $table->foreignId('checked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_maintenance_records');
    }
};