<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gps_tracking_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Driver yang mengirim data
            $table->unsignedInteger('driver_id');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');

            // Bus yang sedang dikendarai
            $table->unsignedInteger('bus_id')->nullable();
            $table->foreign('bus_id')->references('id')->on('buses')->onDelete('set null');

            // Trip yang sedang berlangsung
            $table->unsignedInteger('planned_trip_id')->nullable();
            $table->foreign('planned_trip_id')->references('id')->on('planned_trips')->onDelete('set null');

            // Koordinat GPS
            $table->double('latitude');
            $table->double('longitude');

            // Data tambahan GPS
            $table->double('speed')->nullable()->comment('Kecepatan dalam km/jam');
            $table->double('heading')->nullable()->comment('Arah pergerakan dalam derajat (0-360)');
            $table->double('accuracy')->nullable()->comment('Akurasi GPS dalam meter');

            // Waktu pencatatan
            $table->timestamp('recorded_at');

            $table->timestamps();

            // Indexes untuk performa query
            $table->index('driver_id');
            $table->index('bus_id');
            $table->index('planned_trip_id');
            $table->index('recorded_at');
            $table->index(['driver_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gps_tracking_logs');
    }
};
