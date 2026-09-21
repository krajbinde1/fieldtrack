<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->where('login_id', 'admin')->exists()) {
            return;
        }

        DB::table('users')
            ->where('role', 'admin')
            ->where('login_id', 'director')
            ->update(['login_id' => 'admin']);
    }

    public function down(): void
    {
        if (DB::table('users')->where('login_id', 'director')->exists()) {
            return;
        }

        DB::table('users')
            ->where('role', 'admin')
            ->where('login_id', 'admin')
            ->update(['login_id' => 'director']);
    }
};
