<?php

namespace Tests\Feature;

use App\Models\PlannedTrip;
use App\Models\Reservation;
use App\Models\Route as BusRoute;
use App\Models\User;
use App\Repository\CouponCustomerRepositoryInterface;
use App\Repository\ReservationRepositoryInterface;
use App\Repository\UserRefundRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ReservationSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'reservation_testing', 'database.connections.reservation_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $this->withHeader('Authorization', 'Bearer 1|test-token');
    }

    public function test_reservation_details_are_limited_to_admin_owner_and_assigned_driver(): void
    {
        $route = (new BusRoute())->setRelation('routeStops', new Collection());
        $trip = (new PlannedTrip(['driver_id' => 20]))->setRelation('route', $route);
        $reservation = (new Reservation(['user_id' => 10]))->setRelation('plannedTrip', $trip);
        $repository = Mockery::mock(ReservationRepositoryInterface::class);
        $repository->shouldReceive('findById')->andReturn($reservation);
        $this->app->instance(ReservationRepositoryInterface::class, $repository);

        foreach ([[0, 30, 200], [1, 10, 200], [2, 20, 200], [1, 11, 403], [2, 21, 403], [3, 30, 403]] as [$role, $id, $status]) {
            $user = new User(['role' => $role, 'status_id' => 1]);
            $user->id = $id;
            Sanctum::actingAs($user);
            $this->getJson('/api/reservations/reservation/1')->assertStatus($status);
        }
    }

    public function test_repeated_cancellation_refunds_only_once(): void
    {
        $this->prepareCancellationDatabase();
        $payload = ['id' => 1, 'reason' => 'Cancelled by customer'];

        $this->postJson('/api/reservations/cancel', $payload)->assertOk();
        $this->postJson('/api/reservations/cancel', $payload)->assertStatus(400);

        $this->assertEquals(150, DB::table('users')->where('id', 10)->value('wallet'));
        $this->assertSame(1, DB::table('user_refunds')->count());
        $this->assertEquals(4, DB::table('customer_reserved_trips')->where('id', 1)->value('ride_status'));
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_failed_refund_rolls_back_wallet_and_reservation(): void
    {
        $this->prepareCancellationDatabase();
        $refunds = Mockery::mock(UserRefundRepositoryInterface::class);
        $refunds->shouldReceive('create')->once()->andThrow(new \RuntimeException('Refund write failed'));
        $this->app->instance(UserRefundRepositoryInterface::class, $refunds);

        $this->postJson('/api/reservations/cancel', ['id' => 1, 'reason' => 'Cancel'])->assertStatus(500);

        $this->assertEquals(100, DB::table('users')->where('id', 10)->value('wallet'));
        $this->assertEquals(0, DB::table('customer_reserved_trips')->where('id', 1)->value('ride_status'));
        $this->assertSame(0, DB::table('user_refunds')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_missing_reservation_leaves_no_open_transaction(): void
    {
        $this->prepareCancellationDatabase();
        $this->postJson('/api/reservations/cancel', ['id' => 99, 'reason' => 'Cancel'])->assertNotFound();
        $this->assertSame(0, DB::transactionLevel());
    }

    private function prepareCancellationDatabase(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Enable pdo_sqlite to run isolated cancellation tests.');
        }
        // Never run migrations or write test fixtures against the configured application database.
        config(['database.default' => 'reservation_testing', 'database.connections.reservation_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::purge('reservation_testing');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('role');
            $table->decimal('wallet', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('planned_trips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_id');
        });
        Schema::create('customer_reserved_trips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('planned_trip_id');
            $table->integer('ride_status');
            $table->decimal('paid_price', 12, 2);
            $table->timestamps();
        });
        Schema::create('user_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('reservation_id');
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->date('refund_date');
            $table->timestamps();
        });
        DB::table('users')->insert([
            ['id' => 10, 'role' => 1, 'wallet' => 100],
            ['id' => 20, 'role' => 2, 'wallet' => 0],
        ]);
        DB::table('planned_trips')->insert(['id' => 1, 'driver_id' => 20]);
        DB::table('customer_reserved_trips')->insert([
            'id' => 1, 'user_id' => 10, 'planned_trip_id' => 1, 'ride_status' => 0, 'paid_price' => 50,
        ]);
        $coupons = Mockery::mock(CouponCustomerRepositoryInterface::class);
        $coupons->shouldReceive('findByWhere')->andReturn(new Collection());
        $this->app->instance(CouponCustomerRepositoryInterface::class, $coupons);
        $admin = new User(['role' => 0]);
        $admin->id = 30;
        Sanctum::actingAs($admin);
    }
}
