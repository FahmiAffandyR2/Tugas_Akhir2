<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FleetDepot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FleetDepotController extends Controller
{
    public function index()
    {
        $depots = FleetDepot::withCount('buses')
            ->withCount(['buses as available_buses_count' => function ($query) {
                $query->where('status', 'available');
            }])
            ->orderBy('city')
            ->get();

        return response()->json([
            'depots' => $depots,
        ]);
    }

    public function show(FleetDepot $fleetDepot)
    {
        $fleetDepot->load([
            'buses' => function ($q) {
                $q->with('driver:id,name,email,tel_number', 'busType:id,name,capacity')
                    ->orderBy('fleet_number');
            },
        ]);
        $fleetDepot->loadCount('buses');
        $fleetDepot->loadCount(['buses as available_buses_count' => function ($q) {
            $q->where('status', 'available');
        }]);
        $fleetDepot->loadCount(['buses as on_trip_buses_count' => function ($q) {
            $q->where('status', 'on_trip');
        }]);
        $fleetDepot->loadCount(['buses as inactive_buses_count' => function ($q) {
            $q->where('is_active', false);
        }]);

        return response()->json(['depot' => $fleetDepot]);
    }

    public function store(Request $request)
    {
        $depot = FleetDepot::create($this->validated($request));

        return response()->json([
            'message' => 'Depo armada berhasil dibuat.',
            'depot' => $depot->loadCount('buses')->loadCount(['buses as available_buses_count' => function ($query) {
                $query->where('status', 'available');
            }]),
        ], 201);
    }

    public function update(Request $request, FleetDepot $fleetDepot)
    {
        $fleetDepot->update($this->validated($request));

        return response()->json([
            'message' => 'Depo armada berhasil diperbarui.',
            'depot' => $fleetDepot->fresh()->loadCount('buses')->loadCount(['buses as available_buses_count' => function ($query) {
                $query->where('status', 'available');
            }]),
        ]);
    }

    public function destroy(FleetDepot $fleetDepot)
    {
        if ($fleetDepot->buses()->exists()) {
            return response()->json([
                'message' => 'Depo masih memiliki armada. Pindahkan bus sebelum menghapus depo.',
            ], 422);
        }

        $fleetDepot->delete();
        return response()->json(['message' => 'Depo armada berhasil dihapus.']);
    }

    private function validated(Request $request)
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'city' => 'required|string|max:100',
            'address' => 'nullable|string|max:1000',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'geofence_radius' => 'nullable|integer|min:50|max:5000',
            'contact_name' => 'nullable|string|max:150',
            'contact_phone' => 'nullable|string|max:30',
            'is_active' => 'required|boolean',
        ]);
    }
}
