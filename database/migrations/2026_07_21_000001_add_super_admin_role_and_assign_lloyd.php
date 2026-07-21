<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The users.role column is intentionally a string so this role can be
        // introduced without changing existing records or database engines.
        DB::table('users')
            ->where(function ($query) {
                $query->where('email', 'ldyusores@catsu.edu.ph')
                    ->orWhere('name', 'Lloyd D. Yusores');
            })
            ->update([
                'role' => 'super_admin',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where(function ($query) {
                $query->where('email', 'ldyusores@catsu.edu.ph')
                    ->orWhere('name', 'Lloyd D. Yusores');
            })
            ->where('role', 'super_admin')
            ->update([
                'role' => 'admin',
                'updated_at' => now(),
            ]);
    }
};
