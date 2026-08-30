<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->string('gps_source', 10)->nullable()->after('status')->comment('phone or device');
            $table->decimal('current_lat', 10, 7)->nullable()->after('gps_source');
            $table->decimal('current_lng', 10, 7)->nullable()->after('current_lat');
            $table->decimal('current_speed', 6, 2)->nullable()->after('current_lng')->comment('km/h');
            $table->timestamp('last_gps_at')->nullable()->after('current_speed');
            $table->string('gps_device_id', 50)->nullable()->after('last_gps_at')->comment('physical device identifier');
        });
    }

    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropColumn([
                'gps_source', 'current_lat', 'current_lng',
                'current_speed', 'last_gps_at', 'gps_device_id',
            ]);
        });
    }
};
