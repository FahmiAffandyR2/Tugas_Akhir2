<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charter_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            // The legacy users table uses unsignedInteger rather than bigIncrements.
            $table->unsignedInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('origin');
            $table->string('destination');
            $table->date('departure_date');
            $table->unsignedSmallInteger('passenger_count');
            $table->string('bus_type', 30);
            $table->text('notes')->nullable();
            $table->decimal('quoted_price', 15, 2)->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('status', 40)->default('waiting_quote');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charter_bookings');
    }
};
