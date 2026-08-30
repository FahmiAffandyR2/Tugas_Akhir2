<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'capacity' => 'integer',
        'price_factor' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    //driver
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function depot()
    {
        return $this->belongsTo(FleetDepot::class, 'depot_id');
    }

    public function busType()
    {
        return $this->belongsTo(BusType::class);
    }

    public function gpsLocations()
    {
        return $this->hasMany(GpsDeviceLocation::class);
    }

    public function latestGpsLocation()
    {
        return $this->hasOne(GpsDeviceLocation::class)->latestOfMany('recorded_at');
    }
}
