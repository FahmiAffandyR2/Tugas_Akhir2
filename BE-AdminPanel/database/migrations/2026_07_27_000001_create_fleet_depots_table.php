<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFleetDepotsTable extends Migration
{
    public function up()
    {
        Schema::create('fleet_depots', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('city', 100);
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('buses', function (Blueprint $table) {
            $table->unsignedInteger('depot_id')->nullable()->after('driver_id');
            $table->foreign('depot_id')->references('id')->on('fleet_depots')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropForeign(['depot_id']);
            $table->dropColumn('depot_id');
        });
        Schema::dropIfExists('fleet_depots');
    }
}
