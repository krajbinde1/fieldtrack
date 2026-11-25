<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_presence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_gps_at')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->string('last_location_label')->nullable();
            $table->boolean('gps_services_enabled')->nullable();
            $table->boolean('gps_fix_valid')->nullable();
            $table->timestamp('gps_state_at')->nullable();
            $table->string('connectivity_state', 16)->nullable();
            $table->timestamp('connectivity_event_at')->nullable();
            $table->string('alert_kind', 32)->nullable();
            $table->unsignedInteger('alert_episode')->default(0);
            $table->unsignedInteger('alert_sequence')->default(0);
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamp('condition_since')->nullable();
            $table->timestamp('monitoring_stopped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_presence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('local_uuid')->unique();
            $table->string('event_type', 32);
            $table->timestamp('recorded_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_label')->nullable();
            $table->timestamps();
            $table->index(['attendance_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_presence_events');
        Schema::dropIfExists('attendance_presence');
    }
};
