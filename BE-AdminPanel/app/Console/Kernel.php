<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Traits\TripUtils;
use App\Traits\UserUtils;

class Kernel extends ConsoleKernel
{
    use TripUtils;
    use UserUtils;
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function () {
            $this->deleteAccounts();
            $this->publishTrips();
        })->everyMinute();

        // Cek status GPS setiap 30 detik
        $schedule->command('gps:check-status')->everyThirtySeconds();

        // Auto-reject charter bookings dengan deadline pembayaran habis
        $schedule->command('charter:expire-payments')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
