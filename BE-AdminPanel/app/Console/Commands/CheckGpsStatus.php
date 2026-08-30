<?php

namespace App\Console\Commands;

use App\Models\GpsTrackingLog;
use App\Models\GpsAlert;
use App\Models\PlannedTrip;
use App\Models\RouteStopDirection;
use App\Models\FleetDepot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckGpsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gps:check-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check GPS status for all active trips and create alerts if needed';

    /**
     * GPS offline threshold in seconds.
     */
    const GPS_OFFLINE_THRESHOLD = 60; // 1 minute

    /**
     * Out of route threshold in kilometers.
     */
    const OUT_OF_ROUTE_THRESHOLD = 2; // 2 km

    /**
     * Speed exceeded threshold in km/h.
     */
    const SPEED_EXCEEDED_THRESHOLD = 120; // 120 km/h

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Checking GPS status for active trips...');

        $activeTrips = PlannedTrip::whereNotNull('started_at')
            ->whereNull('ended_at')
            ->with(['driver', 'bus', 'route'])
            ->get();

        $alertsCreated = 0;

        foreach ($activeTrips as $trip) {
            // Check GPS offline
            if ($this->checkGpsOffline($trip)) {
                $alertsCreated++;
            }

            // Get latest GPS position
            $latestGps = GpsTrackingLog::where('planned_trip_id', $trip->id)
                ->orderBy('recorded_at', 'desc')
                ->first();

            if ($latestGps) {
                // Check out of route
                if ($this->checkOutOfRoute($trip, $latestGps)) {
                    $alertsCreated++;
                }

                // Check speed exceeded
                if ($this->checkSpeedExceeded($trip, $latestGps)) {
                    $alertsCreated++;
                }

                // Check arrived at depot
                if ($this->checkArrivedAtDepot($trip, $latestGps)) {
                    $alertsCreated++;
                }
            }
        }

        $this->info("GPS status check completed. {$alertsCreated} alerts created.");

        return Command::SUCCESS;
    }

    /**
     * Check if GPS is offline for a trip.
     */
    private function checkGpsOffline(PlannedTrip $trip): bool
    {
        $latestGps = GpsTrackingLog::where('planned_trip_id', $trip->id)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (!$latestGps) {
            // No GPS data at all since trip started
            $minutesSinceStart = now()->diffInMinutes($trip->started_at);
            if ($minutesSinceStart >= 2) {
                return $this->createAlert(
                    $trip,
                    GpsAlert::TYPE_GPS_OFFLINE,
                    "GPS belum mengirim data sejak perjalanan dimulai ({$minutesSinceStart} menit lalu)",
                    [
                        'last_position_lat' => null,
                        'last_position_lng' => null,
                        'minutes_since_start' => $minutesSinceStart,
                    ]
                );
            }
            return false;
        }

        $secondsSinceLastUpdate = now()->diffInSeconds($latestGps->recorded_at);

        if ($secondsSinceLastUpdate >= self::GPS_OFFLINE_THRESHOLD) {
            $minutes = floor($secondsSinceLastUpdate / 60);
            $seconds = $secondsSinceLastUpdate % 60;
            $timeLabel = $minutes > 0 ? "{$minutes} menit {$seconds} detik" : "{$seconds} detik";

            return $this->createAlert(
                $trip,
                GpsAlert::TYPE_GPS_OFFLINE,
                "GPS offline selama {$timeLabel}. Last update: {$latestGps->recorded_at->format('H:i:s')}",
                [
                    'last_position_lat' => $latestGps->latitude,
                    'last_position_lng' => $latestGps->longitude,
                    'last_update' => $latestGps->recorded_at->toIso8601String(),
                    'seconds_offline' => $secondsSinceLastUpdate,
                ]
            );
        }

        return false;
    }

    /**
     * Check if the vehicle is out of route.
     */
    private function checkOutOfRoute(PlannedTrip $trip, GpsTrackingLog $latestGps): bool
    {
        if (!$trip->route) {
            return false;
        }

        // Get route path from route_stop_directions
        $routeStops = $trip->route->stops()->orderBy('sequence')->get();
        if ($routeStops->count() < 2) {
            return false;
        }

        // Calculate minimum distance to route
        $minDistance = PHP_FLOAT_MAX;
        $nearestPoint = null;

        // Check distance to each stop
        foreach ($routeStops as $stop) {
            $distance = $this->calculateDistance(
                $latestGps->latitude,
                $latestGps->longitude,
                (float) $stop->lat,
                (float) $stop->lng
            );
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestPoint = ['lat' => (float) $stop->lat, 'lng' => (float) $stop->lng];
            }
        }

        // Check distance to route segments (overview_path)
        $directions = RouteStopDirection::whereHas('routeStop', function ($q) use ($trip) {
            $q->where('route_id', $trip->route_id);
        })->get();

        foreach ($directions as $direction) {
            $path = json_decode($direction->overview_path, true);
            if (!is_array($path)) {
                $path = json_decode($direction->overview_path, true);
            }
            if (!is_array($path)) {
                continue;
            }

            foreach ($path as $point) {
                $lat = is_array($point) ? ($point['lat'] ?? $point[1] ?? null) : null;
                $lng = is_array($point) ? ($point['lng'] ?? $point[0] ?? null) : null;
                if ($lat === null || $lng === null) {
                    continue;
                }

                $distance = $this->calculateDistance(
                    $latestGps->latitude,
                    $latestGps->longitude,
                    (float) $lat,
                    (float) $lng
                );

                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $nearestPoint = ['lat' => (float) $lat, 'lng' => (float) $lng];
                }
            }
        }

        if ($minDistance > self::OUT_OF_ROUTE_THRESHOLD) {
            return $this->createAlert(
                $trip,
                GpsAlert::TYPE_OUT_OF_ROUTE,
                "Bus keluar jalur sejauh " . round($minDistance, 1) . " km dari rute yang ditentukan",
                [
                    'current_lat' => $latestGps->latitude,
                    'current_lng' => $latestGps->longitude,
                    'nearest_route_lat' => $nearestPoint['lat'] ?? null,
                    'nearest_route_lng' => $nearestPoint['lng'] ?? null,
                    'distance_km' => round($minDistance, 2),
                ]
            );
        }

        return false;
    }

    /**
     * Check if speed exceeds threshold.
     */
    private function checkSpeedExceeded(PlannedTrip $trip, GpsTrackingLog $latestGps): bool
    {
        if ($latestGps->speed && $latestGps->speed > self::SPEED_EXCEEDED_THRESHOLD) {
            return $this->createAlert(
                $trip,
                GpsAlert::TYPE_SPEED_EXCEEDED,
                "Kecepatan melebihi batas: " . round($latestGps->speed, 1) . " km/h (maks: " . self::SPEED_EXCEEDED_THRESHOLD . " km/h)",
                [
                    'current_lat' => $latestGps->latitude,
                    'current_lng' => $latestGps->longitude,
                    'speed' => $latestGps->speed,
                    'threshold' => self::SPEED_EXCEEDED_THRESHOLD,
                ]
            );
        }

        return false;
    }

    /**
     * Check if the vehicle has arrived at its depot (geofence).
     */
    private function checkArrivedAtDepot(PlannedTrip $trip, GpsTrackingLog $latestGps): bool
    {
        if (!$trip->bus || !$trip->bus->depot_id) {
            return false;
        }

        $depot = FleetDepot::find($trip->bus->depot_id);
        if (!$depot || !$depot->latitude || !$depot->longitude) {
            return false;
        }

        $radiusKm = ($depot->geofence_radius ?? 500) / 1000;
        $distance = $this->calculateDistance(
            $latestGps->latitude, $latestGps->longitude,
            $depot->latitude, $depot->longitude
        );

        if ($distance <= $radiusKm) {
            $existingAlert = GpsAlert::where('planned_trip_id', $trip->id)
                ->where('alert_type', GpsAlert::TYPE_ARRIVED_AT_DEPOT)
                ->where('created_at', '>=', now()->subHours(1))
                ->exists();

            if ($existingAlert) {
                return false;
            }

            return $this->createAlert(
                $trip,
                GpsAlert::TYPE_ARRIVED_AT_DEPOT,
                "Bus tiba di pool {$depot->name} (jarak " . round($distance * 1000) . "m)",
                [
                    'depot_id' => $depot->id,
                    'depot_name' => $depot->name,
                    'depot_lat' => $depot->latitude,
                    'depot_lng' => $depot->longitude,
                    'bus_lat' => $latestGps->latitude,
                    'bus_lng' => $latestGps->longitude,
                    'distance_meters' => round($distance * 1000),
                ]
            );
        }

        return false;
    }

    /**
     * Create an alert if not already exists for this trip and type.
     */
    private function createAlert(PlannedTrip $trip, string $type, string $message, array $metadata): bool
    {
        // Check if similar alert already exists (not dismissed, within last 5 minutes)
        $existingAlert = GpsAlert::where('planned_trip_id', $trip->id)
            ->where('alert_type', $type)
            ->where('dismissed', false)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($existingAlert) {
            return false;
        }

        GpsAlert::create([
            'driver_id' => $trip->driver_id,
            'planned_trip_id' => $trip->id,
            'alert_type' => $type,
            'message' => $message,
            'metadata' => $metadata,
        ]);

        // Broadcast alert via WebSocket
        try {
            broadcast(new \App\Events\GpsAlertCreated($trip->id, $message, $type, $metadata));
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast GPS alert', ['error' => $e->getMessage()]);
        }

        return true;
    }

    /**
     * Calculate distance between two coordinates (Haversine formula).
     *
     * @return float Distance in kilometers
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
