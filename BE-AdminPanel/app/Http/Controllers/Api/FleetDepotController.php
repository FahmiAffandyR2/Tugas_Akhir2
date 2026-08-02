<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FleetDepot;
use Illuminate\Http\Request;

class FleetDepotController extends Controller
{
    public function index()
    {
        return response()->json([
            'depots' => FleetDepot::withCount('buses')->orderBy('city')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $depot = FleetDepot::create($this->validated($request));

        return response()->json([
            'message' => 'Depo armada berhasil dibuat.',
            'depot' => $depot->loadCount('buses'),
        ], 201);
    }

    public function update(Request $request, FleetDepot $fleetDepot)
    {
        $fleetDepot->update($this->validated($request));

        return response()->json([
            'message' => 'Depo armada berhasil diperbarui.',
            'depot' => $fleetDepot->fresh()->loadCount('buses'),
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
            'contact_name' => 'nullable|string|max:150',
            'contact_phone' => 'nullable|string|max:30',
            'is_active' => 'required|boolean',
        ]);
    }
}
