<?php

namespace Database\Seeders;

use App\Models\FleetDepot;
use Illuminate\Database\Seeder;

class FleetDepotSeeder extends Seeder
{
    public function run()
    {
        $depots = [
            ['name' => 'Depo Tasikmalaya', 'city' => 'Tasikmalaya', 'latitude' => -7.3505800, 'longitude' => 108.2171640],
            ['name' => 'Depo Jakarta', 'city' => 'Jakarta', 'latitude' => -6.2087630, 'longitude' => 106.8455990],
            ['name' => 'Depo Tangerang', 'city' => 'Tangerang', 'latitude' => -6.1783060, 'longitude' => 106.6318890],
            ['name' => 'Depo Bali', 'city' => 'Denpasar', 'latitude' => -8.6704580, 'longitude' => 115.2126290],
        ];

        foreach ($depots as $depot) {
            FleetDepot::firstOrCreate(['name' => $depot['name']], array_merge($depot, [
                'address' => 'Koordinat pusat sementara—silakan sesuaikan dengan alamat depo sebenarnya.',
                'is_active' => true,
            ]));
        }
    }
}
