<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_depots', function (Blueprint $table) {
            if (!Schema::hasColumn('fleet_depots', 'geofence_radius')) {
                $table->integer('geofence_radius')->default(500)->after('longitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fleet_depots', function (Blueprint $table) {
            $table->dropColumn('geofence_radius');
        });
    }
};
