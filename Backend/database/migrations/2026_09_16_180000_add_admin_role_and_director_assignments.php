<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('director_project_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        foreach (DB::table('users')->where('role', 'director')->get() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'role' => 'admin',
                'name' => $user->name === 'Director' ? 'Admin' : $user->name,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('users')->where('role', 'admin')->get() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'role' => 'director',
                'name' => $user->name === 'Admin' ? 'Director' : $user->name,
                'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('director_project_assignments');
    }
};
