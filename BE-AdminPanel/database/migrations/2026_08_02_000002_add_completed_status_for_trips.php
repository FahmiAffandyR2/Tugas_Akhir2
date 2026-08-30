<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('statuses')->updateOrInsert(
            ['name' => 'completed'],
            ['name' => 'completed', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        $status = DB::table('statuses')->where('name', 'completed')->first();

        if ($status && !DB::table('trips')->where('status_id', $status->id)->exists()) {
            DB::table('statuses')->where('id', $status->id)->delete();
        }
    }
};
