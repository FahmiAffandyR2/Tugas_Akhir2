<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusType extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'capacity' => 'integer',
        'price_factor' => 'decimal:2',
        'base_price' => 'decimal:2',
        'price_per_km' => 'decimal:2',
        'minimum_price' => 'decimal:2',
        'pickup_fee' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function buses()
    {
        return $this->hasMany(Bus::class);
    }
}
