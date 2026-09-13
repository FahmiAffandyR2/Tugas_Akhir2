<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Enable pdo_sqlite for isolated staff portal tests.');
        }
        config(['database.default' => 'staff_testing', 'database.connections.staff_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::purge('staff_testing');
        $this->withHeader('Authorization', 'Bearer 1|test-token');
        Schema::create('charter_bookings', function (Blueprint $table) {
            $table->id();
            foreach (['depot_id', 'bus_id', 'driver_id', 'customer_id', 'bus_type_id', 'origin_area_id', 'destination_area_id', 'operational_planned_trip_id'] as $key) {
                $table->unsignedBigInteger($key)->nullable();
            }
            $table->string('status')->default('approved');
            $table->string('payment_status')->default('paid');
            $table->string('reference_code')->default('TEST');
            $table->timestamps();
        });
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('depot_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('available');
            $table->softDeletes();
        });
        Schema::create('charter_booking_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('charter_booking_id');
            $table->unsignedBigInteger('bus_id');
            $table->unsignedBigInteger('driver_id')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('depot_id')->nullable();
            $table->integer('role')->default(2);
            $table->integer('status_id')->default(1);
            foreach (['name', 'email', 'tel_number'] as $key) $table->string($key)->nullable();
        });
        Schema::create('fleet_depots', function (Blueprint $table) { $table->id(); });
        DB::table('buses')->insert([['id' => 1, 'depot_id' => 7], ['id' => 2, 'depot_id' => 8]]);
        DB::table('charter_bookings')->insert([
            ['id' => 1, 'depot_id' => 7, 'bus_id' => null, 'status' => 'waiting_quote'],
            ['id' => 2, 'depot_id' => 8, 'bus_id' => 1, 'status' => 'approved'],
            ['id' => 3, 'depot_id' => 8, 'bus_id' => 2, 'status' => 'approved'],
            ['id' => 4, 'depot_id' => 8, 'bus_id' => 2, 'status' => 'approved'],
        ]);
        DB::table('charter_booking_assignments')->insert(['charter_booking_id' => 3, 'bus_id' => 1]);
        $this->signIn(3, 7);
    }

    private function signIn(int $role, ?int $depot): void
    {
        $user = new User(['role' => $role, 'status_id' => 1]);
        $user->id = 100;
        $user->depot_id = $depot;
        Sanctum::actingAs($user);
    }

    public function test_staff_dashboard_and_list_include_only_related_depot_bookings(): void
    {
        $this->getJson('/api/staff/dashboard')->assertOk()
            ->assertJsonPath('dashboard.total_bookings', 3)
            ->assertJsonPath('dashboard.pending_bookings', 1)
            ->assertJsonPath('dashboard.total_buses', 1);
        $response = $this->getJson('/api/staff/bookings')->assertOk();
        $ids = array_column($response->json('bookings'), 'id');
        sort($ids);
        $this->assertSame([1, 2, 3], $ids);
        $this->getJson('/api/dashboard/all')->assertForbidden();
    }

    public function test_staff_can_download_paid_invoices_for_all_depot_relationships(): void
    {
        foreach ([1, 2, 3] as $id) {
            $this->get('/api/staff/bookings/'.$id.'/invoice')->assertOk()->assertSee('INV-TEST');
        }
        $this->getJson('/api/staff/bookings/4/invoice')->assertForbidden();
    }

    public function test_unpaid_invoice_is_still_unavailable(): void
    {
        DB::table('charter_bookings')->where('id', 1)->update(['payment_status' => 'pending']);
        $this->getJson('/api/staff/bookings/1/invoice')->assertStatus(422);
    }

    public function test_staff_without_depot_and_other_roles_cannot_access_invoices(): void
    {
        $this->signIn(3, null);
        $this->getJson('/api/staff/dashboard')->assertOk()->assertJsonPath('dashboard', []);
        $this->getJson('/api/staff/bookings')->assertOk()->assertJsonPath('bookings', []);
        $this->getJson('/api/staff/bookings/1/invoice')->assertForbidden();
        foreach ([0, 1, 2] as $role) {
            $this->signIn($role, 7);
            $this->getJson('/api/staff/dashboard')->assertForbidden();
            $this->getJson('/api/staff/bookings/1/invoice')->assertForbidden();
        }
    }
}
