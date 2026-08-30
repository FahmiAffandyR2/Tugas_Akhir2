<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GpsTrackingLog;
use App\Models\GpsAlert;
use App\Models\PlannedTrip;
use App\Models\Route;
use App\Models\User;
use App\Services\GoogleRoutesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    /**
     * Get GPS tracking logs with filters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getTrackingLogs(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'driver_id' => 'nullable|integer|exists:users,id',
            'planned_trip_id' => 'nullable|integer|exists:planned_trips,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = GpsTrackingLog::with(['driver', 'bus', 'plannedTrip.route']);

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->where('recorded_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('recorded_at', '<=', $request->end_date . ' 23:59:59');
        }

        // Filter by driver
        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        // Filter by planned trip
        if ($request->filled('planned_trip_id')) {
            $query->where('planned_trip_id', $request->planned_trip_id);
        }

        $perPage = $request->get('per_page', 50);
        $logs = $query->orderBy('recorded_at', 'asc')->paginate($perPage);

        return response()->json($logs);
    }

    /**
     * Get GPS tracking logs for playback (all at once, no pagination).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getTrackingLogsForPlayback(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'driver_id' => 'nullable|integer|exists:users,id',
            'planned_trip_id' => 'required|integer|exists:planned_trips,id',
        ]);

        $query = GpsTrackingLog::with(['driver', 'bus'])
            ->where('planned_trip_id', $request->planned_trip_id);

        if ($request->filled('start_date')) {
            $query->where('recorded_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('recorded_at', '<=', $request->end_date . ' 23:59:59');
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        $logs = $query->orderBy('recorded_at', 'asc')->get();

        // Get trip summary
        $trip = PlannedTrip::with(['driver', 'bus', 'route', 'plannedTripDetail.stop'])
            ->find($request->planned_trip_id);

        return response()->json([
            'logs' => $logs,
            'trip' => $trip,
            'summary' => [
                'total_points' => $logs->count(),
                'start_time' => $logs->first()?->recorded_at,
                'end_time' => $logs->last()?->recorded_at,
                'total_distance_km' => $this->calculateTotalDistance($logs),
                'max_speed' => $logs->max('speed'),
                'avg_speed' => $logs->avg('speed'),
            ],
        ]);
    }

    /**
     * Export GPS tracking logs to CSV.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function exportTrackingLogs(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'driver_id' => 'nullable|integer|exists:users,id',
            'planned_trip_id' => 'nullable|integer|exists:planned_trips,id',
        ]);

        $query = GpsTrackingLog::with(['driver', 'bus', 'plannedTrip.route']);

        if ($request->filled('start_date')) {
            $query->where('recorded_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('recorded_at', '<=', $request->end_date . ' 23:59:59');
        }
        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }
        if ($request->filled('planned_trip_id')) {
            $query->where('planned_trip_id', $request->planned_trip_id);
        }

        $logs = $query->orderBy('recorded_at', 'asc')->get();

        $filename = 'gps_tracking_logs_' . now()->format('Y-m-d_His');

        return $this->exportToCsv($logs, $filename);
    }

    /**
     * Export to CSV.
     */
    private function exportToCsv($logs, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');

            // Header
            fputcsv($file, [
                'Tanggal',
                'Waktu',
                'Driver ID',
                'Nama Driver',
                'Bus',
                'Rute',
                'Latitude',
                'Longitude',
                'Kecepatan (km/h)',
                'Akurasi (m)',
            ]);

            // Data
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->recorded_at->format('Y-m-d'),
                    $log->recorded_at->format('H:i:s'),
                    $log->driver_id,
                    $log->driver->name ?? '-',
                    $log->bus->license ?? '-',
                    $log->plannedTrip->route->name ?? '-',
                    $log->latitude,
                    $log->longitude,
                    $log->speed,
                    $log->accuracy,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get active GPS alerts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getGpsAlerts(Request $request)
    {
        $query = GpsAlert::with(['driver', 'plannedTrip'])
            ->active()
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->filled('alert_type')) {
            $query->byType($request->alert_type);
        }

        $alerts = $query->get();

        return response()->json($alerts);
    }

    /**
     * Get all GPS alerts (including dismissed).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getGpsAlertsLog(Request $request)
    {
        $query = GpsAlert::with(['driver', 'plannedTrip'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('alert_type')) {
            $query->byType($request->alert_type);
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        $alerts = $query->paginate(50);

        return response()->json($alerts);
    }

    /**
     * Dismiss a GPS alert.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function dismissAlert($id)
    {
        $alert = GpsAlert::findOrFail($id);
        $alert->dismiss();

        return response()->json(['success' => true, 'message' => 'Alert dismissed']);
    }

    /**
     * Get route path for comparison.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getRoutePath($id)
    {
        $route = Route::with(['stops' => function ($q) {
            $q->orderBy('sequence');
        }])->find($id);

        if (!$route) {
            return response()->json(['message' => 'Route not found'], 404);
        }

        $stops = $route->stops;

        if ($stops->count() < 2) {
            return response()->json(['path' => []]);
        }

        $origin = [
            'latitude' => (float) $stops->first()->lat,
            'longitude' => (float) $stops->first()->lng,
        ];
        $destination = [
            'latitude' => (float) $stops->last()->lat,
            'longitude' => (float) $stops->last()->lng,
        ];
        $waypoints = $stops->slice(1, -1)->map(fn($stop) => [
            'location' => [
                'latLng' => [
                    'latitude' => (float) $stop->lat,
                    'longitude' => (float) $stop->lng,
                ]
            ]
        ])->toArray();

        try {
            $googleRoutesService = app(GoogleRoutesService::class);
            $result = $googleRoutesService->computeRoutes($origin, $destination, $waypoints);

            $path = [];
            if (isset($result['routes'][0]['polyline']['geoJsonLinestring']['coordinates'])) {
                $coordinates = $result['routes'][0]['polyline']['geoJsonLinestring']['coordinates'];
                $path = array_map(fn($coord) => [
                    'lat' => (float) $coord[1],
                    'lng' => (float) $coord[0],
                ], $coordinates);
            }

            return response()->json([
                'path' => $path,
                'stops' => $stops->map(fn($stop) => [
                    'id' => $stop->id,
                    'name' => $stop->name,
                    'lat' => (float) $stop->lat,
                    'lng' => (float) $stop->lng,
                ]),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to compute route path', ['error' => $e->getMessage()]);
            return response()->json(['path' => [], 'stops' => []]);
        }
    }

    /**
     * Get drivers with active trips (for filter dropdown).
     *
     * @return \Illuminate\Http\Response
     */
    public function getActiveDrivers()
    {
        $drivers = User::where('role', 2)
            ->whereHas('plannedTrips', function ($q) {
                $q->whereNull('ended_at')->whereNotNull('started_at');
            })
            ->with(['driverInformation'])
            ->get();

        return response()->json($drivers);
    }

    /**
     * Get trips for a specific driver (for filter dropdown).
     *
     * @param  int  $driverId
     * @return \Illuminate\Http\Response
     */
    public function getDriverTrips($driverId)
    {
        $trips = PlannedTrip::where('driver_id', $driverId)
            ->with(['route', 'bus'])
            ->orderBy('planned_date', 'desc')
            ->get();

        return response()->json($trips);
    }

    /**
     * Calculate total distance from GPS logs.
     */
    private function calculateTotalDistance($logs)
    {
        if ($logs->count() < 2) {
            return 0;
        }

        $totalDistance = 0;
        $previousLog = null;

        foreach ($logs as $log) {
            if ($previousLog) {
                $totalDistance += $this->distance(
                    $previousLog->latitude,
                    $previousLog->longitude,
                    $log->latitude,
                    $log->longitude
                );
            }
            $previousLog = $log;
        }

        return round($totalDistance, 2);
    }

    /**
     * Calculate distance between two coordinates (Haversine formula).
     *
     * @return float Distance in kilometers
     */
    private function distance($lat1, $lng1, $lat2, $lng2)
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
