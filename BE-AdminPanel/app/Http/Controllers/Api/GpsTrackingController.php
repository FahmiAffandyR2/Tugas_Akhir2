<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Bus;
use App\Models\GpsDeviceLocation;

class GpsTrackingController extends Controller
{
    public function deviceIngest(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|max:50',
            'bus_id' => 'required|integer|exists:buses,id',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|integer|min:0',
            'recorded_at' => 'nullable|date',
        ]);

        $recordedAt = $request->recorded_at ?? now();

        GpsDeviceLocation::create([
            'bus_id' => $request->bus_id,
            'device_id' => $request->device_id,
            'source' => 'device',
            'lat' => $request->lat,
            'lng' => $request->lng,
            'speed' => $request->speed,
            'heading' => $request->heading,
            'accuracy' => $request->accuracy,
            'recorded_at' => $recordedAt,
        ]);

        Bus::where('id', $request->bus_id)->update([
            'gps_source' => 'device',
            'current_lat' => $request->lat,
            'current_lng' => $request->lng,
            'current_speed' => $request->speed,
            'last_gps_at' => $recordedAt,
            'gps_device_id' => $request->device_id,
        ]);

        return response()->json(['status' => 'ok'], 201);
    }

    public function phoneUpdate(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|integer|min:0',
        ]);

        $user = $request->user();
        $bus = Bus::where('driver_id', $user->id)->first();

        if (!$bus) {
            return response()->json(['error' => 'No bus assigned'], 404);
        }

        $recordedAt = now();

        GpsDeviceLocation::create([
            'bus_id' => $bus->id,
            'device_id' => null,
            'source' => 'phone',
            'lat' => $request->lat,
            'lng' => $request->lng,
            'speed' => $request->speed,
            'heading' => $request->heading,
            'accuracy' => $request->accuracy,
            'recorded_at' => $recordedAt,
        ]);

        $bus->update([
            'gps_source' => 'phone',
            'current_lat' => $request->lat,
            'current_lng' => $request->lng,
            'current_speed' => $request->speed,
            'last_gps_at' => $recordedAt,
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function getVehicles(Request $request)
    {
        $buses = Bus::with('driver:id,name', 'depot:id,name')
            ->where('is_active', true)
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->get();

        $vehicles = $buses->map(function ($bus) {
            $isOnline = $bus->last_gps_at && $bus->last_gps_at->diffInMinutes(now()) < 5;
            $isIdle = $bus->last_gps_at && !$isOnline && $bus->last_gps_at->diffInMinutes(now()) < 30;

            return [
                'id' => $bus->id,
                'fleet_number' => $bus->fleet_number,
                'license' => $bus->license,
                'lat' => (float) $bus->current_lat,
                'lng' => (float) $bus->current_lng,
                'speed' => $bus->current_speed ? (float) $bus->current_speed : null,
                'gps_source' => $bus->gps_source,
                'last_gps_at' => $bus->last_gps_at?->toISOString(),
                'status' => $isOnline ? 'online' : ($isIdle ? 'idle' : 'offline'),
                'driver' => $bus->driver ? [
                    'id' => $bus->driver->id,
                    'name' => $bus->driver->name,
                ] : null,
                'depot' => $bus->depot ? [
                    'id' => $bus->depot->id,
                    'name' => $bus->depot->name,
                ] : null,
            ];
        });

        return response()->json($vehicles);
    }

    public function getHistory(Request $request, $busId)
    {
        $from = $request->get('from', now()->subDay()->toISOString());
        $to = $request->get('to', now()->toISOString());

        $locations = GpsDeviceLocation::where('bus_id', $busId)
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at', 'asc')
            ->get([
                'lat', 'lng', 'speed', 'heading', 'source', 'recorded_at',
            ]);

        return response()->json($locations);
    }
}
