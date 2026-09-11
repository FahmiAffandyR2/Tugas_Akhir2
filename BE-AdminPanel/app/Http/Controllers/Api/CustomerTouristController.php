<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Stop;
use App\Models\FleetDepot;
use App\Models\PlannedTrip;
use App\Models\Route;
use App\Models\BusType;

class CustomerTouristController extends Controller
{
    public function touristStops(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
        ]);

        $query = Stop::where('category', 'tourist_attraction')
            ->select('id', 'name', 'address', 'lat', 'lng', 'description');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $stops = $query->orderBy('name')->get();

        $stopsWithDepot = $stops->map(function ($stop) {
            $nearestDepot = FleetDepot::where('is_active', true)
                ->select('id', 'name', 'city', 'address', 'latitude', 'longitude')
                ->selectRaw("(6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )) AS distance_km", [$stop->lat, $stop->lng, $stop->lat])
                ->orderBy('distance_km')
                ->first();

            return [
                'id' => $stop->id,
                'name' => $stop->name,
                'address' => $stop->address,
                'lat' => $stop->lat,
                'lng' => $stop->lng,
                'description' => $stop->description,
                'nearest_depot' => $nearestDepot ? [
                    'id' => $nearestDepot->id,
                    'name' => $nearestDepot->name,
                    'city' => $nearestDepot->city,
                    'distance_km' => round($nearestDepot->distance_km ?? 0, 2),
                ] : null,
            ];
        });

        return response()->json([
            'tourist_stops' => $stopsWithDepot,
        ], 200);
    }

    public function touristStopDetail($stopId)
    {
        $stop = Stop::where('id', $stopId)
            ->where('category', 'tourist_attraction')
            ->first();

        if (!$stop) {
            return response()->json(['message' => 'Tempat wisata tidak ditemukan'], 404);
        }

        $depots = FleetDepot::where('is_active', true)
            ->select('id', 'name', 'city', 'address', 'latitude', 'longitude', 'contact_name', 'contact_phone')
            ->selectRaw("(6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude))
            )) AS distance_km", [$stop->lat, $stop->lng, $stop->lat])
            ->orderBy('distance_km')
            ->limit(5)
            ->withCount('buses')
            ->withCount(['buses as available_buses_count' => function ($query) {
                $query->where('status', 'available');
            }])
            ->get();

        return response()->json([
            'stop' => [
                'id' => $stop->id,
                'name' => $stop->name,
                'address' => $stop->address,
                'lat' => $stop->lat,
                'lng' => $stop->lng,
                'description' => $stop->description,
            ],
            'nearby_depots' => $depots,
        ], 200);
    }

    public function depotAvailableRoutes($depotId)
    {
        $depot = FleetDepot::find($depotId);

        if (!$depot) {
            return response()->json(['message' => 'Depo tidak ditemukan'], 404);
        }

        $routes = Route::whereHas('plannedTrips', function ($q) use ($depotId) {
            $q->where('status_id', 1)
              ->whereNotNull('driver_id')
              ->where('planned_date', '>=', now()->toDateString());
        })
        ->with(['plannedTrips' => function ($q) use ($depotId) {
            $q->where('status_id', 1)
              ->whereNotNull('driver_id')
              ->where('planned_date', '>=', now()->toDateString())
              ->with(['driver:id,name', 'bus:id,plate_number,bus_type_id'])
              ->orderBy('planned_date')
              ->limit(10);
        }])
        ->get()
        ->filter(function ($route) {
            return $route->plannedTrips->isNotEmpty();
        })
        ->values();

        return response()->json([
            'depot' => [
                'id' => $depot->id,
                'name' => $depot->name,
                'city' => $depot->city,
            ],
            'routes' => $routes,
        ], 200);
    }

    public function getBusPrices(Request $request, $stopId)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $stop = Stop::where('id', $stopId)
            ->where('category', 'tourist_attraction')
            ->first();

        if (!$stop) {
            return response()->json(['message' => 'Tempat wisata tidak ditemukan'], 404);
        }

        $userLat = (float) $request->lat;
        $userLng = (float) $request->lng;
        $stopLat = (float) $stop->lat;
        $stopLng = (float) $stop->lng;

        $distanceKm = $this->haversineDistance($userLat, $userLng, $stopLat, $stopLng);

        $busTypes = BusType::where('is_active', true)
            ->orderBy('base_price')
            ->get();

        $prices = $busTypes->map(function ($busType) use ($distanceKm) {
            $basePrice = (float) ($busType->base_price ?? 0);
            $pricePerKm = (float) ($busType->price_per_km ?? 0);
            $minimumPrice = (float) ($busType->minimum_price ?? 0);
            $pickupFee = (float) ($busType->pickup_fee ?? 0);

            $unitPrice = max($minimumPrice, $basePrice + ($distanceKm * $pricePerKm));
            $totalPrice = $unitPrice + $pickupFee;

            return [
                'id' => $busType->id,
                'name' => $busType->name,
                'slug' => $busType->slug,
                'capacity' => $busType->capacity,
                'unit_price' => round($unitPrice, 2),
                'pickup_fee' => round($pickupFee, 2),
                'total_price' => round($totalPrice, 2),
                'formatted_price' => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
                'breakdown' => [
                    'base_price' => $basePrice,
                    'price_per_km' => $pricePerKm,
                    'minimum_price' => $minimumPrice,
                    'pickup_fee' => $pickupFee,
                    'distance_km' => round($distanceKm, 2),
                ],
            ];
        });

        return response()->json([
            'stop' => [
                'id' => $stop->id,
                'name' => $stop->name,
            ],
            'distance_km' => round($distanceKm, 2),
            'prices' => $prices,
        ], 200);
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
