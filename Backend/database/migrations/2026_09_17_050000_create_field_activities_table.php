<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_type', 64);
            $table->string('activity_name');
            $table->timestamp('activity_at');
            $table->text('remarks')->nullable();
            $table->string('photo_path');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('location')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'activity_at']);
            $table->index(['center_id', 'activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_activities');
    }
};
