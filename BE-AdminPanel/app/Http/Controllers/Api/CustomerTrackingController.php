<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CharterBooking;
use App\Models\PlannedTrip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerTrackingController extends Controller
{
    public function getActiveBookings(Request $request)
    {
        $customerId = $request->user()->id;

        $bookings = CharterBooking::where('customer_id', $customerId)
            ->whereIn('status', ['approved'])
            ->where('payment_status', 'paid')
            ->whereNotNull('operational_planned_trip_id')
            ->with([
                'bus:id,license,fleet_number,current_lat,current_lng,current_speed,last_gps_at,gps_source',
                'driver:id,name',
                'busType:id,name',
                'assignments.bus:id,license,fleet_number,current_lat,current_lng,current_speed,last_gps_at,gps_source',
                'assignments.driver:id,name',
                'operationalTrip:id,started_at,ended_at,last_position_lat,last_position_lng,channel,trip_id',
                'operationalTrip.trip:id,route_id',
                'operationalTrip.trip.route:id,name',
            ])
            ->latest()
            ->get()
            ->filter(function ($booking) {
                $trip = $booking->operationalTrip;
                return $trip && $trip->started_at && !$trip->ended_at;
            })
            ->values();

        $result = $bookings->map(function ($booking) {
            return $this->formatBookingData($booking);
        });

        return response()->json(['bookings' => $result]);
    }

    public function getTracking(Request $request, $bookingId)
    {
        $customerId = $request->user()->id;

        $booking = CharterBooking::where('id', $bookingId)
            ->where('customer_id', $customerId)
            ->with([
                'bus:id,license,fleet_number,current_lat,current_lng,current_speed,last_gps_at,gps_source',
                'driver:id,name,tel_number',
                'busType:id,name',
                'assignments.bus:id,license,fleet_number,current_lat,current_lng,current_speed,last_gps_at,gps_source',
                'assignments.driver:id,name,tel_number',
                'operationalTrip:id,started_at,ended_at,last_position_lat,last_position_lng,channel,trip_id',
                'operationalTrip.trip:id,route_id',
                'operationalTrip.trip.route:id,name',
            ])
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $trip = $booking->operationalTrip;
        if (!$trip || !$trip->started_at || $trip->ended_at) {
            return response()->json([
                'booking' => $this->formatBookingData($booking),
                'tracking' => null,
                'message' => 'Perjalanan belum dimulai atau sudah selesai.',
            ]);
        }

        $position = $this->getBusPosition($booking);
        $eta = null;
        $distanceToDestination = null;

        if ($position && $booking->dropoff_lat && $booking->dropoff_lng) {
            $distanceToDestination = $this->calculateDistance(
                $position['lat'], $position['lng'],
                $booking->dropoff_lat, $booking->dropoff_lng
            );
            $eta = $this->calculateEta($distanceToDestination, $position['speed'] ?? null);
        }

        return response()->json([
            'booking' => $this->formatBookingData($booking),
            'tracking' => $position ? [
                'lat' => $position['lat'],
                'lng' => $position['lng'],
                'speed' => $position['speed'] ?? null,
                'heading' => $position['heading'] ?? null,
                'last_gps_at' => $position['last_gps_at'] ?? null,
                'gps_source' => $position['gps_source'] ?? null,
            ] : null,
            'destination' => $booking->dropoff_lat && $booking->dropoff_lng ? [
                'lat' => $booking->dropoff_lat,
                'lng' => $booking->dropoff_lng,
                'address' => $booking->destination,
            ] : null,
            'distance_to_destination' => $distanceToDestination,
            'eta' => $eta,
            'route' => $trip->trip && $trip->trip->route ? [
                'id' => $trip->trip->route->id,
                'name' => $trip->trip->route->name,
            ] : null,
        ]);
    }

    private function getBusPosition($booking)
    {
        $trip = $booking->operationalTrip;

        if ($trip && $trip->last_position_lat && $trip->last_position_lng) {
            return [
                'lat' => (float) $trip->last_position_lat,
                'lng' => (float) $trip->last_position_lng,
                'speed' => null,
                'heading' => null,
                'last_gps_at' => $trip->updated_at,
                'gps_source' => null,
            ];
        }

        if ($booking->bus && $booking->bus->current_lat && $booking->bus->current_lng) {
            return [
                'lat' => (float) $booking->bus->current_lat,
                'lng' => (float) $booking->bus->current_lng,
                'speed' => $booking->bus->current_speed ? (float) $booking->bus->current_speed : null,
                'heading' => null,
                'last_gps_at' => $booking->bus->last_gps_at,
                'gps_source' => $booking->bus->gps_source,
            ];
        }

        if ($booking->assignments) {
            foreach ($booking->assignments as $assignment) {
                if ($assignment->bus && $assignment->bus->current_lat && $assignment->bus->current_lng) {
                    return [
                        'lat' => (float) $assignment->bus->current_lat,
                        'lng' => (float) $assignment->bus->current_lng,
                        'speed' => $assignment->bus->current_speed ? (float) $assignment->bus->current_speed : null,
                        'heading' => null,
                        'last_gps_at' => $assignment->bus->last_gps_at,
                        'gps_source' => $assignment->bus->gps_source,
                    ];
                }
            }
        }

        return null;
    }

    private function formatBookingData($booking)
    {
        $busInfo = null;
        $driverInfo = null;

        if ($booking->assignments && $booking->assignments->count() > 0) {
            $firstAssignment = $booking->assignments->first();
            $busInfo = $firstAssignment->bus ? [
                'id' => $firstAssignment->bus->id,
                'license' => $firstAssignment->bus->license,
                'fleet_number' => $firstAssignment->bus->fleet_number,
            ] : null;
            $driverInfo = $firstAssignment->driver ? [
                'id' => $firstAssignment->driver->id,
                'name' => $firstAssignment->driver->name,
            ] : null;
        }

        if (!$busInfo && $booking->bus) {
            $busInfo = [
                'id' => $booking->bus->id,
                'license' => $booking->bus->license,
                'fleet_number' => $booking->bus->fleet_number,
            ];
        }

        if (!$driverInfo && $booking->driver) {
            $driverInfo = [
                'id' => $booking->driver->id,
                'name' => $booking->driver->name,
            ];
        }

        return [
            'id' => $booking->id,
            'reference_code' => $booking->reference_code,
            'origin' => $booking->origin,
            'destination' => $booking->destination,
            'departure_date' => $booking->departure_date?->format('Y-m-d'),
            'departure_time' => $booking->departure_time,
            'passenger_count' => $booking->passenger_count,
            'status' => $booking->status,
            'bus' => $busInfo,
            'driver' => $driverInfo,
            'bus_type' => $booking->busType ? $booking->busType->name : $booking->bus_type,
            'pickup_lat' => $booking->pickup_lat,
            'pickup_lng' => $booking->pickup_lng,
            'dropoff_lat' => $booking->dropoff_lat,
            'dropoff_lng' => $booking->dropoff_lng,
            'trip_status' => $booking->operationalTrip ? ($booking->operationalTrip->ended_at ? 'completed' : 'active') : 'pending',
        ];
    }

    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private function calculateEta($distanceKm, $speedKmh)
    {
        if ($speedKmh && $speedKmh > 5) {
            $hours = $distanceKm / $speedKmh;
        } else {
            $hours = $distanceKm / 40;
        }

        $minutes = max(1, round($hours * 60));

        return [
            'minutes' => $minutes,
            'label' => $minutes < 60
                ? "± {$minutes} menit"
                : "± " . floor($minutes / 60) . " jam " . ($minutes % 60) . " mnt",
            'distance_km' => round($distanceKm, 1),
        ];
    }
}
