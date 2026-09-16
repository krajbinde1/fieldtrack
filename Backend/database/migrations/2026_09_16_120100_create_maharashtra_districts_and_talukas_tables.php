<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maharashtra_districts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('maharashtra_talukas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('maharashtra_districts')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 64);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['district_id', 'code']);
            $table->index(['district_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maharashtra_talukas');
        Schema::dropIfExists('maharashtra_districts');
    }
};
