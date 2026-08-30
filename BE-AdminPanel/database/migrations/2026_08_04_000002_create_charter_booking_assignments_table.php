<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('charter_booking_assignments')) {
            Schema::create('charter_booking_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('charter_booking_id')->constrained('charter_bookings')->cascadeOnDelete();
                $table->unsignedInteger('bus_id');
                $table->foreign('bus_id')->references('id')->on('buses')->cascadeOnDelete();
                $table->unsignedInteger('driver_id');
                $table->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['charter_booking_id', 'bus_id']);
                $table->unique(['charter_booking_id', 'driver_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('charter_booking_assignments');
    }
};
