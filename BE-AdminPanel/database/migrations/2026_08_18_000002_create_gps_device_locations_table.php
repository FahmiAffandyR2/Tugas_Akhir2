<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_device_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bus_id');
            $table->string('device_id', 50)->nullable()->comment('physical device identifier');
            $table->string('source', 10)->default('phone')->comment('phone or device');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('speed', 6, 2)->nullable()->comment('km/h');
            $table->decimal('heading', 5, 2)->nullable()->comment('degrees 0-360');
            $table->unsignedInteger('accuracy')->nullable()->comment('meters');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index('bus_id');
            $table->index('recorded_at');
            $table->index(['bus_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_device_locations');
    }
};
