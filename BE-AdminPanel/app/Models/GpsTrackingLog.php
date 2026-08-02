<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpsTrackingLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'driver_id',
        'bus_id',
        'planned_trip_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'accuracy',
        'recorded_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'latitude' => 'double',
        'longitude' => 'double',
        'speed' => 'double',
        'heading' => 'double',
        'accuracy' => 'double',
        'recorded_at' => 'datetime',
    ];

    /**
     * Get the driver that owns the tracking log.
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the bus that owns the tracking log.
     */
    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    /**
     * Get the planned trip that owns the tracking log.
     */
    public function plannedTrip()
    {
        return $this->belongsTo(PlannedTrip::class);
    }

    /**
     * Get the latest position for a specific driver.
     */
    public static function getLatestPosition($driverId)
    {
        return self::where('driver_id', $driverId)
            ->orderBy('recorded_at', 'desc')
            ->first();
    }

    /**
     * Get tracking logs for a specific trip.
     */
    public static function getTripLogs($plannedTripId)
    {
        return self::where('planned_trip_id', $plannedTripId)
            ->orderBy('recorded_at', 'asc')
            ->get();
    }

    /**
     * Get all active driver positions (latest for each driver).
     */
    public static function getActiveDriverPositions()
    {
        return self::selectRaw('driver_id, MAX(recorded_at) as latest_time')
            ->groupBy('driver_id')
            ->get()
            ->map(function ($item) {
                return self::where('driver_id', $item->driver_id)
                    ->where('recorded_at', $item->latest_time)
                    ->first();
            });
    }
}
