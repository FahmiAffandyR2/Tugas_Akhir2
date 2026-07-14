<?php

namespace Database\Seeders;

use App\Models\RedemptionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RedemptionTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (['Cash', 'Bank transfer', 'Paypal', 'Mobile money'] as $name) {
            DB::table('redemption_types')->updateOrInsert(['name' => $name], ['name' => $name]);
        }
    }
}
