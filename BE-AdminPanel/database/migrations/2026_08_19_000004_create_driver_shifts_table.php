<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('driver_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('driver_id');
            $table->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('shift_name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->date('shift_date');
            $table->enum('status', ['scheduled', 'active', 'completed', 'absent'])->default('scheduled');
            $table->unsignedInteger('planned_trip_id')->nullable();
            $table->foreign('planned_trip_id')->references('id')->on('planned_trips')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'shift_date']);
            $table->index(['shift_date', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('driver_shifts');
    }
};
