<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSeatConfigToBusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $defaultSeatConfig = json_encode([
            'rows' => 5,
            'columns' => 4,
            'seatGrid' => [
                [true, true, true, true],
                [true, true, true, true],
                [true, true, true, true],
                [true, true, true, true],
                [true, true, true, true],
            ]
        ]);

        Schema::table('buses', function (Blueprint $table) {
            $table->json('seat_config')->nullable();
        });

        DB::table('buses')->whereNull('seat_config')->update([
            'seat_config' => $defaultSeatConfig,
        ]);
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropColumn('seat_config');
        });
    }
}
