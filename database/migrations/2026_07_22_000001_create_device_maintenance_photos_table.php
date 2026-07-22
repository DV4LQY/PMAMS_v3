<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_maintenance_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')
                ->constrained('devices')
                ->cascadeOnDelete();
            $table->foreignId('maintenance_record_id')
                ->nullable()
                ->constrained('device_maintenance_records')
                ->nullOnDelete();
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('photo_path');
            $table->dateTime('captured_at');
            $table->string('caption')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'captured_at']);
            $table->index('maintenance_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_maintenance_photos');
    }
};
