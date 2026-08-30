<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\BusType;
use App\Repository\BusRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use DB;
use Log;
class BusController extends Controller
{
    //
    private $busRepository;
    private $driverRepository;
    public function __construct(
        UserRepositoryInterface $driverRepository,
        BusRepositoryInterface $busRepository)
    {
        $this->busRepository = $busRepository;
        $this->driverRepository = $driverRepository;
    }

    public function index()
    {
        //get all buses
        return response()->json($this->busRepository->all(['*'], ['driver', 'depot', 'busType']), 200);
    }

    public function getBus($bus_id)
    {
        //get bus by id
        return response()->json($this->busRepository->findById($bus_id), 200);
    }

    public function createEdit(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'bus' => 'required',
            'bus.id' => 'integer|nullable',
            'bus.fleet_number' => 'required|string|max:30',
            'bus.license' => 'required|string',
            'bus.bus_type_id' => 'required|integer|exists:bus_types,id',
            'bus.seat_config' => 'required',
            'bus.depot_id' => 'nullable|integer|exists:fleet_depots,id',
            'bus.is_active' => 'boolean',
        ], [], []);

        $update = false;
        $bus_id = null;
        if(array_key_exists('id', $request->bus) && $request->bus['id'] != null)
        {
            //update
            $update = true;
                $bus_id = $request->bus['id'];
        }

        $existingFleet = $this->busRepository->findByWhere(['fleet_number' => $request->bus['fleet_number']], ['*'])
            ->where('id', '!=', $bus_id);
        if (!$existingFleet->isEmpty()) {
            return response()->json(['errors' => ['fleet_number' => ['Nomor armada sudah digunakan.']]], 422);
        }

        $busType = BusType::where('id', $request->bus['bus_type_id'])->where('is_active', true)->first();
        if (!$busType) {
            return response()->json(['errors' => ['bus_type_id' => ['Kategori bus tidak aktif atau tidak valid.']]], 422);
        }

        $busData = $request->bus;
        $busData['capacity'] = $busType->capacity;
        $busData['price_factor'] = $busType->price_factor;
        $busData['is_active'] = $busData['is_active'] ?? true;

        if($update)
        {
            //update the bus data
            $this->busRepository->update($bus_id, $busData);
            return response()->json(['success' => ['bus updated successfully']]);
        }
        else
        {
            //create new bus
            $this->busRepository->create($busData);
            return response()->json(['success' => ['bus created successfully']]);
        }
    }

    public function destroy($bus_id)
    {
        //delete bus by id
        $this->busRepository->deleteById($bus_id);
        return response()->json(['success' => ['bus deleted successfully']]);
    }

    public function assignDriver(Request $request)
    {
        $this->validate($request, [
            'driver_id' => 'required|integer',
            'bus_id' => 'required|integer',
        ], [], []);

        // Check if driver is suspended
        $driver = \App\Models\User::find($request->driver_id);
        if ($driver && $driver->status_id == 3) {
            $reason = $driver->suspension_reason ?: 'Tidak ada alasan';
            $until = $driver->suspended_until ? \Carbon\Carbon::parse($driver->suspended_until)->format('d M Y H:i') : '-';
            return response()->json([
                'error' => "Driver ini sedang ditangguhkan sampai {$until}. Alasan: {$reason}"
            ], 400);
        }

        //check if driver is already assigned to another bus
        $driverBus = $this->busRepository->findByWhere(['driver_id' => $request->driver_id], ['*']);
        if(!$driverBus->isEmpty())
        {
            return response()->json(['error' => ['driver is already assigned to another bus']], 400);
        }
        //assign driver to bus
        $this->busRepository->update($request->bus_id,
        [
            'driver_id' => $request->driver_id
        ]);
        return response()->json(['success' => ['bus driver assigned successfully']]);
    }

    //get Available Drivers that are not assigned to any bus
    public function getAvailableDrivers()
    {
        //get all available drivers
        $drivers = $this->getAvailableDriversQuery();
        return response()->json($drivers, 200);
    }

    private function getAvailableDriversQuery()
    {
        $existingBusDrivers = $this->busRepository->all(['driver_id'])->pluck('driver_id')->toArray();
        $existingBusDrivers = array_filter($existingBusDrivers);
        $drivers = $this->driverRepository->findByNotWhereIn('id', [['role', '=', 2]], $existingBusDrivers, ['*']);
        // Exclude suspended drivers
        $drivers = $drivers->filter(function ($driver) {
            return $driver->status_id != 3;
        })->values();
        return $drivers;
    }

    public function busTypes()
    {
        return response()->json([
            'bus_types' => BusType::where('is_active', true)->orderBy('capacity')->get(),
        ]);
    }

    //un-assign driver from bus
    public function unassignDriver(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'bus_id' => 'required|integer',
        ], [], []);

        //un-assign driver from bus
        $this->busRepository->update($request->bus_id,
        [
            'driver_id' => null
        ]);
        return response()->json(['success' => ['bus driver unassigned successfully']]);
    }
}
