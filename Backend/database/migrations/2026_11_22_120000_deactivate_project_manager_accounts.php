<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep Project Manager user rows and their assignment history, but remove
     * login sessions. The stored role is left unchanged so it is not remapped
     * onto Admin, Director, Coordinator, Center Manager, or Employee.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        $ids = DB::table('users')->where('role', 'project_head')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $updates = ['is_active' => false];
        foreach ([
            'active_mobile_session_id',
            'active_mobile_device_id',
            'active_mobile_token_id',
            'active_mobile_login_at',
        ] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $updates[$column] = null;
            }
        }

        DB::table('users')->whereIn('id', $ids)->update($updates);

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->whereIn('tokenable_id', $ids)
                ->delete();
        }
    }

    public function down(): void
    {
        // Login access is not restored. User records were not deleted.
    }
};
