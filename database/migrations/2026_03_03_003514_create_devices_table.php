<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_type_id')->constrained()->restrictOnDelete();

            $table->string('property_number')->unique(); // important
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();

            $table->decimal('unit_price', 12, 2)->nullable();

            $table->enum('status', ['available', 'issued', 'repair', 'retired'])->default('available');

            // store type-specific details (cpu, ram, etc.)
            $table->json('specs')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['device_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};