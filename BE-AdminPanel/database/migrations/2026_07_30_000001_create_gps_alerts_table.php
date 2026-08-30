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
        Schema::create('gps_alerts', function (Blueprint $table) {
            $table->increments('id');

            // Driver yang memicu alert
            $table->unsignedInteger('driver_id');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');

            // Trip terkait
            $table->unsignedInteger('planned_trip_id')->nullable();
            $table->foreign('planned_trip_id')->references('id')->on('planned_trips')->onDelete('set null');

            // Tipe alert: 'gps_offline', 'out_of_route', 'speed_exceeded'
            $table->string('alert_type');

            // Pesan alert
            $table->text('message');

            // Metadata (lat, lng, distance, speed, dll)
            $table->json('metadata')->nullable();

            // Status dismiss
            $table->boolean('dismissed')->default(false);
            $table->timestamp('dismissed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['driver_id', 'dismissed']);
            $table->index('alert_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gps_alerts');
    }
};
