<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverShift extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'shift_date' => 'date',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function plannedTrip()
    {
        return $this->belongsTo(PlannedTrip::class, 'planned_trip_id');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('shift_date', $date);
    }

    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('shift_date', $year)
                     ->whereMonth('shift_date', $month);
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }
}
