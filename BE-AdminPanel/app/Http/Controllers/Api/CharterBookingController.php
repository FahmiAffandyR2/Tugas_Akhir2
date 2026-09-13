<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CharterBookingAssignment;
use App\Models\CharterBooking;
use App\Models\CharterRevenueTransaction;
use App\Models\Bus;
use App\Models\BusType;
use App\Models\Notification;
use App\Models\PlannedTrip;
use App\Models\ServiceArea;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CharterBookingController extends Controller
{
    private const STATUSES = [
        'waiting_quote', 'quote_sent', 'approved', 'rejected', 'cancelled', 'completed',
    ];

    public function adminCancel(Request $request, CharterBooking $charterBooking)
    {
        if (in_array($charterBooking->status, ['cancelled', 'completed'], true)) {
            return response()->json(['message' => 'Booking tidak dapat dibatalkan.'], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $charterBooking->update([
            'status' => 'cancelled',
            'admin_notes' => $validated['reason'] ?? $charterBooking->admin_notes,
        ]);

        $this->resetBusStatus($charterBooking);

        if ($charterBooking->operational_planned_trip_id) {
            $planned = PlannedTrip::find($charterBooking->operational_planned_trip_id);
            if ($planned && !$planned->started_at) {
                $planned->update(['status_id' => 0]);
            }
        }

        Notification::create([
            'user_id' => $charterBooking->customer_id,
            'message' => "Booking {$charterBooking->reference_code} dibatalkan oleh admin."
                . ($validated['reason'] ? " Alasan: {$validated['reason']}" : ''),
            'seen' => 0,
        ]);

        return response()->json([
            'message' => 'Booking berhasil dibatalkan.',
            'booking' => $this->freshBooking($charterBooking),
        ]);
    }

    public function customerIndex(Request $request)
    {
        return response()->json([
            'bookings' => CharterBooking::where('customer_id', $request->user()->id)
                ->with(['bus.depot', 'busType', 'originArea', 'destinationArea', 'driver:id,name,tel_number', 'assignments.bus.depot', 'assignments.driver:id,name,tel_number', 'operationalTrip:id,started_at,ended_at,last_position_lat,last_position_lng'])
                ->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'origin_area_id' => ['nullable', Rule::exists('service_areas', 'id')->where('is_active', true)],
            'destination_area_id' => ['nullable', Rule::exists('service_areas', 'id')->where('is_active', true)],
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'destinations' => 'sometimes|array|min:1|max:10',
            'destinations.*' => 'required|array:address,lat,lng',
            'destinations.*.address' => 'required|string|max:255',
            'destinations.*.lat' => 'nullable|required_with:destinations.*.lng|numeric|between:-90,90',
            'destinations.*.lng' => 'nullable|required_with:destinations.*.lat|numeric|between:-180,180',
            'trip_type' => ['required', Rule::in(['one_way', 'round_trip'])],
            'trip_style' => ['nullable', Rule::in(['day_trip', 'overnight'])],
            'departure_date' => 'required|date|after_or_equal:today',
            'departure_time' => 'required|date_format:H:i',
            'return_date' => 'nullable|required_if:trip_type,round_trip|date|after_or_equal:departure_date',
            'return_time' => 'nullable|required_if:trip_type,round_trip|date_format:H:i',
            'passenger_count' => 'nullable|required_without:requested_bus_count|integer|min:1|max:1000',
            'requested_bus_count' => 'nullable|integer|min:1|max:20',
            'bus_type_id' => ['required', Rule::exists('bus_types', 'id')->where('is_active', true)],
            'notes' => 'nullable|string|max:2000',
            'pickup_lat' => 'nullable|numeric|between:-90,90',
            'pickup_lng' => 'nullable|numeric|between:-180,180',
            'dropoff_lat' => 'nullable|numeric|between:-90,90',
            'dropoff_lng' => 'nullable|numeric|between:-180,180',
        ]);

        if (isset($validated['destinations'])) {
            $validated['destinations'] = array_values($validated['destinations']);
            $last = $validated['destinations'][count($validated['destinations']) - 1];
            $validated['destination'] = $last['address'];
            $validated['dropoff_lat'] = $last['lat'] ?? null;
            $validated['dropoff_lng'] = $last['lng'] ?? null;
        }

        if (!empty($validated['trip_style'])) {
            $validDates = $validated['trip_type'] === 'round_trip'
                && ($validated['trip_style'] === 'day_trip'
                    ? ($validated['return_date'] ?? null) === $validated['departure_date']
                    : ($validated['return_date'] ?? '') > $validated['departure_date']);
            if (!$validDates) {
                throw ValidationException::withMessages(['return_date' => 'Day Trip harus pulang di hari yang sama; Menginap harus pulang setelah tanggal berangkat.']);
            }
        }
        unset($validated['trip_style']);

        if (($validated['trip_type'] ?? null) === 'round_trip'
            && $validated['return_date'] === $validated['departure_date']
            && $validated['return_time'] <= $validated['departure_time']) {
            throw ValidationException::withMessages(['return_time' => 'Jam pulang harus setelah jam penjemputan.']);
        }

        $busType = BusType::findOrFail($validated['bus_type_id']);
        $wholeBusBooking = isset($validated['requested_bus_count']) && !isset($validated['passenger_count']);
        if ($wholeBusBooking) {
            // Reserve the full seating capacity without asking for a passenger headcount.
            $validated['passenger_count'] = max(1, (int) $busType->capacity) * $validated['requested_bus_count'];
        }
        $availability = $this->availabilityForBusType(
            $busType,
            (int) $validated['passenger_count'],
            $validated['departure_date'],
            $validated['return_date'] ?? $validated['departure_date']
        );

        if (!$availability['is_available']) {
            throw ValidationException::withMessages(['bus_type_id' => $availability['message']]);
        }

        $duplicate = CharterBooking::where('customer_id', $request->user()->id)
            ->where('departure_date', $validated['departure_date'])
            ->where('bus_type_id', $validated['bus_type_id'])
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed'])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'departure_date' => 'Anda sudah memiliki booking untuk tanggal dan tipe bus yang sama.',
            ]);
        }

        return DB::transaction(function () use ($request, $validated, $wholeBusBooking) {
            do {
                $reference = 'REQ-' . random_int(100000, 999999);
            } while (CharterBooking::where('reference_code', $reference)->exists());

            $busType = BusType::findOrFail($validated['bus_type_id']);
            $originArea = isset($validated['origin_area_id']) ? ServiceArea::find($validated['origin_area_id']) : null;
            $destinationArea = isset($validated['destination_area_id']) ? ServiceArea::find($validated['destination_area_id']) : null;
            $distanceKm = $this->estimateDistanceKm($originArea, $destinationArea, $validated['trip_type']);
            $validated['bus_type'] = $busType->slug;
            $validated['requested_bus_count'] = (int) ceil($validated['passenger_count'] / max(1, $busType->capacity));
            $price = $this->calculatePrice($busType, $distanceKm, $validated['requested_bus_count']);
            $price['breakdown']['booking_mode'] = $wholeBusBooking ? 'whole_bus' : 'passengers';

            $booking = CharterBooking::create(array_merge($validated, [
                'reference_code' => $reference,
                'customer_id' => $request->user()->id,
                'quoted_price' => $price['total_price'],
                'unit_price' => $price['unit_price'],
                'distance_km' => $distanceKm,
                'price_breakdown' => $price['breakdown'],
                'status' => 'waiting_quote',
            ]));

            User::where('role', 0)->pluck('id')->each(function ($adminId) use ($booking, $request) {
                Notification::create([
                    'user_id' => $adminId,
                    'message' => "Booking baru {$booking->reference_code} dari {$request->user()->name}",
                    'seen' => 0,
                ]);
            });

            return response()->json([
                'message' => 'Permintaan booking berhasil dikirim ke Super Admin.',
                'booking' => $booking,
            ], 201);
        });
    }

    public function adminIndex(Request $request)
    {
        $query = CharterBooking::with([
            'customer:id,name,email,tel_number', 'bus.depot', 'busType', 'originArea', 'destinationArea',
            'assignments.bus.depot', 'assignments.driver:id,name,email,tel_number',
            'driver:id,name,email,tel_number', 'depot', 'operationalTrip:id,started_at,ended_at,last_position_lat,last_position_lng',
        ])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        return response()->json(['bookings' => $query->get()]);
    }

    public function staffIndex(Request $request)
    {
        $staff = $request->user();
        $depotId = $staff->depot_id;

        if (!$depotId) {
            return response()->json(['bookings' => []]);
        }

        $query = CharterBooking::with([
            'customer:id,name,email,tel_number', 'bus.depot', 'busType', 'originArea', 'destinationArea',
            'assignments.bus.depot', 'assignments.driver:id,name,email,tel_number',
            'driver:id,name,email,tel_number', 'depot', 'operationalTrip:id,started_at,ended_at,last_position_lat,last_position_lng',
        ])->latest()->forDepot($depotId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        return response()->json(['bookings' => $query->get()]);
    }

    public function staffUpdate(Request $request, CharterBooking $charterBooking)
    {
        $staff = $request->user();
        $depotId = $staff->depot_id;

        if (!$depotId) {
            abort(403, 'Staff tidak terkait depot.');
        }

        $bookingDepotId = optional($charterBooking->bus)->depot_id;
        $assignedDepotIds = $charterBooking->assignments->pluck('bus.depot_id')->filter()->toArray();

        if ($bookingDepotId !== $depotId && !in_array($depotId, $assignedDepotIds)) {
            abort(403, 'Booking bukan di depot Anda.');
        }

        return $this->adminUpdate($request, $charterBooking);
    }

    public function staffDashboard(Request $request)
    {
        $staff = $request->user();
        $depotId = $staff->depot_id;

        if (!$depotId) {
            return response()->json(['dashboard' => []]);
        }

        $baseQuery = CharterBooking::forDepot($depotId);

        $totalBookings = $baseQuery->count();
        $pendingBookings = (clone $baseQuery)->where('status', 'waiting_quote')->count();
        $activeBookings = (clone $baseQuery)->where('status', 'approved')->count();
        $totalBuses = \App\Models\Bus::where('depot_id', $depotId)->where('is_active', true)->count();
        $availableBuses = (clone \App\Models\Bus::where('depot_id', $depotId))->where('is_active', true)->where('status', 'available')->count();
        $totalDrivers = \App\Models\User::where('depot_id', $depotId)->where('role', 2)->where('status_id', 1)->count();

        return response()->json([
            'dashboard' => [
                'total_bookings' => $totalBookings,
                'pending_bookings' => $pendingBookings,
                'active_bookings' => $activeBookings,
                'total_buses' => $totalBuses,
                'available_buses' => $availableBuses,
                'total_drivers' => $totalDrivers,
            ]
        ]);
    }

    public function adminUpdate(Request $request, CharterBooking $charterBooking)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'quoted_price' => 'nullable|numeric|min:0|max:9999999999999',
            'admin_notes' => 'nullable|string|max:2000',
            'payment_bank_name' => 'nullable|string|max:100',
            'payment_account_number' => 'nullable|string|max:100',
            'payment_account_holder' => 'nullable|string|max:150',
            'bus_id' => 'nullable|integer|exists:buses,id',
            'driver_id' => 'nullable|integer|exists:users,id',
            'assignments' => 'nullable|array',
            'assignments.*.bus_id' => 'required_with:assignments|integer|exists:buses,id',
            'assignments.*.driver_id' => 'required_with:assignments|integer|exists:users,id',
            'departure_time' => 'nullable|date_format:H:i',
            'return_date' => 'nullable|date',
            'return_time' => 'nullable|date_format:H:i',
        ]);

        if (empty($validated['quoted_price']) && $charterBooking->quoted_price) {
            $validated['quoted_price'] = $charterBooking->quoted_price;
        }

        if (in_array($validated['status'], ['quote_sent', 'approved'], true)
            && empty($validated['quoted_price'])) {
            return response()->json([
                'errors' => ['quoted_price' => ['Harga otomatis belum tersedia. Periksa kategori bus dan jarak booking.']],
            ], 422);
        }

        if (in_array($validated['status'], ['quote_sent', 'approved'], true)
            && (empty($validated['payment_bank_name']) || empty($validated['payment_account_number'])
                || empty($validated['payment_account_holder']))) {
            return response()->json([
                'errors' => ['payment_account' => ['Detail rekening pembayaran wajib dilengkapi.']],
            ], 422);
        }

        $assignmentFields = ['departure_time', 'return_date', 'return_time'];
        $hasAssignment = collect($assignmentFields)->contains(fn ($field) => !empty($validated[$field]));
        $hasAssignment = $hasAssignment || !empty($validated['assignments']) || !empty($validated['bus_id']) || !empty($validated['driver_id']);
        if ($hasAssignment && collect($assignmentFields)->contains(fn ($field) => empty($validated[$field]))) {
            throw ValidationException::withMessages(['assignment' => 'Bus, driver, jam berangkat, serta tanggal dan jam kembali wajib dilengkapi bersama.']);
        }

        if ($hasAssignment) {
            if ($validated['status'] !== 'approved' || $charterBooking->payment_status !== 'paid') {
                throw ValidationException::withMessages(['assignment' => 'Penugasan operasional hanya bisa dibuat setelah booking disetujui dan pembayaran lunas.']);
            }

            $assignments = $this->normalizeAssignments($validated);
            $this->ensureAssignmentAvailable($charterBooking, $validated, $assignments);
            $bus = Bus::findOrFail($assignments[0]['bus_id']);
            $driver = User::findOrFail($assignments[0]['driver_id']);
            $validated['bus_id'] = $bus->id;
            $validated['driver_id'] = $driver->id;
            $validated['depot_id'] = $bus->depot_id;
            $validated['assigned_at'] = now();
        }

        $assignments = $hasAssignment ? $this->normalizeAssignments($validated) : null;
        unset($validated['assignments']);

        $newStatus = $validated['status'] ?? $charterBooking->status;

        if ($newStatus === 'quote_sent' && $charterBooking->status !== 'quote_sent') {
            $validated['payment_deadline'] = now()->addHours(24);
        }

        $oldBusIds = $charterBooking->assignments->pluck('bus_id')->filter()->values()->all();
        if ($charterBooking->bus_id) $oldBusIds[] = $charterBooking->bus_id;
        $oldBusIds = array_unique($oldBusIds);

        $charterBooking->update($validated);
        if ($assignments !== null) {
            $charterBooking->assignments()->delete();
            foreach ($assignments as $assignment) {
                $charterBooking->assignments()->create($assignment);
            }
            Bus::whereIn('id', collect($assignments)->pluck('bus_id'))->update(['status' => 'on_trip']);
            $newBusIds = collect($assignments)->pluck('bus_id')->values()->all();
            $releasedBusIds = array_diff($oldBusIds, $newBusIds);
            if (!empty($releasedBusIds)) {
                Bus::whereIn('id', $releasedBusIds)->where('status', 'on_trip')->update(['status' => 'available']);
            }
        }
        $this->syncOperationalTrip($charterBooking);

        if (in_array($newStatus, ['cancelled', 'completed'])) {
            $this->resetBusStatus($charterBooking);
        }

        $notifMessage = match ($newStatus) {
            'quote_sent' => "Admin mengirim penawaran harga {$charterBooking->reference_code}. Silakan lakukan pembayaran sebelum " . $charterBooking->payment_deadline->format('d/m/Y H:i') . ".",
            'approved' => "Booking {$charterBooking->reference_code} telah disetujui. Menunggu penugasan armada.",
            'rejected' => "Booking {$charterBooking->reference_code} ditolak oleh admin.",
            'cancelled' => "Booking {$charterBooking->reference_code} dibatalkan oleh admin.",
            'completed' => "Booking {$charterBooking->reference_code} telah selesai. Terima kasih!",
            default => "Status booking {$charterBooking->reference_code} telah diperbarui ke " . ($this->statusLabels()[$newStatus] ?? $newStatus) . ".",
        };

        Notification::create([
            'user_id' => $charterBooking->customer_id,
            'message' => $notifMessage,
            'seen' => 0,
        ]);

        return response()->json([
            'message' => 'Booking berhasil diperbarui.',
            'booking' => $this->freshBooking($charterBooking),
        ]);
    }

    public function assignmentOptions()
    {
        return response()->json([
            'buses' => Bus::with(['depot:id,name,city,is_active', 'busType'])->where('is_active', true)->orderBy('license')->get(),
            'drivers' => User::where('role', 2)->where('status_id', 1)->orderBy('name')->get(['id', 'name', 'email', 'tel_number']),
        ]);
    }

    public function options(Request $request)
    {
        $request->validate([
            'departure_date' => 'nullable|date',
            'return_date' => 'nullable|date|after_or_equal:departure_date',
            'passenger_count' => 'nullable|integer|min:0|max:1000',
            'trip_type' => ['nullable', Rule::in(['one_way', 'round_trip'])],
            'origin_area_id' => 'nullable|integer',
            'destination_area_id' => 'nullable|integer',
        ]);
        $areas = ServiceArea::where('is_active', true)->orderBy('name')->get(['id', 'name', 'area_group']);
        $busTypes = BusType::where('is_active', true)
            ->whereHas('buses', fn ($query) => $query->where('is_active', true))
            ->orderBy('capacity')->get();

        $passengers = (int) $request->query('passenger_count', 0);
        $departureDate = $request->query('departure_date');
        $returnDate = $request->query('return_date') ?: $departureDate;
        $tripType = $request->query('trip_type', 'one_way');
        $originArea = $request->filled('origin_area_id') ? ServiceArea::find($request->query('origin_area_id')) : null;
        $destinationArea = $request->filled('destination_area_id') ? ServiceArea::find($request->query('destination_area_id')) : null;
        $distanceKm = $this->estimateDistanceKm($originArea, $destinationArea, $tripType);

        $busTypes = $busTypes->map(function (BusType $busType) use ($passengers, $departureDate, $returnDate, $distanceKm) {
            if ($passengers > 0 && $departureDate) {
                $requiredBuses = (int) ceil($passengers / max(1, $busType->capacity));
                $price = $this->calculatePrice($busType, $distanceKm, $requiredBuses);

                return array_merge(
                    $busType->toArray(),
                    $this->availabilityForBusType($busType, $passengers, $departureDate, $returnDate),
                    [
                        'distance_km' => $distanceKm,
                        'unit_price' => $price['unit_price'],
                        'estimated_price' => $price['total_price'],
                        'price_breakdown' => $price['breakdown'],
                    ]
                );
            }

            $availableBuses = Bus::where('bus_type_id', $busType->id)->where('is_active', true)->where('status', 'available')->count();
            return array_merge($busType->toArray(), [
                'available_buses' => $availableBuses,
                'required_buses' => null,
                'total_capacity' => $availableBuses * $busType->capacity,
                'is_available' => $availableBuses > 0,
                'message' => null,
                'distance_km' => null,
                'unit_price' => null,
                'estimated_price' => null,
                'price_breakdown' => null,
            ]);
        })->values();

        return response()->json([
            'service_areas' => $areas,
            'bus_types' => $busTypes,
            'recommendations' => $busTypes
                ->where('is_available', true)
                ->sortBy([
                    ['estimated_price', 'asc'],
                    ['required_buses', 'asc'],
                ])
                ->values(),
        ]);
    }

    public function submitPayment(Request $request, CharterBooking $charterBooking)
    {
        if ((int) $charterBooking->customer_id !== (int) $request->user()->id) {
            abort(403, 'Booking bukan milik customer ini.');
        }

        if (!in_array($charterBooking->status, ['quote_sent', 'approved'], true)
            || !$charterBooking->quoted_price) {
            return response()->json(['message' => 'Booking belum siap untuk dibayar.'], 422);
        }

        if (!$charterBooking->payment_bank_name || !$charterBooking->payment_account_number
            || !$charterBooking->payment_account_holder) {
            return response()->json(['message' => 'Rekening pembayaran belum diberikan Admin.'], 422);
        }

        if ($charterBooking->payment_status === 'paid') {
            return response()->json(['message' => 'Booking ini sudah lunas.'], 422);
        }

        if ($charterBooking->payment_deadline && $charterBooking->payment_deadline->isPast()) {
            return response()->json(['message' => 'Batas waktu pembayaran telah habis. Silakan hubungi admin untuk memperpanjang.'], 422);
        }

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['bank_transfer', 'mobile_banking', 'atm'])],
            'payment_reference' => 'required|string|max:150',
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        return DB::transaction(function () use ($request, $validated, $charterBooking) {
            if ($charterBooking->payment_proof_path) {
                Storage::disk('public')->delete($charterBooking->payment_proof_path);
            }

            $path = $request->file('payment_proof')->store(
                "charter-payments/{$charterBooking->id}", 'public'
            );

            $charterBooking->update([
                'payment_method' => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'],
                'payment_proof_path' => $path,
                'payment_status' => 'pending_verification',
                'payment_submitted_at' => now(),
                'paid_at' => null,
                'payment_rejection_reason' => null,
            ]);

            User::where('role', 0)->pluck('id')->each(function ($adminId) use ($charterBooking) {
                Notification::create([
                    'user_id' => $adminId,
                    'message' => "Pembayaran {$charterBooking->reference_code} menunggu verifikasi.",
                    'seen' => 0,
                ]);
            });

            return response()->json([
                'message' => 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi Admin.',
                'booking' => $charterBooking->fresh(),
            ]);
        });
    }

    public function cancelRejectedPayment(Request $request, CharterBooking $charterBooking)
    {
        if ((int) $charterBooking->customer_id !== (int) $request->user()->id) {
            abort(403, 'Booking bukan milik customer ini.');
        }

        if ($charterBooking->payment_status !== 'rejected') {
            return response()->json([
                'message' => 'Pesanan hanya dapat dibatalkan setelah pembayaran ditolak.',
            ], 422);
        }

        if (in_array($charterBooking->status, ['cancelled', 'completed'], true)) {
            return response()->json([
                'message' => 'Pesanan ini tidak dapat dibatalkan.',
            ], 422);
        }

        $charterBooking->update(['status' => 'cancelled']);
        $this->resetBusStatus($charterBooking);

        User::where('role', 0)->pluck('id')->each(function ($adminId) use ($charterBooking) {
            Notification::create([
                'user_id' => $adminId,
                'message' => "Customer membatalkan booking {$charterBooking->reference_code} setelah pembayaran ditolak.",
                'seen' => 0,
            ]);
        });

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
            'booking' => $this->freshBooking($charterBooking),
        ]);
    }

    public function customerCancel(Request $request, CharterBooking $charterBooking)
    {
        if ((int) $charterBooking->customer_id !== (int) $request->user()->id) {
            abort(403, 'Booking bukan milik customer ini.');
        }

        if (in_array($charterBooking->status, ['cancelled', 'completed'], true)) {
            return response()->json(['message' => 'Pesanan ini tidak dapat dibatalkan.'], 422);
        }

        if (!in_array($charterBooking->status, ['waiting_quote', 'quote_sent'], true)) {
            return response()->json([
                'message' => 'Pembatalan hanya bisa dilakukan sebelum pembayaran dikonfirmasi.',
            ], 422);
        }

        if ($charterBooking->payment_status === 'paid') {
            return response()->json(['message' => 'Pembayaran sudah lunas, hubungi admin untuk pembatalan.'], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $charterBooking->update([
            'status' => 'cancelled',
            'admin_notes' => $validated['reason'] ?? $charterBooking->admin_notes,
        ]);

        User::where('role', 0)->pluck('id')->each(function ($adminId) use ($charterBooking) {
            Notification::create([
                'user_id' => $adminId,
                'message' => "Customer membatalkan booking {$charterBooking->reference_code} sebelum pembayaran.",
                'seen' => 0,
            ]);
        });

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
            'booking' => $this->freshBooking($charterBooking),
        ]);
    }

    public function reviewPayment(Request $request, CharterBooking $charterBooking)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['verify', 'reject'])],
            'reason' => 'nullable|required_if:action,reject|string|max:1000',
        ]);

        if ($charterBooking->payment_status !== 'pending_verification') {
            return response()->json(['message' => 'Tidak ada pembayaran yang menunggu verifikasi.'], 422);
        }

        $isVerified = $validated['action'] === 'verify';
        DB::transaction(function () use ($isVerified, $validated, $charterBooking, $request) {
            $charterBooking->update([
                'status' => $isVerified ? 'approved' : $charterBooking->status,
                'payment_status' => $isVerified ? 'paid' : 'rejected',
                'paid_at' => $isVerified ? now() : null,
                'payment_rejection_reason' => $isVerified ? null : $validated['reason'],
            ]);

            if ($isVerified) {
                CharterRevenueTransaction::updateOrCreate(
                    ['charter_booking_id' => $charterBooking->id],
                    ['amount' => $charterBooking->quoted_price, 'status' => 'paid', 'verified_by' => $request->user()->id, 'paid_at' => now()]
                );
                $this->syncOperationalTrip($charterBooking);
            }
        });

        Notification::create([
            'user_id' => $charterBooking->customer_id,
            'message' => $isVerified
                ? "Pembayaran {$charterBooking->reference_code} sebesar Rp " . number_format((float) $charterBooking->quoted_price, 0, ',', '.') . " telah diverifikasi dan dinyatakan lunas."
                : "Pembayaran {$charterBooking->reference_code} ditolak. Alasan: {$validated['reason']}",
            'seen' => 0,
        ]);

        return response()->json([
            'message' => $isVerified ? 'Pembayaran berhasil diverifikasi.' : 'Pembayaran ditolak.',
            'booking' => $this->freshBooking($charterBooking),
        ]);
    }

    public function paymentProof(CharterBooking $charterBooking)
    {
        if (!$charterBooking->payment_proof_path
            || !Storage::disk('public')->exists($charterBooking->payment_proof_path)) {
            abort(404, 'Bukti pembayaran tidak ditemukan.');
        }

        return response()->file(Storage::disk('public')->path($charterBooking->payment_proof_path), [
            'Content-Disposition' => 'inline; filename="payment-' . $charterBooking->reference_code . '"',
        ]);
    }

    public function invoice(Request $request, CharterBooking $charterBooking)
    {
        $user = $request->user();
        $isStaffForDepot = (int) $user->role === 3 && $user->depot_id
            && CharterBooking::whereKey($charterBooking->id)->forDepot($user->depot_id)->exists();
        if ((int) $user->role !== 0
            && !((int) $user->role === 1 && (int) $charterBooking->customer_id === (int) $user->id)
            && !$isStaffForDepot) {
            abort(403, 'Invoice bukan milik user ini.');
        }

        if ($charterBooking->payment_status !== 'paid') {
            return response()->json(['message' => 'Invoice hanya tersedia setelah pembayaran lunas.'], 422);
        }

        $booking = $charterBooking->load(['customer:id,name,email,tel_number', 'bus.depot', 'busType', 'originArea', 'destinationArea', 'assignments.bus.depot', 'assignments.driver:id,name,tel_number', 'driver:id,name,tel_number']);
        $invoiceNumber = 'INV-' . $booking->reference_code;
        $paidDate = optional($booking->paid_at)->format('d/m/Y H:i') ?: '-';
        $departureDate = optional($booking->departure_date)->format('d/m/Y') ?: '-';
        $returnDate = optional($booking->return_date)->format('d/m/Y') ?: '-';
        $amount = 'Rp ' . number_format((float) $booking->quoted_price, 0, ',', '.');
        $unitAmount = 'Rp ' . number_format((float) ($booking->unit_price ?: $booking->quoted_price), 0, ',', '.');
        $busCount = max(1, (int) $booking->requested_bus_count);
        $assignedBuses = $booking->assignments->map(fn ($assignment) => optional($assignment->bus)->license)->filter()->join(', ');
        $assignedDrivers = $booking->assignments->map(fn ($assignment) => optional($assignment->driver)->name)->filter()->join(', ');
        $busType = optional($booking->busType)->name ?: ([
            'medium' => 'Medium Bus',
            'large' => 'Large Bus',
            'luxury' => 'Luxury Bus',
        ][$booking->bus_type] ?? $booking->bus_type);

        $destinationList = '';
        foreach ($booking->destinations ?? [] as $index => $stop) {
            $destinationList .= '<li>' . e($stop['address']) . '</li>';
        }

        $html = '<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoice ' . e($invoiceNumber) . '</title>
  <style>
    *{box-sizing:border-box}body{margin:0;background:#f4f5f8;color:#2f2a3d;font-family:Arial,Helvetica,sans-serif}.page{max-width:860px;margin:32px auto;padding:32px;background:#fff;border-radius:14px;box-shadow:0 14px 36px rgba(35,31,48,.08)}.top{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #eeeaf7;padding-bottom:22px}.brand{font-size:24px;font-weight:700;color:#7c3aed}.muted{color:#817b8e}.badge{display:inline-block;background:#e8f8ee;color:#16833a;border-radius:999px;padding:8px 14px;font-weight:700}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:26px}.box{border:1px solid #eeeaf7;border-radius:12px;padding:18px}.label{font-size:12px;text-transform:uppercase;color:#8a8494;margin-bottom:7px}.value{font-weight:700}.table{width:100%;border-collapse:collapse;margin-top:26px}.table th,.table td{border-bottom:1px solid #eeeaf7;padding:14px;text-align:left}.table th{background:#faf8ff;color:#6f36d8}.total{display:flex;justify-content:flex-end;margin-top:22px}.total-box{min-width:280px;border-radius:12px;background:#f6f1ff;padding:18px}.total-row{display:flex;justify-content:space-between;gap:20px}.amount{font-size:24px;font-weight:800;color:#6f36d8}.actions{margin-top:28px;text-align:right}.print{border:0;border-radius:10px;background:#7c3aed;color:#fff;padding:12px 18px;font-weight:700;cursor:pointer}@media print{body{background:#fff}.page{margin:0;max-width:none;box-shadow:none;border-radius:0}.actions{display:none}}@media(max-width:700px){.top,.grid{display:block}.box{margin-top:14px}}
  </style>
</head>
<body>
  <main class="page">
    <section class="top">
      <div>
        <div class="brand">EZBus Pariwisata</div>
        <div class="muted">Invoice pembayaran sewa bus pariwisata</div>
      </div>
      <div>
        <div class="label">Nomor Invoice</div>
        <div class="value">' . e($invoiceNumber) . '</div>
        <div style="margin-top:10px"><span class="badge">LUNAS</span></div>
      </div>
    </section>

    <section class="grid">
      <div class="box">
        <div class="label">Ditagihkan kepada</div>
        <div class="value">' . e(optional($booking->customer)->name ?: '-') . '</div>
        <div class="muted">' . e(optional($booking->customer)->email ?: '-') . '</div>
        <div class="muted">' . e(optional($booking->customer)->tel_number ?: '-') . '</div>
      </div>
      <div class="box">
        <div class="label">Informasi pembayaran</div>
        <div>No. Booking: <strong>' . e($booking->reference_code) . '</strong></div>
        <div>Tanggal Lunas: <strong>' . e($paidDate) . '</strong></div>
        <div>Metode: <strong>' . e($booking->payment_method ?: '-') . '</strong></div>
      </div>
      <div class="box">
        <div class="label">Rute perjalanan</div>
        <div class="value">' . e($booking->origin) . ' - ' . e($booking->destination) . '</div><ol>' . $destinationList . '</ol>
        <div class="muted">Berangkat: ' . e($departureDate) . ' ' . e($booking->departure_time ? substr($booking->departure_time, 0, 5) : '') . '</div>
        <div class="muted">Kembali: ' . e($returnDate) . ' ' . e($booking->return_time ? substr($booking->return_time, 0, 5) : '') . '</div>
      </div>
      <div class="box">
        <div class="label">Armada</div>
        <div>Jenis Bus: <strong>' . e($busType) . '</strong></div>
        <div>' . (($booking->price_breakdown['booking_mode'] ?? null) === 'whole_bus' ? 'Kapasitas dipesan' : 'Jumlah Peserta') . ': <strong>' . e((string) $booking->passenger_count) . ' orang</strong></div>
        <div>Bus: <strong>' . e($assignedBuses ?: optional($booking->bus)->license ?: '-') . '</strong></div>
        <div>Driver: <strong>' . e($assignedDrivers ?: optional($booking->driver)->name ?: '-') . '</strong></div>
      </div>
    </section>

    <table class="table">
      <thead><tr><th>Deskripsi</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
      <tbody><tr><td>Sewa ' . e($busType) . ' rute ' . e($booking->origin) . ' - ' . e($booking->destination) . '</td><td>' . e((string) $busCount) . '</td><td>' . e($unitAmount) . '</td><td>' . e($amount) . '</td></tr></tbody>
    </table>

    <section class="total">
      <div class="total-box">
        ' . (isset($booking->price_breakdown['base_price']) ? '<div class="total-row"><span>Harga Dasar Bus / unit</span><span>Rp ' . number_format((float) $booking->price_breakdown['base_price'], 0, ',', '.') . '</span></div>' : '') . '
        <div class="total-row"><span>Total Pembayaran</span><span class="amount">' . e($amount) . '</span></div>
        <div class="muted" style="margin-top:8px">Status pembayaran telah diverifikasi oleh Admin.</div>
      </div>
    </section>

    <div class="actions"><button class="print" onclick="window.print()">Cetak / Simpan PDF</button></div>
  </main>
</body>
</html>';

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function ensureAssignmentAvailable(CharterBooking $booking, array $data, array $assignments): void
    {
        $departureDate = $booking->departure_date->format('Y-m-d');
        if ($data['return_date'] < $departureDate
            || ($data['return_date'] === $departureDate && $data['return_time'] <= $data['departure_time'])) {
            throw ValidationException::withMessages(['return_date' => 'Waktu kembali harus setelah waktu keberangkatan.']);
        }

        $requiredCount = max(1, (int) $booking->requested_bus_count);
        if (count($assignments) !== $requiredCount) {
            throw ValidationException::withMessages(['assignment' => "Booking ini membutuhkan {$requiredCount} unit bus."]);
        }

        $busIds = collect($assignments)->pluck('bus_id')->all();
        $driverIds = collect($assignments)->pluck('driver_id')->all();
        if (count($busIds) !== count(array_unique($busIds))) {
            throw ValidationException::withMessages(['assignment' => 'Bus yang dipilih tidak boleh sama.']);
        }
        if (count($driverIds) !== count(array_unique($driverIds))) {
            throw ValidationException::withMessages(['assignment' => 'Driver yang dipilih tidak boleh sama.']);
        }

        $buses = Bus::whereIn('id', $busIds)->where('is_active', true)->get();
        if ($buses->count() !== count($busIds)) {
            throw ValidationException::withMessages(['assignment' => 'Ada bus yang tidak aktif atau tidak valid.']);
        }

        if ($buses->contains(fn ($bus) => (int) $bus->bus_type_id !== (int) $booking->bus_type_id)) {
            throw ValidationException::withMessages(['assignment' => 'Semua bus harus sesuai kategori yang diminta customer.']);
        }

        $totalCapacity = $buses->sum('capacity');
        if ($totalCapacity < (int) $booking->passenger_count) {
            throw ValidationException::withMessages(['assignment' => "Total kapasitas bus hanya {$totalCapacity} kursi."]);
        }

        $activeDrivers = User::whereIn('id', $driverIds)->where('role', 2)->where('status_id', 1)->count();
        if ($activeDrivers !== count($driverIds)) {
            throw ValidationException::withMessages(['assignment' => 'Ada driver yang tidak aktif atau tidak valid.']);
        }

        $start = $departureDate;
        $end = $data['return_date'];
        $conflicts = CharterBooking::where('id', '!=', $booking->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where(function ($query) use ($start, $end) {
                $query->whereDate('departure_date', '<=', $end)
                    ->where(function ($inner) use ($start) {
                        $inner->whereDate('return_date', '>=', $start)->orWhereNull('return_date');
                    });
            });

        if ((clone $conflicts)->whereIn('bus_id', $busIds)->exists()
            || CharterBookingAssignment::whereIn('bus_id', $busIds)
                ->whereHas('booking', function ($query) use ($booking, $start, $end) {
                    $query->where('id', '!=', $booking->id)
                        ->whereNotIn('status', ['rejected', 'cancelled', 'completed'])
                        ->whereDate('departure_date', '<=', $end)
                        ->where(function ($inner) use ($start) {
                            $inner->whereDate('return_date', '>=', $start)->orWhereNull('return_date');
                        });
                })->exists()) {
            throw ValidationException::withMessages(['bus_id' => 'Bus sudah dipakai oleh booking lain pada rentang tanggal tersebut.']);
        }
        if ((clone $conflicts)->whereIn('driver_id', $driverIds)->exists()
            || CharterBookingAssignment::whereIn('driver_id', $driverIds)
                ->whereHas('booking', function ($query) use ($booking, $start, $end) {
                    $query->where('id', '!=', $booking->id)
                        ->whereNotIn('status', ['rejected', 'cancelled', 'completed'])
                        ->whereDate('departure_date', '<=', $end)
                        ->where(function ($inner) use ($start) {
                            $inner->whereDate('return_date', '>=', $start)->orWhereNull('return_date');
                        });
                })->exists()) {
            throw ValidationException::withMessages(['driver_id' => 'Driver sudah memiliki booking lain pada rentang tanggal tersebut.']);
        }

        $plannedConflict = PlannedTrip::whereBetween('planned_date', [$start, $end])
            ->where(function ($query) use ($busIds, $driverIds) {
                $query->whereIn('bus_id', $busIds)->orWhereIn('driver_id', $driverIds);
            });
        if ($booking->operational_planned_trip_id) $plannedConflict->where('id', '!=', $booking->operational_planned_trip_id);
        if ($plannedConflict->exists()) {
            throw ValidationException::withMessages(['assignment' => 'Bus atau driver berbenturan dengan jadwal perjalanan reguler.']);
        }
    }

    private function syncOperationalTrip(CharterBooking $booking): void
    {
        $booking->refresh();
        if ($booking->payment_status !== 'paid' || !$booking->departure_time || !$booking->return_date || !$booking->return_time) return;

        \Illuminate\Support\Facades\DB::transaction(function () use ($booking) {
        $allAssignments = [];

        if ($booking->bus_id && $booking->driver_id) {
            $allAssignments[] = ['bus_id' => $booking->bus_id, 'driver_id' => $booking->driver_id];
        }

        if ($booking->assignments && $booking->assignments->count() > 0) {
            foreach ($booking->assignments as $assignment) {
                if ($assignment->bus_id && $assignment->driver_id) {
                    $alreadyAdded = false;
                    foreach ($allAssignments as $existing) {
                        if ($existing['bus_id'] == $assignment->bus_id && $existing['driver_id'] == $assignment->driver_id) {
                            $alreadyAdded = true;
                            break;
                        }
                    }
                    if (!$alreadyAdded) {
                        $allAssignments[] = ['bus_id' => $assignment->bus_id, 'driver_id' => $assignment->driver_id];
                    }
                }
            }
        }

        if (empty($allAssignments)) return;

        // Check if already synced
        if ($booking->operational_planned_trip_id) {
            $existingTrips = \App\Models\PlannedTrip::where('route_id', optional($booking->operationalTrip)->route_id ?? 0)->get();
            if ($existingTrips->count() === count($allAssignments)) {
                foreach ($existingTrips as $pt) {
                    if (!$pt->started_at) {
                        $match = collect($allAssignments)->first(function ($a) use ($pt) {
                            return $a['bus_id'] == $pt->bus_id && $a['driver_id'] == $pt->driver_id;
                        });
                        if ($match) {
                            $pt->update(['planned_date' => $booking->departure_date]);
                            $pt->trip()->update(['first_stop_time' => $booking->departure_time, 'last_stop_time' => $booking->return_time]);
                        }
                    }
                }
                return;
            }
        }

        // Create route
        $route = \App\Models\Route::create(['name' => "Charter {$booking->reference_code}: {$booking->origin} - {$booking->destination}"]);

        $firstPlannedId = null;
        foreach ($allAssignments as $index => $assignment) {
            $channel = 'charter-' . $booking->id . '-' . $index . '-' . bin2hex(random_bytes(4));
            $trip = Trip::create([
                'channel' => $channel, 'route_id' => $route->id, 'effective_date' => $booking->departure_date,
                'repetition_period' => 0, 'stop_to_stop_avg_time' => 0, 'first_stop_time' => $booking->departure_time,
                'last_stop_time' => $booking->return_time, 'status_id' => 1, 'driver_id' => $assignment['driver_id'],
            ]);
            $seatsPerBus = (int) ceil(($booking->passenger_count ?? 0) / count($allAssignments));
            $planned = PlannedTrip::create([
                'channel' => $channel, 'trip_id' => $trip->id, 'route_id' => $route->id,
                'planned_date' => $booking->departure_date, 'driver_id' => $assignment['driver_id'],
                'bus_id' => $assignment['bus_id'], 'reserved_seats' => $seatsPerBus,
            ]);

            if ($index === 0) {
                $firstPlannedId = $planned->id;
            }
        }

            if ($firstPlannedId) {
                $booking->update(['operational_planned_trip_id' => $firstPlannedId]);
            }
        });
    }

    private function freshBooking(CharterBooking $booking): CharterBooking
    {
        return $booking->fresh(['customer:id,name,email,tel_number', 'bus.depot', 'busType', 'originArea', 'destinationArea', 'assignments.bus.depot', 'assignments.driver:id,name,email,tel_number', 'driver:id,name,email,tel_number', 'depot', 'operationalTrip']);
    }

    private function resetBusStatus(CharterBooking $booking): void
    {
        $busIds = [];
        if ($booking->bus_id) {
            $busIds[] = $booking->bus_id;
        }
        $assignmentBusIds = $booking->assignments->pluck('bus_id')->toArray();
        $busIds = array_merge($busIds, $assignmentBusIds);
        $busIds = array_unique($busIds);

        if (!empty($busIds)) {
            Bus::whereIn('id', $busIds)->update(['status' => 'available']);
        }

        $driverIds = [];
        if ($booking->driver_id) {
            $driverIds[] = $booking->driver_id;
        }
        $assignmentDriverIds = $booking->assignments->pluck('driver_id')->toArray();
        $driverIds = array_merge($driverIds, $assignmentDriverIds);
        $driverIds = array_unique($driverIds);

        if (!empty($driverIds)) {
            \App\Models\User::whereIn('id', $driverIds)->update(['status' => 'available']);
        }
    }

    private function normalizeAssignments(array $data): array
    {
        if (!empty($data['assignments'])) {
            return collect($data['assignments'])
                ->map(fn ($assignment) => [
                    'bus_id' => (int) $assignment['bus_id'],
                    'driver_id' => (int) $assignment['driver_id'],
                ])
                ->values()
                ->all();
        }

        if (!empty($data['bus_id']) && !empty($data['driver_id'])) {
            return [[
                'bus_id' => (int) $data['bus_id'],
                'driver_id' => (int) $data['driver_id'],
            ]];
        }

        throw ValidationException::withMessages(['assignment' => 'Pilih minimal satu bus dan driver.']);
    }

    private function availabilityForBusType(BusType $busType, int $passengers, string $departureDate, ?string $returnDate): array
    {
        $returnDate = $returnDate ?: $departureDate;
        $requiredBuses = (int) ceil($passengers / max(1, $busType->capacity));
        $activeBusCount = Bus::where('bus_type_id', $busType->id)
            ->where('is_active', true)
            ->where('status', 'available')
            ->count();

        $reservedCharterBusCount = (int) CharterBooking::where('status', 'approved')
            ->where('bus_type_id', $busType->id)
            ->where(function ($query) use ($departureDate, $returnDate) {
                $query->whereDate('departure_date', '<=', $returnDate)
                    ->where(function ($inner) use ($departureDate) {
                        $inner->whereDate('return_date', '>=', $departureDate)
                            ->orWhereNull('return_date');
                    });
            })
            ->sum('requested_bus_count');

        $plannedBusCount = PlannedTrip::whereBetween('planned_date', [$departureDate, $returnDate])
            ->whereNotNull('bus_id')
            ->whereIn('bus_id', Bus::where('bus_type_id', $busType->id)->where('is_active', true)->select('id'))
            ->whereNotIn('id', CharterBooking::whereNotNull('operational_planned_trip_id')->select('operational_planned_trip_id'))
            ->distinct('bus_id')
            ->count('bus_id');

        $availableBuses = max(0, $activeBusCount - $reservedCharterBusCount - $plannedBusCount);
        $totalCapacity = $availableBuses * $busType->capacity;
        $isAvailable = $availableBuses >= $requiredBuses;

        return [
            'available_buses' => $availableBuses,
            'required_buses' => $requiredBuses,
            'total_capacity' => $totalCapacity,
            'is_available' => $isAvailable,
            'message' => $isAvailable ? null : "{$busType->name} aktif hanya tersedia {$availableBuses} unit dengan total kapasitas {$totalCapacity} kursi. Pesanan {$passengers} peserta membutuhkan minimal {$requiredBuses} unit.",
        ];
    }

    private function calculatePrice(BusType $busType, ?float $distanceKm, int $busCount): array
    {
        $distance = max(0, (float) ($distanceKm ?? 0));
        $basePrice = (float) ($busType->base_price ?? 0);
        $pricePerKm = (float) ($busType->price_per_km ?? 0);
        $minimumPrice = (float) ($busType->minimum_price ?? 0);
        $pickupFee = (float) ($busType->pickup_fee ?? 0);
        $quantity = max(1, $busCount);

        $unitPrice = max($minimumPrice, $basePrice + ($distance * $pricePerKm));
        $totalPrice = ($unitPrice + $pickupFee) * $quantity;

        return [
            'unit_price' => round($unitPrice, 2),
            'pickup_fee' => round($pickupFee, 2),
            'total_price' => round($totalPrice, 2),
            'breakdown' => [
                'base_price' => $basePrice,
                'price_per_km' => $pricePerKm,
                'minimum_price' => $minimumPrice,
                'pickup_fee' => $pickupFee,
                'distance_km' => $distance,
                'bus_count' => $quantity,
                'formula' => '(max(minimum_price, base_price + distance_km * price_per_km) + pickup_fee) * bus_count',
            ],
        ];
    }

    private function estimateDistanceKm(?ServiceArea $originArea, ?ServiceArea $destinationArea, string $tripType): ?float
    {
        if (!$originArea || !$destinationArea
            || $originArea->latitude === null || $originArea->longitude === null
            || $destinationArea->latitude === null || $destinationArea->longitude === null) {
            return null;
        }

        $earthRadius = 6371;
        $latFrom = deg2rad((float) $originArea->latitude);
        $lonFrom = deg2rad((float) $originArea->longitude);
        $latTo = deg2rad((float) $destinationArea->latitude);
        $lonTo = deg2rad((float) $destinationArea->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        $distance = $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
        if ($tripType === 'round_trip') {
            $distance *= 2;
        }

        return round($distance, 2);
    }

    private function statusLabels(): array
    {
        return [
            'waiting_quote' => 'Menunggu penawaran',
            'quote_sent' => 'Penawaran dikirim',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'completed' => 'Selesai',
        ];
    }
}
