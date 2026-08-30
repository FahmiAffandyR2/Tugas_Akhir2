<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverShift;
use App\Models\User;
use Illuminate\Http\Request;

class DriverShiftController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'year'  => 'required|integer|min:2024',
            'month' => 'required|integer|between:1,12',
            'driver_id' => 'nullable|integer|exists:users,id',
        ]);

        $query = DriverShift::with('driver:id,name,email')
            ->forMonth($request->year, $request->month);

        if ($request->filled('driver_id')) {
            $query->forDriver($request->driver_id);
        }

        $shifts = $query->orderBy('shift_date')->orderBy('start_time')->get();

        return response()->json($shifts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'driver_id'       => 'required|integer|exists:users,id',
            'shift_name'      => 'required|string|max:100',
            'start_time'      => 'required|date_format:H:i',
            'end_time'        => 'required|date_format:H:i|after:start_time',
            'shift_date'      => 'required|date',
            'planned_trip_id' => 'nullable|integer|exists:planned_trips,id',
            'notes'           => 'nullable|string|max:500',
        ]);

        $validated['status'] = 'scheduled';

        $shift = DriverShift::create($validated);

        return response()->json([
            'message' => 'Shift driver berhasil dibuat.',
            'shift' => $shift,
        ], 201);
    }

    public function update(Request $request, DriverShift $driverShift)
    {
        $validated = $request->validate([
            'shift_name'      => 'required|string|max:100',
            'start_time'      => 'required|date_format:H:i',
            'end_time'        => 'required|date_format:H:i|after:start_time',
            'shift_date'      => 'required|date',
            'status'          => 'required|in:scheduled,active,completed,absent',
            'planned_trip_id' => 'nullable|integer|exists:planned_trips,id',
            'notes'           => 'nullable|string|max:500',
        ]);

        $driverShift->update($validated);

        return response()->json([
            'message' => 'Shift driver berhasil diperbarui.',
            'shift' => $driverShift,
        ]);
    }

    public function destroy(DriverShift $driverShift)
    {
        $driverShift->delete();
        return response()->json(['message' => 'Shift driver berhasil dihapus.']);
    }

    public function drivers()
    {
        $drivers = User::where('role', 2)
            ->where('status_id', 1)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json($drivers);
    }
}
