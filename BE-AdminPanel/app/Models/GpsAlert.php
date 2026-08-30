<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpsAlert extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'driver_id',
        'planned_trip_id',
        'alert_type',
        'message',
        'metadata',
        'dismissed',
        'dismissed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'dismissed' => 'boolean',
        'dismissed_at' => 'datetime',
    ];

    /**
     * Alert type constants
     */
    const TYPE_GPS_OFFLINE = 'gps_offline';
    const TYPE_OUT_OF_ROUTE = 'out_of_route';
    const TYPE_SPEED_EXCEEDED = 'speed_exceeded';
    const TYPE_ARRIVED_AT_DEPOT = 'arrived_at_depot';

    /**
     * Get the driver that owns the alert.
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the planned trip that owns the alert.
     */
    public function plannedTrip()
    {
        return $this->belongsTo(PlannedTrip::class);
    }

    /**
     * Scope to get only active (non-dismissed) alerts.
     */
    public function scopeActive($query)
    {
        return $query->where('dismissed', false);
    }

    /**
     * Scope to get alerts by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('alert_type', $type);
    }

    /**
     * Mark alert as dismissed.
     */
    public function dismiss()
    {
        $this->update([
            'dismissed' => true,
            'dismissed_at' => now(),
        ]);
    }

    /**
     * Get alert type label.
     */
    public function getTypeLabelAttribute()
    {
        return match($this->alert_type) {
            self::TYPE_GPS_OFFLINE => 'GPS Offline',
            self::TYPE_OUT_OF_ROUTE => 'Keluar Jalur',
            self::TYPE_SPEED_EXCEEDED => 'Kecepatan Berlebih',
            self::TYPE_ARRIVED_AT_DEPOT => 'Tiba di Pool',
            default => 'Unknown',
        };
    }

    /**
     * Get alert type icon.
     */
    public function getTypeIconAttribute()
    {
        return match($this->alert_type) {
            self::TYPE_GPS_OFFLINE => 'mdi-wifi-off',
            self::TYPE_OUT_OF_ROUTE => 'mdi-map-marker-off',
            self::TYPE_SPEED_EXCEEDED => 'mdi-speedometer',
            self::TYPE_ARRIVED_AT_DEPOT => 'mdi-home-map-marker',
            default => 'mdi-alert',
        };
    }

    /**
     * Get alert type color.
     */
    public function getTypeColorAttribute()
    {
        return match($this->alert_type) {
            self::TYPE_GPS_OFFLINE => 'error',
            self::TYPE_OUT_OF_ROUTE => 'warning',
            self::TYPE_SPEED_EXCEEDED => 'orange',
            self::TYPE_ARRIVED_AT_DEPOT => 'success',
            default => 'grey',
        };
    }
}
