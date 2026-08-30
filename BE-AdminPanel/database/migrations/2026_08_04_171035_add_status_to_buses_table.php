<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->string('status', 20)->default('available')->after('is_active');
        });

        // Set existing buses that are on a planned trip to 'on_trip'
        DB::statement('
            UPDATE buses SET status = "on_trip" WHERE id IN (
                SELECT DISTINCT bus_id FROM planned_trips 
                WHERE bus_id IS NOT NULL 
                AND started_at IS NOT NULL 
                AND ended_at IS NULL
            )
        ');
    }

    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
