<?php

namespace App\Console\Commands;

use App\Models\CharterBooking;
use App\Models\Notification;
use Illuminate\Console\Command;

class ExpireCharterPayments extends Command
{
    protected $signature = 'charter:expire-payments';

    protected $description = 'Auto-reject charter bookings with expired payment deadlines';

    public function handle(): int
    {
        $expiredBookings = CharterBooking::where('status', 'quote_sent')
            ->where('payment_status', 'unpaid')
            ->whereNotNull('payment_deadline')
            ->where('payment_deadline', '<', now())
            ->get();

        $count = 0;

        foreach ($expiredBookings as $booking) {
            $booking->update([
                'status' => 'rejected',
                'admin_notes' => 'Ditolak otomatis: batas waktu pembayaran telah habis.',
            ]);

            Notification::create([
                'user_id' => $booking->customer_id,
                'message' => "Booking {$booking->reference_code} ditolak otomatis karena batas waktu pembayaran telah habis. Silakan buat booking baru jika masih ingin melanjutkan.",
                'seen' => 0,
            ]);

            $count++;
        }

        if ($count > 0) {
            $this->info("{$count} booking expired dan ditolak otomatis.");
        } else {
            $this->info('Tidak ada booking yang expired.');
        }

        return Command::SUCCESS;
    }
}
