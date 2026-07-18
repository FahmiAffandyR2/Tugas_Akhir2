<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ExpandOverviewPathOnRouteStopDirectionsTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE route_stop_directions MODIFY overview_path LONGTEXT NOT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE route_stop_directions MODIFY overview_path TEXT NOT NULL');
    }
}
