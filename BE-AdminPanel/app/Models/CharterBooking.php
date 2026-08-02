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
        'passenger_count' => 'integer',
        'payment_submitted_at' => 'datetime',
        'paid_at' => 'datetime',
        'assigned_at' => 'datetime',
    ];

    protected $hidden = ['payment_proof_path'];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function bus() { return $this->belongsTo(Bus::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
    public function depot() { return $this->belongsTo(FleetDepot::class, 'depot_id'); }
    public function operationalTrip() { return $this->belongsTo(PlannedTrip::class, 'operational_planned_trip_id'); }
    public function revenueTransaction() { return $this->hasOne(CharterRevenueTransaction::class); }
}
