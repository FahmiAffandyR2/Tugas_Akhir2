<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bus_types', function (Blueprint $table) {
            if (!Schema::hasColumn('bus_types', 'base_price')) {
                $table->decimal('base_price', 15, 2)->default(0)->after('price_factor');
            }
            if (!Schema::hasColumn('bus_types', 'price_per_km')) {
                $table->decimal('price_per_km', 15, 2)->default(0)->after('base_price');
            }
            if (!Schema::hasColumn('bus_types', 'minimum_price')) {
                $table->decimal('minimum_price', 15, 2)->default(0)->after('price_per_km');
            }
        });

        foreach ($this->defaultBusTypes() as $type) {
            DB::table('bus_types')->updateOrInsert(
                ['slug' => $type['slug']],
                array_merge($type, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        Schema::table('charter_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('charter_bookings', 'distance_km')) {
                $table->decimal('distance_km', 10, 2)->nullable()->after('requested_bus_count');
            }
            if (!Schema::hasColumn('charter_bookings', 'unit_price')) {
                $table->decimal('unit_price', 15, 2)->nullable()->after('distance_km');
            }
            if (!Schema::hasColumn('charter_bookings', 'price_breakdown')) {
                $table->json('price_breakdown')->nullable()->after('unit_price');
            }
        });

        Schema::table('service_areas', function (Blueprint $table) {
            if (!Schema::hasColumn('service_areas', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('area_group');
            }
            if (!Schema::hasColumn('service_areas', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });

        foreach ($this->defaultAreaCoordinates() as $name => $coordinates) {
            DB::table('service_areas')->where('name', $name)->update(array_merge($coordinates, [
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::table('service_areas', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->dropColumn(['distance_km', 'unit_price', 'price_breakdown']);
        });

        Schema::table('bus_types', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'price_per_km', 'minimum_price']);
        });
    }

    private function defaultBusTypes(): array
    {
        return [
            ['name' => 'Elf', 'slug' => 'elf', 'capacity' => 19, 'price_factor' => 0.75, 'base_price' => 1500000, 'price_per_km' => 8000, 'minimum_price' => 1500000, 'is_active' => true],
            ['name' => 'Medium Bus', 'slug' => 'medium', 'capacity' => 33, 'price_factor' => 1.00, 'base_price' => 2500000, 'price_per_km' => 12000, 'minimum_price' => 2500000, 'is_active' => true],
            ['name' => 'Big Bus', 'slug' => 'big', 'capacity' => 50, 'price_factor' => 1.45, 'base_price' => 3500000, 'price_per_km' => 16000, 'minimum_price' => 3500000, 'is_active' => true],
            ['name' => 'Luxury Bus', 'slug' => 'luxury', 'capacity' => 40, 'price_factor' => 2.10, 'base_price' => 6000000, 'price_per_km' => 25000, 'minimum_price' => 6000000, 'is_active' => true],
            ['name' => 'Medium Bus 25 Seat', 'slug' => 'medium_25', 'capacity' => 25, 'price_factor' => 1.00, 'base_price' => 2500000, 'price_per_km' => 12000, 'minimum_price' => 2500000, 'is_active' => true],
            ['name' => 'Medium Bus 26 Seat', 'slug' => 'medium_26', 'capacity' => 26, 'price_factor' => 1.05, 'base_price' => 2500000, 'price_per_km' => 12000, 'minimum_price' => 2500000, 'is_active' => true],
            ['name' => 'Big Bus 45 Seat', 'slug' => 'big_45', 'capacity' => 45, 'price_factor' => 1.40, 'base_price' => 3500000, 'price_per_km' => 16000, 'minimum_price' => 3500000, 'is_active' => true],
            ['name' => 'Big Bus 50 Seat', 'slug' => 'big_50', 'capacity' => 50, 'price_factor' => 1.50, 'base_price' => 3500000, 'price_per_km' => 16000, 'minimum_price' => 3500000, 'is_active' => true],
        ];
    }

    private function defaultAreaCoordinates(): array
    {
        return [
            'Jakarta Pusat' => ['latitude' => -6.1864860, 'longitude' => 106.8340910],
            'Jakarta Barat' => ['latitude' => -6.1683295, 'longitude' => 106.7588494],
            'Jakarta Selatan' => ['latitude' => -6.2614927, 'longitude' => 106.8105998],
            'Jakarta Timur' => ['latitude' => -6.2250138, 'longitude' => 106.9004472],
            'Jakarta Utara' => ['latitude' => -6.1384142, 'longitude' => 106.8639566],
            'Bogor' => ['latitude' => -6.5971469, 'longitude' => 106.8060388],
            'Depok' => ['latitude' => -6.4024844, 'longitude' => 106.7942405],
            'Tangerang' => ['latitude' => -6.1783060, 'longitude' => 106.6318890],
            'Tangerang Selatan' => ['latitude' => -6.2888889, 'longitude' => 106.7180556],
            'Bekasi' => ['latitude' => -6.2382699, 'longitude' => 106.9755726],
        ];
    }
};
