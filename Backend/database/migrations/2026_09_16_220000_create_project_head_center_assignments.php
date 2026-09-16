<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_head_center_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['center_id', 'user_id']);
        });

        $now = now();

        foreach (DB::table('project_head_assignments')->get() as $assignment) {
            $centerIds = DB::table('centers')
                ->where('project_id', $assignment->project_id)
                ->pluck('id');

            foreach ($centerIds as $centerId) {
                DB::table('project_head_center_assignments')->updateOrInsert(
                    [
                        'center_id' => $centerId,
                        'user_id' => $assignment->user_id,
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_head_center_assignments');
    }
};
