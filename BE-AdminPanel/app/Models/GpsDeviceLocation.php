<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpsDeviceLocation extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'speed' => 'decimal:2',
        'heading' => 'decimal:2',
        'accuracy' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }
}
