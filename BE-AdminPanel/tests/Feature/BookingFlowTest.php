<?php

namespace Tests\Feature;

use App\Models\CharterBooking;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('Enable pdo_sqlite.');
        config(['database.default' => 'booking_testing', 'database.connections.booking_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('booking_testing');
        Schema::create('service_areas', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('area_group')->nullable(); $table->boolean('is_active')->default(true);
            $table->double('latitude'); $table->double('longitude');
        });
        Schema::create('bus_types', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug'); $table->integer('capacity'); $table->boolean('is_active')->default(true);
            foreach (['base_price', 'price_per_km', 'minimum_price', 'pickup_fee'] as $key) $table->double($key)->default(0);
        });
        Schema::create('buses', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('bus_type_id'); $table->boolean('is_active')->default(true); $table->string('status')->default('available');
        });
        Schema::create('planned_trips', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('bus_id')->nullable(); $table->date('planned_date');
        });
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->integer('role'); });
        Schema::create('charter_bookings', function (Blueprint $table) {
            $table->id();
            foreach (['origin_area_id', 'destination_area_id', 'bus_type_id', 'customer_id', 'operational_planned_trip_id'] as $key) $table->unsignedBigInteger($key)->nullable();
            foreach (['origin', 'destination', 'trip_type', 'bus_type', 'reference_code', 'status', 'notes'] as $key) $table->string($key)->nullable();
            $table->date('departure_date'); $table->date('return_date')->nullable();
            $table->string('departure_time'); $table->string('return_time')->nullable();
            $table->integer('passenger_count'); $table->integer('requested_bus_count');
            foreach (['quoted_price', 'unit_price', 'distance_km', 'pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng'] as $key) $table->double($key)->nullable();
            $table->json('destinations')->nullable(); $table->text('price_breakdown')->nullable(); $table->timestamps();
        });
        DB::table('service_areas')->insert([['id' => 1, 'name' => 'Jakarta', 'latitude' => -6.2, 'longitude' => 106.8], ['id' => 2, 'name' => 'Bandung', 'latitude' => -6.9, 'longitude' => 107.6]]);
        DB::table('bus_types')->insert(['id' => 1, 'name' => 'Large Bus', 'slug' => 'large', 'capacity' => 45, 'base_price' => 2000000, 'price_per_km' => 1000]);
        DB::table('buses')->insert([['bus_type_id' => 1, 'status' => 'available'], ['bus_type_id' => 1, 'status' => 'on_trip']]);
    }

    private function payload(string $style = 'day_trip'): array
    {
        return ['origin_area_id' => 1, 'destination_area_id' => 2, 'origin' => 'Jakarta', 'destination' => 'Bandung',
            'trip_type' => 'round_trip', 'trip_style' => $style, 'departure_date' => now()->addDays(2)->toDateString(), 'departure_time' => '08:00',
            'return_date' => now()->addDays($style === 'day_trip' ? 2 : 3)->toDateString(), 'return_time' => '18:00', 'bus_type_id' => 1, 'requested_bus_count' => 1];
    }

    private function customer(): void
    {
        $user = new User(['role' => 1, 'status_id' => 1]); $user->id = 100;
        Sanctum::actingAs($user);
        $this->withHeader('Authorization', 'Bearer 1|test-token');
    }

    public function test_public_options_report_available_units_without_login(): void
    {
        $this->getJson('/api/booking-options')->assertOk()->assertJsonPath('bus_types.0.available_buses', 1);
        $this->getJson('/api/booking-options?departure_date=invalid')->assertStatus(422);
    }

    public function test_options_only_include_types_with_actual_active_buses(): void
    {
        DB::table('bus_types')->where('id', 1)->update(['name' => 'Big Bus 45 Seat', 'slug' => 'big_45']);
        DB::table('bus_types')->insert([
            ['id' => 2, 'name' => 'Empty type', 'slug' => 'empty', 'capacity' => 25],
            ['id' => 3, 'name' => 'Inactive fleet', 'slug' => 'inactive', 'capacity' => 30],
        ]);
        DB::table('buses')->insert(['bus_type_id' => 3, 'status' => 'available', 'is_active' => false]);
        $this->getJson('/api/booking-options')->assertOk()->assertJsonCount(1, 'bus_types')
            ->assertJsonPath('bus_types.0.id', 1)->assertJsonPath('bus_types.0.name', 'Big Bus 45 Seat')
            ->assertJsonPath('bus_types.0.available_buses', 1);
    }

    public function test_day_trip_can_reserve_one_whole_bus_without_passenger_input(): void
    {
        $this->customer();
        $response = CharterBooking::withoutEvents(fn () => $this->postJson('/api/charter-bookings', $this->payload()));
        $response->assertCreated()->assertJsonPath('booking.requested_bus_count', 1)->assertJsonPath('booking.passenger_count', 45)
            ->assertJsonPath('booking.price_breakdown.booking_mode', 'whole_bus');
        $this->assertEquals(2000000, $response->json('booking.price_breakdown.base_price'));
        $this->assertGreaterThan(0, $response->json('booking.distance_km'));
    }

    public function test_numbered_destinations_are_saved_in_order_and_last_point_is_final(): void
    {
        $this->customer();
        $payload = $this->payload();
        $payload['destinations'] = [
            ['address' => 'Titik pertama', 'lat' => -6.3, 'lng' => 106.9],
            ['address' => 'Titik kedua', 'lat' => -6.4, 'lng' => 107.1],
        ];
        CharterBooking::withoutEvents(fn () => $this->postJson('/api/charter-bookings', $payload))
            ->assertCreated()->assertJsonPath('booking.destination', 'Titik kedua')
            ->assertJsonPath('booking.destinations.0.address', 'Titik pertama')
            ->assertJsonPath('booking.destinations.1.address', 'Titik kedua');
        $this->assertEquals(107.1, CharterBooking::firstOrFail()->dropoff_lng);
    }

    public function test_empty_destination_stops_are_rejected(): void
    {
        $this->customer();
        $payload = $this->payload();
        $payload['destinations'] = [['address' => '', 'lat' => null, 'lng' => null]];
        $this->postJson('/api/charter-bookings', $payload)->assertUnprocessable()->assertJsonValidationErrors('destinations.0.address');
    }

    public function test_overnight_booking_accepts_a_later_return_date(): void
    {
        $this->customer();
        CharterBooking::withoutEvents(fn () => $this->postJson('/api/charter-bookings', $this->payload('overnight')))->assertCreated();
    }

    public function test_rental_duration_matches_dates_and_is_returned_in_booking(): void
    {
        $this->customer();
        $payload = $this->payload('overnight');
        $payload['rental_days'] = 3;
        $this->postJson('/api/charter-bookings', $payload)->assertUnprocessable()->assertJsonValidationErrors('rental_days');
        $payload['rental_days'] = 2;
        CharterBooking::withoutEvents(fn () => $this->postJson('/api/charter-bookings', $payload))
            ->assertCreated()->assertJsonPath('booking.rental_days', 2);
    }

    public function test_booking_accepts_locations_without_service_area_selection(): void
    {
        $this->customer();
        $payload = $this->payload();
        unset($payload['origin_area_id'], $payload['destination_area_id']);
        $payload['pickup_lat'] = -6.2;
        $payload['pickup_lng'] = 106.8;
        $payload['dropoff_lat'] = -6.9;
        $payload['dropoff_lng'] = 107.6;
        CharterBooking::withoutEvents(fn () => $this->postJson('/api/charter-bookings', $payload))
            ->assertCreated()->assertJsonPath('booking.origin', 'Jakarta')->assertJsonPath('booking.destination', 'Bandung');
        $booking = CharterBooking::firstOrFail();
        $this->assertNull($booking->origin_area_id);
        $this->assertNull($booking->destination_area_id);
        $this->assertEquals(-6.9, $booking->dropoff_lat);
    }

    public function test_booking_still_requires_destination_without_service_areas(): void
    {
        $this->customer();
        $payload = $this->payload();
        unset($payload['origin_area_id'], $payload['destination_area_id'], $payload['destination']);
        $this->postJson('/api/charter-bookings', $payload)->assertUnprocessable()->assertJsonValidationErrors('destination');
    }

    public function test_inconsistent_dates_and_unavailable_bus_are_rejected(): void
    {
        $this->customer();
        $payload = $this->payload();
        $payload['trip_style'] = 'overnight';
        $this->postJson('/api/charter-bookings', $payload)->assertUnprocessable()->assertJsonValidationErrors('return_date');
        $payload = $this->payload(); $payload['return_time'] = '07:00';
        $this->postJson('/api/charter-bookings', $payload)->assertUnprocessable()->assertJsonValidationErrors('return_time');
        DB::table('buses')->update(['status' => 'on_trip']);
        $this->postJson('/api/charter-bookings', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('bus_type_id');
        $this->assertSame(0, DB::table('charter_bookings')->count());
    }
}
