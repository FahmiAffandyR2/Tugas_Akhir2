<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::table('planned_trips', function(Blueprint $table) { $table->unsignedBigInteger('charter_booking_id')->nullable()->index(); });
  DB::table('charter_bookings')->whereNotNull('operational_planned_trip_id')->orderBy('id')->chunkById(100, function($bookings) {
   foreach($bookings as $booking) {
    $planned=DB::table('planned_trips')->where('id',$booking->operational_planned_trip_id)->first();
    if($planned) DB::table('planned_trips')->where('route_id',$planned->route_id)->whereNull('charter_booking_id')->update(['charter_booking_id'=>$booking->id]);
   }
  });
 }
 public function down(): void { Schema::table('planned_trips', fn(Blueprint $table) => $table->dropColumn('charter_booking_id')); }
};
