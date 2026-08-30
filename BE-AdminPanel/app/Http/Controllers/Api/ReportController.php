<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Reservation;
use App\Models\GpsAlert;
use App\Models\Complaint;
use App\Models\User;
use App\Models\Route;
use App\Models\PlannedTrip;

class ReportController extends Controller
{
    public function driverAnalytics(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'route_id' => 'nullable|integer',
        ]);

        $startDate = $request->start_date ?? $request->startDate ?? date('Y-m-01');
        $endDate = $request->end_date ?? $request->endDate ?? date('Y-m-t');

        $drivers = User::where('role', 2)->where('status_id', 1)->get();

        $periodStart = new \DateTime($startDate);
        $periodEnd = new \DateTime($endDate);
        $totalDays = max(1, $periodStart->diff($periodEnd)->days + 1);
        $totalWeeks = max(1, $totalDays / 7);

        // === EARNINGS DATA ===
        $earningsData = Reservation::where('ride_status', 1)
            ->whereHas('plannedTrip', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('planned_date', [$startDate, $endDate]);
            })
            ->when($request->route_id, function ($q) use ($request) {
                $q->whereHas('plannedTrip', function ($q2) use ($request) {
                    $q2->where('route_id', $request->route_id);
                });
            })
            ->select(DB::raw('ANY_VALUE(planned_trip_id) as planned_trip_id'), DB::raw('SUM(driver_share) as total_earnings'), DB::raw('COUNT(*) as trip_count'))
            ->with('plannedTrip:id,driver_id')
            ->get()
            ->groupBy(function ($item) {
                return $item->plannedTrip ? $item->plannedTrip->driver_id : null;
            });

        $driverEarnings = $drivers->map(function ($driver) use ($earningsData) {
            $data = $earningsData->get($driver->id);
            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'avatar' => $driver->avatar,
                'total_earnings' => $data ? round($data->sum('total_earnings'), 2) : 0,
                'trip_count' => $data ? $data->sum('trip_count') : 0,
            ];
        })->sortByDesc('total_earnings')->values();

        // === TRIP PERFORMANCE DATA ===
        $tripStats = PlannedTrip::whereBetween('planned_date', [$startDate, $endDate])
            ->when($request->route_id, function ($q) use ($request) {
                $q->where('route_id', $request->route_id);
            })
            ->whereNotNull('driver_id')
            ->with(['reservations:id,planned_trip_id,ride_status', 'bus:id,capacity'])
            ->get()
            ->groupBy('driver_id');

        $driverTrips = $drivers->map(function ($driver) use ($tripStats) {
            $trips = $tripStats->get($driver->id);
            $totalTrips = $trips ? $trips->count() : 0;
            $completedTrips = 0;
            $totalReservations = 0;
            $totalSeats = 0;
            $totalCapacity = 0;
            $totalDurationMinutes = 0;
            $tripsWithDuration = 0;

            if ($trips) {
                foreach ($trips as $trip) {
                    $completedReservations = $trip->reservations->where('ride_status', 1)->count();
                    if ($completedReservations > 0) {
                        $completedTrips++;
                    }
                    $totalReservations += $completedReservations;
                    $totalSeats += $trip->reserved_seats ?? 0;

                    if ($trip->bus) {
                        $totalCapacity += $trip->bus->capacity ?? 0;
                    }

                    if ($trip->started_at && $trip->ended_at) {
                        $start = new \DateTime($trip->started_at);
                        $end = new \DateTime($trip->ended_at);
                        $totalDurationMinutes += $start->diff($end)->i + ($start->diff($end)->h * 60);
                        $tripsWithDuration++;
                    }
                }
            }

            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'total_trips' => $totalTrips,
                'completed_trips' => $completedTrips,
                'completion_rate' => $totalTrips > 0 ? round(($completedTrips / $totalTrips) * 100, 1) : 0,
                'total_passengers' => $totalReservations,
                'total_seats' => $totalSeats,
            ];
        })->sortByDesc('total_trips')->values();

        // === SAFETY DATA ===
        $alertStats = GpsAlert::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('driver_id', 'alert_type', DB::raw('COUNT(*) as count'))
            ->groupBy('driver_id', 'alert_type')
            ->get()
            ->groupBy('driver_id');

        $complaintStats = Complaint::whereHas('reservation.plannedTrip', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('planned_date', [$startDate, $endDate]);
        })
            ->select(DB::raw('ANY_VALUE(reservation_id) as reservation_id'), DB::raw('COUNT(*) as count'))
            ->with('reservation:id,planned_trip_id')
            ->get();

        $complaintByDriver = collect();
        foreach ($complaintStats as $c) {
            if ($c->reservation && $c->reservation->plannedTrip) {
                $driverId = $c->reservation->plannedTrip->driver_id;
                $complaintByDriver[$driverId] = ($complaintByDriver[$driverId] ?? 0) + 1;
            }
        }

        $driverSafety = $drivers->map(function ($driver) use ($alertStats, $complaintByDriver) {
            $alerts = $alertStats->get($driver->id);
            $speeding = 0;
            $offline = 0;
            $outOfRoute = 0;

            if ($alerts) {
                foreach ($alerts as $alert) {
                    switch ($alert->alert_type) {
                        case 'speed_exceeded': $speeding = $alert->count; break;
                        case 'gps_offline': $offline = $alert->count; break;
                        case 'out_of_route': $outOfRoute = $alert->count; break;
                    }
                }
            }

            $totalAlerts = $speeding + $offline + $outOfRoute;
            $complaints = $complaintByDriver[$driver->id] ?? 0;

            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'speeding' => $speeding,
                'offline' => $offline,
                'out_of_route' => $outOfRoute,
                'total_alerts' => $totalAlerts,
                'complaints' => $complaints,
                'safety_score' => max(0, 100 - ($totalAlerts * 2) - ($complaints * 5)),
            ];
        })->sortBy('safety_score')->values();

        // === DAILY EARNINGS CHART ===
        $dailyEarnings = Reservation::where('ride_status', 1)
            ->whereHas('plannedTrip', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('planned_date', [$startDate, $endDate]);
            })
            ->select('id', 'planned_trip_id', 'driver_share')
            ->with('plannedTrip:id,planned_date')
            ->get()
            ->groupBy(function ($item) {
                return $item->plannedTrip->planned_date ?? 'unknown';
            })
            ->map(function ($group) {
                return round($group->sum('driver_share'), 2);
            });

        // === ROUTE BREAKDOWN ===
        $routeBreakdown = Reservation::where('ride_status', 1)
            ->whereHas('plannedTrip', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('planned_date', [$startDate, $endDate]);
            })
            ->select('id', 'driver_share')
            ->with('plannedTrip:id,route_id')
            ->get()
            ->groupBy('plannedTrip.route_id');

        $routeNames = Route::whereIn('id', $routeBreakdown->keys()->filter()->toArray())
            ->pluck('name', 'id');

        $routeData = $routeBreakdown->map(function ($group, $routeId) use ($routeNames) {
            return [
                'route' => $routeNames[$routeId] ?? 'Unknown',
                'total_earnings' => round($group->sum('driver_share'), 2),
                'count' => $group->count(),
            ];
        })->values();

        // === NEW METRICS ===

        // 1. On-Time Performance (trips started within 5 minutes of planned time)
        $onTimeData = PlannedTrip::whereBetween('planned_date', [$startDate, $endDate])
            ->when($request->route_id, function ($q) use ($request) {
                $q->where('route_id', $request->route_id);
            })
            ->whereNotNull('driver_id')
            ->whereNotNull('started_at')
            ->with('trip:id,first_stop_time')
            ->get()
            ->groupBy('driver_id');

        $driverOnTime = $drivers->map(function ($driver) use ($onTimeData) {
            $trips = $onTimeData->get($driver->id, collect());
            $onTimeTrips = 0;
            $totalTripsWithTime = 0;

            foreach ($trips as $trip) {
                if ($trip->trip && $trip->started_at) {
                    $totalTripsWithTime++;
                    $plannedStart = $trip->trip->first_stop_time ?? null;
                    if ($plannedStart) {
                        $plannedDateTime = new \DateTime($trip->planned_date . ' ' . $plannedStart);
                        $actualStart = new \DateTime($trip->started_at);
                        $diffMinutes = $plannedDateTime->diff($actualStart)->i + ($plannedDateTime->diff($actualStart)->h * 60);
                        if ($diffMinutes <= 5) {
                            $onTimeTrips++;
                        }
                    }
                }
            }

            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'on_time_trips' => $onTimeTrips,
                'total_trips_with_time' => $totalTripsWithTime,
                'on_time_percentage' => $totalTripsWithTime > 0 ? round(($onTimeTrips / $totalTripsWithTime) * 100, 1) : 0,
            ];
        })->sortByDesc('on_time_percentage')->values();

        // 2. Average Trip Duration
        $durationData = PlannedTrip::whereBetween('planned_date', [$startDate, $endDate])
            ->when($request->route_id, function ($q) use ($request) {
                $q->where('route_id', $request->route_id);
            })
            ->whereNotNull('driver_id')
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->select('driver_id', 'started_at', 'ended_at')
            ->get()
            ->groupBy('driver_id');

        $driverDuration = $drivers->map(function ($driver) use ($durationData) {
            $trips = $durationData->get($driver->id, collect());
            $totalMinutes = 0;
            $tripCount = 0;

            foreach ($trips as $trip) {
                $start = new \DateTime($trip->started_at);
                $end = new \DateTime($trip->ended_at);
                $totalMinutes += $start->diff($end)->i + ($start->diff($end)->h * 60);
                $tripCount++;
            }

            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'avg_duration_minutes' => $tripCount > 0 ? round($totalMinutes / $tripCount, 1) : 0,
                'total_duration_hours' => round($totalMinutes / 60, 1),
                'trips_with_duration' => $tripCount,
            ];
        })->sortBy('avg_duration_minutes')->values();

        // 3. Utilization Rate
        $utilizationData = PlannedTrip::whereBetween('planned_date', [$startDate, $endDate])
            ->when($request->route_id, function ($q) use ($request) {
                $q->where('route_id', $request->route_id);
            })
            ->whereNotNull('driver_id')
            ->with('bus:id,capacity')
            ->get()
            ->groupBy('driver_id');

        $driverUtilization = $drivers->map(function ($driver) use ($utilizationData) {
            $trips = $utilizationData->get($driver->id, collect());
            $totalReserved = 0;
            $totalCapacity = 0;
            $tripCount = 0;

            foreach ($trips as $trip) {
                if ($trip->bus && $trip->bus->capacity > 0) {
                    $totalReserved += $trip->reserved_seats ?? 0;
                    $totalCapacity += $trip->bus->capacity;
                    $tripCount++;
                }
            }

            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'avg_utilization' => $totalCapacity > 0 ? round(($totalReserved / $totalCapacity) * 100, 1) : 0,
                'total_passengers' => $totalReserved,
                'total_capacity' => $totalCapacity,
                'trips_measured' => $tripCount,
            ];
        })->sortByDesc('avg_utilization')->values();

        // 4. Revenue Per KM (estimated based on average route distance)
        $revenuePerKm = $drivers->map(function ($driver) use ($driverEarnings, $driverTrips) {
            $earnings = $driverEarnings->firstWhere('id', $driver->id);
            $trips = $driverTrips->firstWhere('id', $driver->id);

            $totalEarnings = $earnings['total_earnings'] ?? 0;
            $totalTrips = $trips['total_trips'] ?? 0;

            $estimatedDistanceKm = $totalTrips * 25;

            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'total_earnings' => $totalEarnings,
                'estimated_distance_km' => $estimatedDistanceKm,
                'revenue_per_km' => $estimatedDistanceKm > 0 ? round($totalEarnings / $estimatedDistanceKm, 2) : 0,
            ];
        })->sortByDesc('revenue_per_km')->values();

        // 5. Fuel Efficiency (estimated cost per trip)
        $fuelEfficiency = $drivers->map(function ($driver) use ($driverEarnings, $driverTrips) {
            $earnings = $driverEarnings->firstWhere('id', $driver->id);
            $trips = $driverTrips->firstWhere('id', $driver->id);

            $totalEarnings = $earnings['total_earnings'] ?? 0;
            $totalTrips = $trips['total_trips'] ?? 0;

            $estimatedFuelCostPerTrip = $totalTrips > 0 ? round($totalEarnings * 0.3 / $totalTrips, 2) : 0;

            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'total_trips' => $totalTrips,
                'estimated_fuel_cost_per_trip' => $estimatedFuelCostPerTrip,
                'fuel_efficiency_score' => $totalTrips > 0 ? min(100, max(0, round(100 - ($estimatedFuelCostPerTrip / 50000 * 100), 1))) : 0,
            ];
        })->sortByDesc('fuel_efficiency_score')->values();

        // 6. Trip Frequency (average trips per week)
        $tripFrequency = $drivers->map(function ($driver) use ($driverTrips, $totalWeeks) {
            $trips = $driverTrips->firstWhere('id', $driver->id);
            $totalTrips = $trips['total_trips'] ?? 0;

            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'total_trips' => $totalTrips,
                'trips_per_week' => round($totalTrips / $totalWeeks, 1),
                'trips_per_day' => round($totalTrips / max(1, $totalWeeks * 7), 2),
            ];
        })->sortByDesc('trips_per_week')->values();

        // 7. Overall Performance Score (composite)
        $driverPerformance = $drivers->map(function ($driver) use ($driverEarnings, $driverTrips, $driverSafety, $driverOnTime, $driverUtilization) {
            $earnings = $driverEarnings->firstWhere('id', $driver->id);
            $trips = $driverTrips->firstWhere('id', $driver->id);
            $safety = $driverSafety->firstWhere('id', $driver->id);
            $onTime = $driverOnTime->firstWhere('id', $driver->id);
            $utilization = $driverUtilization->firstWhere('id', $driver->id);

            $completionRate = $trips['completion_rate'] ?? 0;
            $safetyScore = $safety['safety_score'] ?? 100;
            $onTimeScore = $onTime['on_time_percentage'] ?? 0;
            $utilizationScore = $utilization['avg_utilization'] ?? 0;

            $overallScore = round(
                ($completionRate * 0.25) +
                ($safetyScore * 0.30) +
                ($onTimeScore * 0.25) +
                ($utilizationScore * 0.20),
                1
            );

            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'completion_rate' => $completionRate,
                'safety_score' => $safetyScore,
                'on_time_score' => $onTimeScore,
                'utilization_score' => $utilizationScore,
                'overall_score' => $overallScore,
            ];
        })->sortByDesc('overall_score')->values();

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $totalDays,
                'total_weeks' => round($totalWeeks, 1),
            ],
            'driver_earnings' => $driverEarnings,
            'driver_trips' => $driverTrips,
            'driver_safety' => $driverSafety,
            'daily_earnings' => $dailyEarnings,
            'route_breakdown' => $routeData,
            'driver_on_time' => $driverOnTime,
            'driver_duration' => $driverDuration,
            'driver_utilization' => $driverUtilization,
            'driver_revenue_per_km' => $revenuePerKm,
            'driver_fuel_efficiency' => $fuelEfficiency,
            'driver_trip_frequency' => $tripFrequency,
            'driver_performance' => $driverPerformance,
        ], 200);
    }

    public function exportDriverAnalytics(Request $request)
    {
        $data = $this->driverAnalytics($request)->getData();

        $csvContent = "Driver Name,Total Earnings,Trip Count,Completion Rate,Safety Score,On-Time %,Avg Duration (min),Utilization %,Revenue/KM,Trips/Week,Overall Score\n";

        $earningsMap = collect($data->driver_earnings)->keyBy('id');
        $tripsMap = collect($data->driver_trips)->keyBy('id');
        $safetyMap = collect($data->driver_safety)->keyBy('id');
        $onTimeMap = collect($data->driver_on_time)->keyBy('id');
        $durationMap = collect($data->driver_duration)->keyBy('id');
        $utilizationMap = collect($data->driver_utilization)->keyBy('id');
        $revenuePerKmMap = collect($data->driver_revenue_per_km)->keyBy('id');
        $frequencyMap = collect($data->driver_trip_frequency)->keyBy('id');
        $performanceMap = collect($data->driver_performance)->keyBy('id');

        $allDriverIds = collect([
            $earningsMap->keys(),
            $tripsMap->keys(),
            $safetyMap->keys(),
        ])->flatten()->unique();

        foreach ($allDriverIds as $driverId) {
            $e = $earningsMap[$driverId] ?? null;
            $t = $tripsMap[$driverId] ?? null;
            $s = $safetyMap[$driverId] ?? null;
            $ot = $onTimeMap[$driverId] ?? null;
            $d = $durationMap[$driverId] ?? null;
            $u = $utilizationMap[$driverId] ?? null;
            $rpk = $revenuePerKmMap[$driverId] ?? null;
            $f = $frequencyMap[$driverId] ?? null;
            $p = $performanceMap[$driverId] ?? null;

            $csvContent .= sprintf(
                '"%s",%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n',
                $e['name'] ?? $t['name'] ?? $s['name'] ?? 'Unknown',
                $e['total_earnings'] ?? 0,
                $t['total_trips'] ?? 0,
                ($t['completion_rate'] ?? 0) . '%',
                $s['safety_score'] ?? 100,
                ($ot['on_time_percentage'] ?? 0) . '%',
                $d['avg_duration_minutes'] ?? 0,
                ($u['avg_utilization'] ?? 0) . '%',
                $rpk['revenue_per_km'] ?? 0,
                $f['trips_per_week'] ?? 0,
                $p['overall_score'] ?? 0
            );
        }

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="driver-analytics-' . $data->period->start_date . '-to-' . $data->period->end_date . '.csv"',
        ]);
    }
}
