<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('project_head_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['project_id', 'code']);
        });

        Schema::create('center_manager_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['center_id', 'user_id']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->foreignId('center_id')->constrained()->restrictOnDelete();
            $table->string('full_name');
            $table->string('mobile', 10)->unique();
            $table->string('email')->nullable();
            $table->string('department')->default('Field');
            $table->string('designation')->default('Employee');
            $table->foreignId('reporting_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('joining_date')->nullable();
            $table->decimal('salary', 12, 2)->nullable();
            $table->string('base_location')->nullable();
            $table->decimal('daily_allowance', 12, 2)->nullable();
            $table->decimal('travel_allowance', 12, 2)->nullable();
            $table->string('aadhaar_number', 12)->nullable();
            $table->string('pan_number', 10)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number', 30)->nullable();
            $table->string('ifsc_code', 11)->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->boolean('status')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['center_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->time('punch_in_time')->nullable();
            $table->time('punch_out_time')->nullable();
            $table->string('attendance_status', 32)->default('Punched In');
            $table->string('working_hours', 10)->nullable();
            $table->string('punch_in_location')->nullable();
            $table->string('punch_out_location')->nullable();
            $table->decimal('punch_in_latitude', 10, 7)->nullable();
            $table->decimal('punch_in_longitude', 10, 7)->nullable();
            $table->decimal('punch_out_latitude', 10, 7)->nullable();
            $table->decimal('punch_out_longitude', 10, 7)->nullable();
            $table->string('punch_in_photo')->nullable();
            $table->string('punch_out_photo')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('approval_status', 32)->default('Pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedInteger('total_working_minutes')->nullable();
            $table->decimal('total_route_distance_km', 10, 2)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['attendance_status', 'approval_status']);
        });

        Schema::create('employee_route_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('local_uuid')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->decimal('speed', 10, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->dateTime('recorded_at');
            $table->string('source')->nullable();
            $table->timestamps();
            $table->index('attendance_id');
            $table->index('employee_id');
            $table->index('recorded_at');
        });

        Schema::create('revoked_mobile_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64);
            $table->string('device_id', 64)->nullable();
            $table->timestamp('revoked_at');
            $table->timestamps();
            $table->unique('token_hash');
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token');
            $table->string('platform')->nullable();
            $table->string('device_name')->nullable();
            $table->string('installation_id', 64)->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('revoked_mobile_tokens');
        Schema::dropIfExists('employee_route_points');
        Schema::dropIfExists('attendances');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });
        Schema::dropIfExists('employees');
        Schema::dropIfExists('center_manager_assignments');
        Schema::dropIfExists('centers');
        Schema::dropIfExists('project_head_assignments');
        Schema::dropIfExists('projects');
    }
};
