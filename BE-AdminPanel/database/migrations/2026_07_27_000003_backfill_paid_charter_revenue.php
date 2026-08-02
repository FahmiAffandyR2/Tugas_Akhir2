<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('charter_bookings')
            ->where('payment_status', 'paid')
            ->whereNotNull('quoted_price')
            ->orderBy('id')
            ->each(function ($booking) {
                DB::table('charter_revenue_transactions')->updateOrInsert(
                    ['charter_booking_id' => $booking->id],
                    [
                        'amount' => $booking->quoted_price,
                        'status' => 'paid',
                        'verified_by' => null,
                        'paid_at' => $booking->paid_at ?: $booking->updated_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        // Financial history is intentionally retained on rollback.
    }
};
