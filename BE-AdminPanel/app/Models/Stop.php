<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stop extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function routes()
    {
        return $this->belongsToMany(Route::class, 'route_stops');
    }

    public function scopeCategory($query, $category)
    {
        if ($category) {
            return $query->where('category', $category);
        }
        return $query;
    }

    public function scopeTouristAttractions($query)
    {
        return $query->where('category', 'tourist_attraction');
    }
}
