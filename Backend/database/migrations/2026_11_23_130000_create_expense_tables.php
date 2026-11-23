<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('center_id')->constrained()->restrictOnDelete();
            $table->foreignId('scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('claim_number')->unique();
            $table->string('status')->index();
            $table->string('payment_status')->default('unpaid')->index();
            $table->unsignedTinyInteger('current_level')->default(0);
            $table->unsignedTinyInteger('blocked_level')->nullable();
            $table->foreignId('designated_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('final_approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_claim_id')->constrained()->cascadeOnDelete();
            $table->date('expense_date');
            $table->string('type');
            $table->decimal('amount', 12, 2);
            $table->text('description');
            $table->string('receipt_disk')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->string('receipt_mime')->nullable();
            $table->string('travel_from')->nullable();
            $table->string('travel_to')->nullable();
            $table->decimal('travel_km', 10, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_claim_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('action');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('approver_name');
            $table->string('role');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['expense_claim_id', 'level']);
        });

        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_claim_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_mode');
            $table->string('reference_number')->nullable();
            $table->date('paid_on');
            $table->text('remarks')->nullable();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('paid_by_name');
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('dedupe_key')->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('expense_payments');
        Schema::dropIfExists('expense_approvals');
        Schema::dropIfExists('expense_items');
        Schema::dropIfExists('expense_claims');
    }
};
