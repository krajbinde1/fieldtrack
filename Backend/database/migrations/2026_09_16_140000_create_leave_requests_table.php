<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('center_id')->constrained('centers')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('leave_type', 32);
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedSmallInteger('total_days');
            $table->text('reason');
            $table->string('status', 32)->default('pending')->index();
            $table->text('approval_remark')->nullable();
            $table->text('rejection_remark')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('document_storage_key')->nullable()->unique();
            $table->string('document_disk', 32)->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->string('document_mime_type', 128)->nullable();
            $table->unsignedInteger('document_size')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['project_id', 'center_id']);
            $table->index(['from_date', 'to_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
