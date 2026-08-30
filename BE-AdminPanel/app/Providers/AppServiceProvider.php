<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Observers\AuditLogObserver;
use App\Models\Bus;
use App\Models\BusType;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Complaint;
use App\Models\CharterBooking;
use App\Models\FleetDepot;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->app->register(RepositoryServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Bus::observe(AuditLogObserver::class);
        BusType::observe(AuditLogObserver::class);
        Route::observe(AuditLogObserver::class);
        Stop::observe(AuditLogObserver::class);
        Complaint::observe(AuditLogObserver::class);
        CharterBooking::observe(AuditLogObserver::class);
        FleetDepot::observe(AuditLogObserver::class);
    }
}
