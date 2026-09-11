<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_reserved_trips', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('ticket_number');
            $table->index('reservation_date');
            $table->index('ride_status');
        });

        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->index('status');
            $table->index('customer_id');
        });

        Schema::table('user_payments', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('user_refunds', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('user_charges', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_reserved_trips', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['ticket_number']);
            $table->dropIndex(['reservation_date']);
            $table->dropIndex(['ride_status']);
        });

        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['customer_id']);
        });

        Schema::table('user_payments', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('user_refunds', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('user_charges', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
