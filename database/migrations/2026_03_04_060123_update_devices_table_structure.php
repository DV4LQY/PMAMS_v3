<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('devices', function (Blueprint $table) {
        // Remove unwanted columns
        $table->dropColumn([
            'model',
            'serial_number',
        ]);

        // If CPU, RAM, storage are stored inside JSON specs,
        // we don't need to drop columns (they are not DB columns).

        // Add new columns
        $table->string('mac_address')->nullable()->after('brand');
        $table->date('date_acquired')->nullable()->after('unit_price');
    });
}

public function down(): void
{
    Schema::table('devices', function (Blueprint $table) {
        $table->string('model')->nullable();
        $table->string('serial_number')->nullable();

        $table->dropColumn(['mac_address', 'date_acquired']);
    });
}
};
