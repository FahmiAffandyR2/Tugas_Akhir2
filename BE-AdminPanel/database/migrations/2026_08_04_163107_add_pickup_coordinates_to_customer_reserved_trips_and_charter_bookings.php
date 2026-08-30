<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_reserved_trips', function (Blueprint $table) {
            $table->double('pickup_lat', 10, 7)->nullable()->after('end_point_lng');
            $table->double('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
        });

        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->double('pickup_lat', 10, 7)->nullable()->after('destination');
            $table->double('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
            $table->double('dropoff_lat', 10, 7)->nullable()->after('pickup_lng');
            $table->double('dropoff_lng', 10, 7)->nullable()->after('dropoff_lat');
        });
    }

    public function down(): void
    {
        Schema::table('customer_reserved_trips', function (Blueprint $table) {
            $table->dropColumn(['pickup_lat', 'pickup_lng']);
        });

        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->dropColumn(['pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng']);
        });
    }
};
