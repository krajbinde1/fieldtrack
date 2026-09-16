<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('center_id')->constrained('centers')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('admission_targets')->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('target_count');
            $table->timestamps();

            $table->index(['employee_id', 'target_type', 'period_start', 'period_end']);
            $table->index(['center_id', 'project_id']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_targets');
    }
};
