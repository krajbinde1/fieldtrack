<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('center_coordinator_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['center_id', 'user_id']);
        });

        $duplicateCenters = DB::table('center_manager_assignments')
            ->select('center_id')
            ->groupBy('center_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (! $duplicateCenters && ! $this->centerIdIsUnique()) {
            Schema::table('center_manager_assignments', function (Blueprint $table) {
                $table->unique('center_id', 'center_manager_assignments_one_manager');
            });
        }
    }

    public function down(): void
    {
        if ($this->centerIdIsUnique()) {
            Schema::table('center_manager_assignments', function (Blueprint $table) {
                $table->dropUnique('center_manager_assignments_one_manager');
            });
        }

        Schema::dropIfExists('center_coordinator_assignments');
    }

    private function centerIdIsUnique(): bool
    {
        $indexes = Schema::getIndexes('center_manager_assignments');

        foreach ($indexes as $index) {
            if (($index['name'] ?? '') === 'center_manager_assignments_one_manager') {
                return true;
            }
        }

        return false;
    }
};
