<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Nullable + nullOnDelete so deleting a user account doesn't wipe
            // the audit trail of what that user did.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable(); // snapshot, survives user deletion

            $table->string('action'); // e.g. 'created', 'updated', 'deleted', 'issued', 'returned'
            $table->string('subject_type')->nullable(); // e.g. 'College', 'Device'
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');

            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
