<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('login_id', 255)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('must_change_password')->default(false);
            $table->string('role', 32)->default('employee')->index();
            $table->boolean('is_active')->default(true);
            $table->uuid('active_mobile_session_id')->nullable();
            $table->string('active_mobile_device_id', 64)->nullable();
            $table->unsignedBigInteger('active_mobile_token_id')->nullable();
            $table->timestamp('active_mobile_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
