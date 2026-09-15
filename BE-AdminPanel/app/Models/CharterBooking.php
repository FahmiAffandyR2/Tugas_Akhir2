<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharterBooking extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'departure_date' => 'date:Y-m-d',
        'return_date' => 'date:Y-m-d',
        'quoted_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'price_breakdown' => 'array',
        'destinations' => 'array',
        'passenger_count' => 'integer',
        'requested_bus_count' => 'integer',
        'payment_submitted_at' => 'datetime',
        'paid_at' => 'datetime',
        'assigned_at' => 'datetime',
        'payment_deadline' => 'datetime',
    ];

    protected $hidden = ['payment_proof_path'];
    protected $appends = ['rental_days'];

    public function getRentalDaysAttribute(): ?int
    {
        if (!$this->departure_date) return null;
        return $this->return_date ? (int) $this->departure_date->diffInDays($this->return_date) + 1 : 1;
    }

    public function scopeForDepot($query, $depotId)
    {
        if (!$depotId) {
            return $query->whereRaw('1 = 0');
        }
        return $query->where(function ($query) use ($depotId) {
            $query->where('depot_id', $depotId)
                ->orWhereHas('bus', fn ($bus) => $bus->where('depot_id', $depotId))
                ->orWhereHas('assignments.bus', fn ($bus) => $bus->where('depot_id', $depotId));
        });
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function bus() { return $this->belongsTo(Bus::class); }
    public function busType() { return $this->belongsTo(BusType::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
    public function depot() { return $this->belongsTo(FleetDepot::class, 'depot_id'); }
    public function originArea() { return $this->belongsTo(ServiceArea::class, 'origin_area_id'); }
    public function destinationArea() { return $this->belongsTo(ServiceArea::class, 'destination_area_id'); }
    public function operationalTrip() { return $this->belongsTo(PlannedTrip::class, 'operational_planned_trip_id'); }
    public function revenueTransaction() { return $this->hasOne(CharterRevenueTransaction::class); }
    public function assignments() { return $this->hasMany(CharterBookingAssignment::class); }
}
