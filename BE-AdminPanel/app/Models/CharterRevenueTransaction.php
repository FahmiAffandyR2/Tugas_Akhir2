<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharterRevenueTransaction extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function booking() { return $this->belongsTo(CharterBooking::class, 'charter_booking_id'); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
}
