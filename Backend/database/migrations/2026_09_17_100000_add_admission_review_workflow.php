<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admissions')) {
            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            if (! Schema::hasColumn('admissions', 'review_reason')) {
                $table->text('review_reason')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('admissions', 'reviewed_by_user_id')) {
                $table->foreignId('reviewed_by_user_id')->nullable()->after('review_reason')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('admissions', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            }
            if (! Schema::hasColumn('admissions', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->index()->after('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admissions')) {
            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            if (Schema::hasColumn('admissions', 'reviewed_by_user_id')) {
                $table->dropConstrainedForeignId('reviewed_by_user_id');
            }
            foreach (['review_reason', 'reviewed_at', 'confirmed_at'] as $column) {
                if (Schema::hasColumn('admissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
