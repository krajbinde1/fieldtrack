<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('travel_km_rate', 8, 2)->nullable()->after('travel_allowance');
            $table->decimal('monthly_km_limit', 10, 2)->nullable()->after('travel_km_rate');
        });

        Schema::table('expense_items', function (Blueprint $table) {
            $table->string('travel_mode')->nullable()->after('type');
            $table->decimal('recorded_km', 10, 2)->nullable()->after('travel_km');
            $table->decimal('eligible_km', 10, 2)->nullable()->after('recorded_km');
            $table->decimal('km_rate', 8, 2)->nullable()->after('eligible_km');
            $table->decimal('monthly_km_used', 10, 2)->nullable()->after('km_rate');
            $table->decimal('monthly_km_limit', 10, 2)->nullable()->after('monthly_km_used');
            $table->decimal('travel_from_latitude', 10, 7)->nullable()->after('travel_to');
            $table->decimal('travel_from_longitude', 10, 7)->nullable()->after('travel_from_latitude');
            $table->decimal('travel_to_latitude', 10, 7)->nullable()->after('travel_from_longitude');
            $table->decimal('travel_to_longitude', 10, 7)->nullable()->after('travel_to_latitude');
            $table->string('travel_from_source')->nullable()->after('travel_to_longitude');
            $table->string('travel_to_source')->nullable()->after('travel_from_source');
            $table->string('gps_from_address')->nullable()->after('travel_to_source');
            $table->string('gps_to_address')->nullable()->after('gps_from_address');
        });

        Schema::create('expense_self_travel_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('travel_date');
            $table->foreignId('expense_claim_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'travel_date']);
        });

        Schema::create('expense_receipt_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_receipt_uploads');
        Schema::dropIfExists('expense_self_travel_days');

        Schema::table('expense_items', function (Blueprint $table) {
            $table->dropColumn([
                'travel_mode',
                'recorded_km',
                'eligible_km',
                'km_rate',
                'monthly_km_used',
                'monthly_km_limit',
                'travel_from_latitude',
                'travel_from_longitude',
                'travel_to_latitude',
                'travel_to_longitude',
                'travel_from_source',
                'travel_to_source',
                'gps_from_address',
                'gps_to_address',
            ]);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['travel_km_rate', 'monthly_km_limit']);
        });
    }
};
