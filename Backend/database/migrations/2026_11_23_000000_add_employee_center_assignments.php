<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_center_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'center_id']);
        });

        DB::table('employees')
            ->whereNotNull('center_id')
            ->orderBy('id')
            ->select(['id', 'center_id'])
            ->chunkById(200, function ($rows): void {
                $now = now();
                $assignments = [];
                foreach ($rows as $row) {
                    $assignments[] = [
                        'employee_id' => $row->id,
                        'center_id' => $row->center_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($assignments !== []) {
                    DB::table('employee_center_assignments')->insertOrIgnore($assignments);
                }
            });

        $this->protectLoginIds();
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_center_assignments');

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_login_id_lower_unique');
            });
        }
    }

    /**
     * The users.login_id unique index already blocks exact duplicates.
     * A case-insensitive index is added only when no conflicting rows exist.
     * Existing duplicate accounts are left in place for manual review.
     */
    private function protectLoginIds(): void
    {
        $conflicts = DB::table('users')
            ->whereNotNull('login_id')
            ->selectRaw('lower(login_id) as login_key, count(*) as aggregate')
            ->groupByRaw('lower(login_id)')
            ->havingRaw('count(*) > 1')
            ->exists();

        if ($conflicts || Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        $names = array_column(Schema::getIndexes('users'), 'name');
        if (in_array('users_login_id_lower_unique', $names, true)) {
            return;
        }

        DB::statement('create unique index users_login_id_lower_unique on users (lower(login_id))');
    }
};
