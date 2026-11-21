<?php

use App\Services\CenterLoginService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_center_login')->default(false);
        });

        Schema::table('centers', function (Blueprint $table) {
            $table->string('login_id')->nullable()->unique();
            $table->foreignId('login_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('primary_center_manager_id')->nullable()->constrained('users')->nullOnDelete();
        });

        app(CenterLoginService::class)->backfill();
    }

    public function down(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('login_user_id');
            $table->dropConstrainedForeignId('primary_center_manager_id');
            $table->dropUnique(['login_id']);
            $table->dropColumn('login_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_center_login');
        });
    }
};
