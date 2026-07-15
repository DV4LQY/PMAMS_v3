<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['office_id', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};