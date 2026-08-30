<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bus_types')) {
            Schema::create('bus_types', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 100)->unique();
                $table->string('slug', 100)->unique();
                $table->unsignedInteger('capacity');
                $table->decimal('price_factor', 8, 2)->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        foreach ($this->defaultBusTypes() as $type) {
            DB::table('bus_types')->updateOrInsert(
                ['slug' => $type['slug']],
                array_merge($type, ['updated_at' => now(), 'created_at' => now()])
            );
        }

        Schema::table('buses', function (Blueprint $table) {
            if (!Schema::hasColumn('buses', 'fleet_number')) {
                $table->string('fleet_number', 30)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('buses', 'bus_type_id')) {
                $table->unsignedInteger('bus_type_id')->nullable()->after('license');
                $table->foreign('bus_type_id')->references('id')->on('bus_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('buses', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('seat_config');
            }
        });

        DB::table('buses')->orderBy('id')->get()->each(function ($bus) {
            $busType = DB::table('bus_types')->where('capacity', $bus->capacity)->first()
                ?: DB::table('bus_types')->get()->sortBy(fn ($type) => abs($type->capacity - $bus->capacity))->first();

            DB::table('buses')->where('id', $bus->id)->update([
                'fleet_number' => $bus->fleet_number ?: (string) $bus->id,
                'bus_type_id' => $busType ? $busType->id : null,
                'updated_at' => now(),
            ]);
        });

        if (!Schema::hasTable('service_areas')) {
            Schema::create('service_areas', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 100)->unique();
                $table->string('area_group', 50)->default('Jabodetabek');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $areas = [
            'Jakarta Pusat', 'Jakarta Barat', 'Jakarta Selatan', 'Jakarta Timur', 'Jakarta Utara',
            'Bogor', 'Depok', 'Tangerang', 'Tangerang Selatan', 'Bekasi',
        ];

        foreach ($areas as $area) {
            DB::table('service_areas')->updateOrInsert(
                ['name' => $area],
                ['area_group' => 'Jabodetabek', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        Schema::table('charter_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('charter_bookings', 'bus_type_id')) {
                $table->unsignedInteger('bus_type_id')->nullable()->after('bus_type');
                $table->foreign('bus_type_id')->references('id')->on('bus_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('charter_bookings', 'trip_type')) {
                $table->string('trip_type', 30)->default('one_way')->after('destination');
            }
            if (!Schema::hasColumn('charter_bookings', 'origin_area_id')) {
                $table->unsignedInteger('origin_area_id')->nullable()->after('origin');
                $table->foreign('origin_area_id')->references('id')->on('service_areas')->nullOnDelete();
            }
            if (!Schema::hasColumn('charter_bookings', 'destination_area_id')) {
                $table->unsignedInteger('destination_area_id')->nullable()->after('destination');
                $table->foreign('destination_area_id')->references('id')->on('service_areas')->nullOnDelete();
            }
            if (!Schema::hasColumn('charter_bookings', 'requested_bus_count')) {
                $table->unsignedInteger('requested_bus_count')->default(1)->after('passenger_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->dropForeign(['bus_type_id']);
            $table->dropForeign(['origin_area_id']);
            $table->dropForeign(['destination_area_id']);
            $table->dropColumn(['bus_type_id', 'trip_type', 'origin_area_id', 'destination_area_id', 'requested_bus_count']);
        });

        Schema::dropIfExists('service_areas');

        Schema::table('buses', function (Blueprint $table) {
            $table->dropForeign(['bus_type_id']);
            $table->dropColumn(['fleet_number', 'bus_type_id', 'is_active']);
        });

        Schema::dropIfExists('bus_types');
    }

    private function defaultBusTypes(): array
    {
        return [
            ['name' => 'Medium Bus 25 Seat', 'slug' => 'medium_25', 'capacity' => 25, 'price_factor' => 1.00, 'is_active' => true],
            ['name' => 'Medium Bus 26 Seat', 'slug' => 'medium_26', 'capacity' => 26, 'price_factor' => 1.05, 'is_active' => true],
            ['name' => 'Big Bus 45 Seat', 'slug' => 'big_45', 'capacity' => 45, 'price_factor' => 1.40, 'is_active' => true],
            ['name' => 'Big Bus 50 Seat', 'slug' => 'big_50', 'capacity' => 50, 'price_factor' => 1.50, 'is_active' => true],
        ];
    }
};
