<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Reservation;
use App\Models\CharterBooking;
use App\Models\FleetDepot;

class CustomerLocationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:all,pending,active,completed',
            'type' => 'nullable|in:all,regular,charter',
        ]);

        $status = $request->get('status', 'all');
        $type = $request->get('type', 'all');

        $locations = collect();

        // Regular bookings with pickup coordinates
        if ($type === 'all' || $type === 'regular') {
            $regularBookings = Reservation::whereNotNull('pickup_lat')
                ->whereNotNull('pickup_lng')
                ->with(['customer:id,name,tel_number', 'plannedTrip:id,planned_date,driver_id,bus_id', 'plannedTrip.route:id,name', 'firstStop:id,name'])
                ->when($status !== 'all', function ($q) use ($status) {
                    if ($status === 'pending') {
                        $q->where('ride_status', 0);
                    } elseif ($status === 'active') {
                        $q->where('ride_status', 1);
                    } elseif ($status === 'completed') {
                        $q->whereIn('ride_status', [1, 3]);
                    }
                })
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'type' => 'regular',
                        'customer_name' => $r->customer->name ?? '-',
                        'customer_phone' => $r->customer->tel_number ?? '-',
                        'pickup_lat' => $r->pickup_lat,
                        'pickup_lng' => $r->pickup_lng,
                        'pickup_address' => $r->start_address ?? '-',
                        'destination' => $r->destination_address ?? '-',
                        'route_name' => $r->plannedTrip->route->name ?? '-',
                        'planned_date' => $r->plannedTrip->planned_date ?? '-',
                        'ride_status' => $r->ride_status,
                        'ticket_number' => $r->ticket_number,
                        'trip_price' => $r->trip_price,
                        'created_at' => $r->created_at,
                    ];
                });

            $locations = $locations->merge($regularBookings);
        }

        // Charter bookings with pickup coordinates
        if ($type === 'all' || $type === 'charter') {
            $charterBookings = CharterBooking::whereNotNull('pickup_lat')
                ->whereNotNull('pickup_lng')
                ->with(['customer:id,name,tel_number', 'busType:id,name', 'depot:id,name', 'driver:id,name'])
                ->when($status !== 'all', function ($q) use ($status) {
                    if ($status === 'pending') {
                        $q->where('status', 'waiting_quote');
                    } elseif ($status === 'active') {
                        $q->whereIn('status', ['confirmed', 'assigned', 'in_progress']);
                    } elseif ($status === 'completed') {
                        $q->where('status', 'completed');
                    }
                })
                ->get()
                ->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'type' => 'charter',
                        'customer_name' => $c->customer->name ?? '-',
                        'customer_phone' => $c->customer->tel_number ?? '-',
                        'pickup_lat' => $c->pickup_lat,
                        'pickup_lng' => $c->pickup_lng,
                        'pickup_address' => $c->origin ?? '-',
                        'destination' => $c->destination ?? '-',
                        'dropoff_lat' => $c->dropoff_lat,
                        'dropoff_lng' => $c->dropoff_lng,
                        'bus_type' => $c->busType->name ?? '-',
                        'passenger_count' => $c->passenger_count,
                        'departure_date' => $c->departure_date,
                        'departure_time' => $c->departure_time,
                        'status' => $c->status,
                        'reference_code' => $c->reference_code,
                        'quoted_price' => $c->quoted_price,
                        'depot_name' => $c->depot->name ?? '-',
                        'driver_name' => $c->driver->name ?? '-',
                        'created_at' => $c->created_at,
                    ];
                });

            $locations = $locations->merge($charterBookings);
        }

        return response()->json([
            'locations' => $locations->values(),
            'total' => $locations->count(),
        ], 200);
    }

    public function count()
    {
        $regularCount = Reservation::whereNotNull('pickup_lat')
            ->whereNotNull('pickup_lng')
            ->where('ride_status', 0)
            ->count();

        $charterCount = CharterBooking::whereNotNull('pickup_lat')
            ->whereNotNull('pickup_lng')
            ->whereIn('status', ['confirmed', 'assigned', 'in_progress'])
            ->count();

        return response()->json([
            'total' => $regularCount + $charterCount,
        ]);
    }

    public function nearbyDepots(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        $lat = $request->lat;
        $lng = $request->lng;
        $limit = $request->get('limit', 5);

        $depots = FleetDepot::where('is_active', true)
            ->select('*', DB::raw("
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )) AS distance_km
            "), [$lat, $lng, $lat])
            ->orderBy('distance_km')
            ->limit($limit)
            ->get();

        return response()->json([
            'depots' => $depots,
        ], 200);
    }
}
