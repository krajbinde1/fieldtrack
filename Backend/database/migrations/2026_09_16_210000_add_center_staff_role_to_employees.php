<?php

use App\Enums\CenterStaffRole;
use App\Models\Center;
use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'staff_role')) {
                $table->string('staff_role', 32)->default(CenterStaffRole::Mobilizer->value);
            }
            if (! Schema::hasColumn('employees', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('employees', 'staff_role')) {
            Employee::query()
                ->where(function ($query): void {
                    $query->whereNull('staff_role')->orWhere('staff_role', '');
                })
                ->update(['staff_role' => CenterStaffRole::Mobilizer->value]);
        }

        if (Schema::hasTable('center_manager_assignments') && Schema::hasColumn('employees', 'created_by_user_id')) {
            $assignments = DB::table('center_manager_assignments')
                ->select('center_id', 'user_id')
                ->orderBy('id')
                ->get()
                ->groupBy('center_id');

            foreach ($assignments as $centerId => $rows) {
                $managerId = (int) $rows->first()->user_id;
                if ($managerId < 1 || ! Center::query()->whereKey($centerId)->exists()) {
                    continue;
                }

                Employee::query()
                    ->where('center_id', $centerId)
                    ->whereNull('created_by_user_id')
                    ->update(['created_by_user_id' => $managerId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            if (Schema::hasColumn('employees', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }
            if (Schema::hasColumn('employees', 'staff_role')) {
                $table->dropColumn('staff_role');
            }
        });
    }
};
