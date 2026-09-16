<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_id')->nullable()->constrained('schemes')->nullOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('center_id')->constrained('centers')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('gender', 32)->nullable();
            $table->string('religion', 64)->nullable();
            $table->string('caste', 64)->nullable();

            $table->string('state', 64)->default('Maharashtra');
            $table->foreignId('district_id')->nullable()->constrained('maharashtra_districts')->nullOnDelete();
            $table->foreignId('taluka_id')->nullable()->constrained('maharashtra_talukas')->nullOnDelete();
            $table->string('village')->nullable();

            $table->string('status', 32)->default('draft')->index();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['project_id', 'center_id']);
            $table->index(['scheme_id', 'status']);
            $table->index(['district_id', 'taluka_id']);
            $table->index('full_name');
        });

        Schema::create('admission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->string('document_type', 64);
            $table->uuid('storage_key');
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();

            $table->unique(['admission_id', 'document_type']);
            $table->unique('storage_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_documents');
        Schema::dropIfExists('admissions');
    }
};
