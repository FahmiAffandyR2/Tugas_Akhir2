<?php

namespace Tests\Feature;

use App\Models\CharterBooking;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WeeklyScheduleTest extends TestCase
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
            $table->id(); $table->unsignedBigInteger('bus_id')->nullable(); $table->unsignedBigInteger('charter_booking_id')->nullable(); $table->date('planned_date');
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
        Schema::table('buses', function (Blueprint $t) { $t->integer('capacity')->default(45); $t->integer('depot_id')->nullable(); $t->string('license')->nullable(); $t->timestamps(); });
        Schema::table('users', function (Blueprint $t) { $t->integer('status_id')->default(1); foreach (['name','email','tel_number'] as $f) $t->string($f)->nullable(); });
        Schema::table('charter_bookings', function (Blueprint $t) {
            foreach (['bus_id','driver_id','depot_id'] as $f) $t->integer($f)->nullable();
            foreach (['payment_bank_name','payment_account_number','payment_account_holder','admin_notes'] as $f) $t->string($f)->nullable();
            $t->timestamp('assigned_at')->nullable(); $t->string('payment_status')->default('paid');
        });
        Schema::table('planned_trips', function (Blueprint $t) {
            foreach (['driver_id','route_id','trip_id','reserved_seats'] as $f) $t->integer($f)->nullable();
            $t->string('channel')->nullable(); $t->timestamp('started_at')->nullable(); $t->timestamp('ended_at')->nullable(); $t->timestamps();
        });
        Schema::create('charter_booking_assignments', function (Blueprint $t) { $t->id(); foreach (['charter_booking_id','bus_id','driver_id'] as $f) $t->integer($f); $t->timestamps(); });
        Schema::create('routes', function (Blueprint $t) { $t->id(); $t->string('name'); $t->timestamps(); $t->softDeletes(); });
        Schema::create('trips', function (Blueprint $t) {
            $t->id(); foreach (['route_id','driver_id','repetition_period','stop_to_stop_avg_time','status_id'] as $f) $t->integer($f)->nullable();
            foreach (['channel','effective_date','first_stop_time','last_stop_time'] as $f) $t->string($f)->nullable(); $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->text('message'); $t->integer('seen'); $t->timestamps(); });
        Schema::create('fleet_depots', function (Blueprint $t) { $t->id(); });
        DB::table('buses')->insert([['id'=>1,'bus_type_id'=>1],['id'=>2,'bus_type_id'=>1]]);
        DB::table('users')->insert([['id'=>1,'role'=>2],['id'=>2,'role'=>2],['id'=>3,'role'=>2]]);
        $user = new User(['role'=>0,'status_id'=>1]); $user->id=10;
        Sanctum::actingAs($user);
        $this->withHeader('Authorization', 'Bearer 1|test-token');
        Schema::create('audit_logs', function (Blueprint $t) { $t->id(); $t->integer('user_id')->nullable(); $t->integer('entity_id')->nullable(); foreach (['action','entity_type','old_values','new_values','ip_address','description'] as $f) $t->text($f)->nullable(); $t->timestamps(); });
    }


    private function booking(string $day = '2030-01-07'): CharterBooking
    {
        return CharterBooking::withoutEvents(fn () => CharterBooking::create([
            'customer_id'=>1, 'reference_code'=>'TEST', 'bus_type_id'=>1, 'origin'=>'Jakarta', 'destination'=>'Bandung',
            'departure_date'=>$day, 'return_date'=>$day, 'departure_time'=>'08:00', 'return_time'=>'18:00',
            'status'=>'approved', 'payment_status'=>'paid', 'passenger_count'=>90, 'requested_bus_count'=>2,
        ]));
    }

    private function invoke(string $method, ...$args)
    {
        $controller = new \App\Http\Controllers\Api\CharterBookingController;
        return \Illuminate\Database\Eloquent\Model::withoutEvents(fn () => (new \ReflectionMethod($controller, $method))->invoke($controller, ...$args));
    }

    private function assign(CharterBooking $booking): void
    {
        $booking->assignments()->createMany([['bus_id'=>1,'driver_id'=>1], ['bus_id'=>2,'driver_id'=>2]]);
        $this->invoke('syncOperationalTrip', $booking);
    }

    public function test_every_unit_links_to_booking_and_reassignment_reuses_schedule(): void
    {
        $booking=$this->booking(); $this->assign($booking);
        $trips=\App\Models\PlannedTrip::orderBy('id')->get();
        $this->assertCount(2,$trips);
        foreach($trips as $trip) $this->assertEquals($booking->id,$trip->charterBooking->id);
        $this->assertEquals('available',DB::table('buses')->where('id',1)->value('status'));
        $booking->assignments()->where('bus_id',2)->update(['driver_id'=>3]);
        $this->invoke('syncOperationalTrip',$booking);
        $this->assertEquals(2,DB::table('planned_trips')->count());
        $this->assertEquals(3,DB::table('planned_trips')->where('bus_id',2)->value('driver_id'));
    }

    public function test_pending_and_multiday_bookings_appear_without_manual_schedule(): void
    {
        $booking=$this->booking('2030-01-06'); $booking->update(['status'=>'waiting_quote','return_date'=>'2030-01-08']);
        $this->getJson('/api/charter-bookings/weekly-schedule?start=2030-01-07')->assertOk()->assertJsonCount(1,'bookings')->assertJsonPath('bookings.0.status','waiting_quote');
        $this->assertEquals(0,DB::table('planned_trips')->count());
    }

    public function test_reschedule_updates_all_units_and_conflict_rolls_back(): void
    {
        $booking=$this->booking();$this->assign($booking);
        $data=['departure_date'=>'2030-01-08','return_date'=>'2030-01-09','departure_time'=>'09:00','return_time'=>'17:00'];
        $this->putJson('/api/charter-bookings/'.$booking->id.'/schedule',$data)->assertOk();
        $this->assertEquals(2,DB::table('planned_trips')->whereDate('planned_date','2030-01-08')->count());
        $this->assertEquals(2,DB::table('trips')->where('first_stop_time','09:00')->count());
        $other=$this->booking('2030-01-10');$other->assignments()->create(['bus_id'=>2,'driver_id'=>3]);
        $data['departure_date']=$data['return_date']='2030-01-10';
        $this->putJson('/api/charter-bookings/'.$booking->id.'/schedule',$data)->assertStatus(422);
        $this->assertEquals('2030-01-08',$booking->fresh()->departure_date->format('Y-m-d'));
    }

    public function test_cancellation_removes_every_future_unit_and_allocation(): void
    {
        $booking=$this->booking();$this->assign($booking);
        $this->invoke('clearOperationalTrips',$booking);
        $this->assertEquals(0,DB::table('planned_trips')->count());
        $this->assertEquals(0,$booking->assignments()->count());
        $this->assertNull($booking->fresh()->operational_planned_trip_id);
        $this->assertEquals(2,DB::table('trips')->where('status_id',0)->count());
    }

    public function test_active_trip_cannot_be_cancelled(): void
    {
        $booking=$this->booking();$this->assign($booking);
        DB::table('planned_trips')->where('bus_id',2)->update(['started_at'=>now()]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->invoke('clearOperationalTrips',$booking);
    }

    public function test_customer_cannot_access_admin_schedule(): void
    {
        $user=new User(['role'=>1,'status_id'=>1]);$user->id=100;Sanctum::actingAs($user);
        $this->getJson('/api/charter-bookings/weekly-schedule?start=2030-01-07')->assertForbidden();
    }
    public function test_assignment_endpoint_uses_booking_dates_without_reentering_them(): void
    {
        $this->withoutExceptionHandling();
        $booking=$this->booking();
        $this->putJson('/api/charter-bookings/admin/'.$booking->id,[
            'status'=>'approved', 'quoted_price'=>1000000, 'payment_bank_name'=>'Test',
            'payment_account_number'=>'123','payment_account_holder'=>'Test',
            'assignments'=>[['bus_id'=>1,'driver_id'=>1],['bus_id'=>2,'driver_id'=>2]],
        ])->assertOk();
        $this->assertEquals(2,DB::table('planned_trips')->count());
        $this->assertEquals('08:00',DB::table('trips')->value('first_stop_time'));
    }

    public function test_same_bus_can_use_different_driver_after_previous_booking_ends(): void
    {
        $other=$this->booking();$other->assignments()->create(['bus_id'=>1,'driver_id'=>3]);
        $booking=$this->booking();
        $this->invoke('ensureAssignmentAvailable',$booking,['departure_time'=>'18:00','return_date'=>'2030-01-07','return_time'=>'23:00'],[['bus_id'=>1,'driver_id'=>1],['bus_id'=>2,'driver_id'=>2]]);
        $this->assertTrue(true);
    }

    public function test_overnight_regular_trip_prevents_conflicting_assignment(): void
    {
        $trip=DB::table('trips')->insertGetId(['first_stop_time'=>'23:00','last_stop_time'=>'10:00']);
        DB::table('planned_trips')->insert(['bus_id'=>2,'driver_id'=>3,'trip_id'=>$trip,'planned_date'=>'2030-01-06']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->invoke('ensureAssignmentAvailable',$this->booking(),['departure_time'=>'08:00','return_date'=>'2030-01-07','return_time'=>'18:00'],[['bus_id'=>1,'driver_id'=>1],['bus_id'=>2,'driver_id'=>2]]);
    }

    public function test_admin_cancellation_clears_the_entire_booking_schedule(): void
    {
        $booking=$this->booking();$this->assign($booking);
        $this->postJson('/api/charter-bookings/admin/'.$booking->id.'/cancel',['reason'=>'Customer meminta pembatalan'])->assertOk()->assertJsonPath('booking.status','cancelled');
        $this->assertEquals(0,DB::table('planned_trips')->count());
        $this->assertEquals(0,DB::table('charter_booking_assignments')->count());
    }

}
