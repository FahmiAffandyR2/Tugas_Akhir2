<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CharterBooking;
use App\Models\CharterRevenueTransaction;
use App\Models\Bus;
use App\Models\Notification;
use App\Models\PlannedTrip;
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

    public function customerIndex(Request $request)
    {
        return response()->json([
            'bookings' => CharterBooking::where('customer_id', $request->user()->id)
                ->with(['bus.depot', 'driver:id,name,tel_number', 'operationalTrip:id,started_at,ended_at,last_position_lat,last_position_lng'])
                ->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'departure_date' => 'required|date|after_or_equal:today',
            'passenger_count' => 'required|integer|min:1|max:1000',
            'bus_type' => ['required', Rule::in(['medium', 'large', 'luxury'])],
            'notes' => 'nullable|string|max:2000',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            do {
                $reference = 'REQ-' . random_int(100000, 999999);
            } while (CharterBooking::where('reference_code', $reference)->exists());

            $booking = CharterBooking::create(array_merge($validated, [
                'reference_code' => $reference,
                'customer_id' => $request->user()->id,
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
            'customer:id,name,email,tel_number', 'bus.depot', 'driver:id,name,email,tel_number',
            'depot', 'operationalTrip:id,started_at,ended_at,last_position_lat,last_position_lng',
        ])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(['bookings' => $query->get()]);
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
            'departure_time' => 'nullable|date_format:H:i',
            'return_date' => 'nullable|date',
            'return_time' => 'nullable|date_format:H:i',
        ]);

        if (in_array($validated['status'], ['quote_sent', 'approved'], true)
            && empty($validated['quoted_price'])) {
            return response()->json([
                'errors' => ['quoted_price' => ['Harga penawaran wajib diisi.']],
            ], 422);
        }

        if (in_array($validated['status'], ['quote_sent', 'approved'], true)
            && (empty($validated['payment_bank_name']) || empty($validated['payment_account_number'])
                || empty($validated['payment_account_holder']))) {
            return response()->json([
                'errors' => ['payment_account' => ['Detail rekening pembayaran wajib dilengkapi.']],
            ], 422);
        }

        $assignmentFields = ['bus_id', 'driver_id', 'departure_time', 'return_date', 'return_time'];
        $hasAssignment = collect($assignmentFields)->contains(fn ($field) => !empty($validated[$field]));
        if ($hasAssignment && collect($assignmentFields)->contains(fn ($field) => empty($validated[$field]))) {
            throw ValidationException::withMessages(['assignment' => 'Bus, driver, jam berangkat, serta tanggal dan jam kembali wajib dilengkapi bersama.']);
        }

        if ($hasAssignment) {
            $this->ensureAssignmentAvailable($charterBooking, $validated);
            $bus = Bus::findOrFail($validated['bus_id']);
            $validated['depot_id'] = $bus->depot_id;
            $validated['assigned_at'] = now();
        }

        $charterBooking->update($validated);
        $this->syncOperationalTrip($charterBooking);

        Notification::create([
            'user_id' => $charterBooking->customer_id,
            'message' => "Status booking {$charterBooking->reference_code} telah diperbarui.",
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
            'buses' => Bus::with('depot:id,name,city,is_active')->orderBy('license')->get(),
            'drivers' => User::where('role', 2)->where('status_id', 1)->orderBy('name')->get(['id', 'name', 'email', 'tel_number']),
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
                ? "Pembayaran {$charterBooking->reference_code} telah diverifikasi dan dinyatakan lunas."
                : "Pembayaran {$charterBooking->reference_code} ditolak. Silakan periksa kembali.",
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
        if ((int) $user->role !== 0 && (int) $charterBooking->customer_id !== (int) $user->id) {
            abort(403, 'Invoice bukan milik user ini.');
        }

        if ($charterBooking->payment_status !== 'paid') {
            return response()->json(['message' => 'Invoice hanya tersedia setelah pembayaran lunas.'], 422);
        }

        $booking = $charterBooking->load(['customer:id,name,email,tel_number', 'bus.depot', 'driver:id,name,tel_number']);
        $invoiceNumber = 'INV-' . $booking->reference_code;
        $paidDate = optional($booking->paid_at)->format('d/m/Y H:i') ?: '-';
        $departureDate = optional($booking->departure_date)->format('d/m/Y') ?: '-';
        $returnDate = optional($booking->return_date)->format('d/m/Y') ?: '-';
        $amount = 'Rp ' . number_format((float) $booking->quoted_price, 0, ',', '.');
        $busType = [
            'medium' => 'Medium Bus',
            'large' => 'Large Bus',
            'luxury' => 'Luxury Bus',
        ][$booking->bus_type] ?? $booking->bus_type;

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
        <div class="value">' . e($booking->origin) . ' - ' . e($booking->destination) . '</div>
        <div class="muted">Berangkat: ' . e($departureDate) . ' ' . e($booking->departure_time ? substr($booking->departure_time, 0, 5) : '') . '</div>
        <div class="muted">Kembali: ' . e($returnDate) . ' ' . e($booking->return_time ? substr($booking->return_time, 0, 5) : '') . '</div>
      </div>
      <div class="box">
        <div class="label">Armada</div>
        <div>Jenis Bus: <strong>' . e($busType) . '</strong></div>
        <div>Jumlah Peserta: <strong>' . e((string) $booking->passenger_count) . ' orang</strong></div>
        <div>Bus: <strong>' . e(optional($booking->bus)->license ?: '-') . '</strong></div>
        <div>Driver: <strong>' . e(optional($booking->driver)->name ?: '-') . '</strong></div>
      </div>
    </section>

    <table class="table">
      <thead><tr><th>Deskripsi</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
      <tbody><tr><td>Sewa ' . e($busType) . ' rute ' . e($booking->origin) . ' - ' . e($booking->destination) . '</td><td>1</td><td>' . e($amount) . '</td><td>' . e($amount) . '</td></tr></tbody>
    </table>

    <section class="total">
      <div class="total-box">
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

    private function ensureAssignmentAvailable(CharterBooking $booking, array $data): void
    {
        $departureDate = $booking->departure_date->format('Y-m-d');
        if ($data['return_date'] < $departureDate
            || ($data['return_date'] === $departureDate && $data['return_time'] <= $data['departure_time'])) {
            throw ValidationException::withMessages(['return_date' => 'Waktu kembali harus setelah waktu keberangkatan.']);
        }

        $bus = Bus::findOrFail($data['bus_id']);
        if ((int) $bus->capacity < (int) $booking->passenger_count) {
            throw ValidationException::withMessages(['bus_id' => "Kapasitas bus hanya {$bus->capacity} kursi."]);
        }

        $driver = User::where('id', $data['driver_id'])->where('role', 2)->where('status_id', 1)->first();
        if (!$driver) throw ValidationException::withMessages(['driver_id' => 'Driver tidak aktif atau tidak valid.']);

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

        if ((clone $conflicts)->where('bus_id', $data['bus_id'])->exists()) {
            throw ValidationException::withMessages(['bus_id' => 'Bus sudah dipakai oleh booking lain pada rentang tanggal tersebut.']);
        }
        if ((clone $conflicts)->where('driver_id', $data['driver_id'])->exists()) {
            throw ValidationException::withMessages(['driver_id' => 'Driver sudah memiliki booking lain pada rentang tanggal tersebut.']);
        }

        $plannedConflict = PlannedTrip::whereBetween('planned_date', [$start, $end])
            ->where(function ($query) use ($data) {
                $query->where('bus_id', $data['bus_id'])->orWhere('driver_id', $data['driver_id']);
            });
        if ($booking->operational_planned_trip_id) $plannedConflict->where('id', '!=', $booking->operational_planned_trip_id);
        if ($plannedConflict->exists()) {
            throw ValidationException::withMessages(['assignment' => 'Bus atau driver berbenturan dengan jadwal perjalanan reguler.']);
        }
    }

    private function syncOperationalTrip(CharterBooking $booking): void
    {
        $booking->refresh();
        if ($booking->payment_status !== 'paid' || !$booking->bus_id || !$booking->driver_id
            || !$booking->departure_time || !$booking->return_date || !$booking->return_time) return;

        if ($booking->operational_planned_trip_id) {
            $planned = PlannedTrip::find($booking->operational_planned_trip_id);
            if ($planned && !$planned->started_at) {
                $planned->update(['planned_date' => $booking->departure_date, 'driver_id' => $booking->driver_id, 'bus_id' => $booking->bus_id]);
                $planned->trip()->update(['driver_id' => $booking->driver_id, 'first_stop_time' => $booking->departure_time, 'last_stop_time' => $booking->return_time]);
            }
            return;
        }

        $route = \App\Models\Route::create(['name' => "Charter {$booking->reference_code}: {$booking->origin} - {$booking->destination}"]);
        $channel = 'charter-' . $booking->id . '-' . bin2hex(random_bytes(4));
        $trip = Trip::create([
            'channel' => $channel, 'route_id' => $route->id, 'effective_date' => $booking->departure_date,
            'repetition_period' => 0, 'stop_to_stop_avg_time' => 0, 'first_stop_time' => $booking->departure_time,
            'last_stop_time' => $booking->return_time, 'status_id' => 1, 'driver_id' => $booking->driver_id,
        ]);
        $planned = PlannedTrip::create([
            'channel' => $channel, 'trip_id' => $trip->id, 'route_id' => $route->id,
            'planned_date' => $booking->departure_date, 'driver_id' => $booking->driver_id,
            'bus_id' => $booking->bus_id, 'reserved_seats' => $booking->passenger_count,
        ]);
        $booking->update(['operational_planned_trip_id' => $planned->id]);
    }

    private function freshBooking(CharterBooking $booking): CharterBooking
    {
        return $booking->fresh(['customer:id,name,email,tel_number', 'bus.depot', 'driver:id,name,email,tel_number', 'depot', 'operationalTrip']);
    }
}
