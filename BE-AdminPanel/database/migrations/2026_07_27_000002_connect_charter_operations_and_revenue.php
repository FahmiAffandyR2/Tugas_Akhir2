<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->unsignedInteger('bus_id')->nullable()->after('bus_type');
            $table->foreign('bus_id')->references('id')->on('buses')->nullOnDelete();
            $table->unsignedInteger('driver_id')->nullable()->after('bus_id');
            $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('depot_id')->nullable()->after('driver_id');
            $table->foreign('depot_id')->references('id')->on('fleet_depots')->nullOnDelete();
            $table->time('departure_time')->nullable()->after('departure_date');
            $table->date('return_date')->nullable()->after('departure_time');
            $table->time('return_time')->nullable()->after('return_date');
            $table->unsignedInteger('operational_planned_trip_id')->nullable()->after('depot_id');
            $table->foreign('operational_planned_trip_id')->references('id')->on('planned_trips')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
        });

        Schema::create('charter_revenue_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('charter_booking_id')->unique();
            $table->foreign('charter_booking_id')->references('id')->on('charter_bookings')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('status', 30)->default('paid');
            $table->unsignedInteger('verified_by')->nullable();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charter_revenue_transactions');
        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->dropForeign(['operational_planned_trip_id']);
            $table->dropForeign(['depot_id']);
            $table->dropForeign(['driver_id']);
            $table->dropForeign(['bus_id']);
            $table->dropColumn(['bus_id', 'driver_id', 'depot_id', 'departure_time', 'return_date', 'return_time', 'operational_planned_trip_id', 'assigned_at']);
        });
    }
};
