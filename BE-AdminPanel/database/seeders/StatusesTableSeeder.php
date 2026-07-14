<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (['active', 'pending', 'suspended', 'under_review'] as $name) {
            DB::table('statuses')->updateOrInsert(['name' => $name], ['name' => $name]);
        }
    }
}
