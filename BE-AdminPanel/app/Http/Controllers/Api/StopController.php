<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repository\StopRepositoryInterface;
use App\Models\FleetDepot;
use App\Models\Stop;
use DB;

class StopController extends Controller
{
    private $stopRepository;

    public function __construct(StopRepositoryInterface $stopRepository)
    {
        $this->stopRepository = $stopRepository;
    }

    public function index(Request $request)
    {
        $category = $request->get('category');
        $query = Stop::with('routes');

        if ($category) {
            $query->where('category', $category);
        }

        $stops = $query->orderBy('name')->get();

        return response()->json($stops, 200);
    }

    public function getStop($stop_id)
    {
        return response()->json($this->stopRepository->findById($stop_id), 200);
    }

    public function createEdit(Request $request)
    {
        $this->validate($request, [
            'stop' => 'required',
            'stop.id' => 'integer|nullable',
            'stop.name' => 'required|string',
            'stop.address' => 'required|string',
            'stop.place_id' => 'nullable|string',
            'stop.lat' => 'required|numeric',
            'stop.lng' => 'required|numeric',
            'stop.category' => 'nullable|string|in:regular,tourist_attraction,terminal,mall,hospital,school',
            'stop.description' => 'nullable|string|max:2000',
        ], [], []);

        $stopData = $request->stop;

        if (!isset($stopData['category'])) {
            $stopData['category'] = 'regular';
        }

        $update = false;
        $stop_id = null;
        if (array_key_exists('id', $stopData) && $stopData['id'] != null) {
            $update = true;
            $stop_id = $stopData['id'];
        }

        if ($update) {
            $this->stopRepository->update($stop_id, $stopData);
            return response()->json(['success' => ['stop updated successfully']]);
        } else {
            $this->stopRepository->create($stopData);
            return response()->json(['success' => ['stop created successfully']]);
        }
    }

    public function destroy($stop_id)
    {
        $stop = $this->stopRepository->findById($stop_id, ['*'], ['routes']);
        if (count($stop->routes) > 0) {
            return response()->json(['message' => 'The stop has routes, you can not delete it'], 400);
        }
        $this->stopRepository->deleteById($stop_id);
        return response()->json(['success' => ['stop deleted successfully']]);
    }

    public function nearbyDepots($stop_id)
    {
        $stop = $this->stopRepository->findById($stop_id);

        if (!$stop) {
            return response()->json(['message' => 'Stop not found'], 404);
        }

        $lat = $stop->lat;
        $lng = $stop->lng;

        $depots = FleetDepot::where('is_active', true)
            ->select('*', DB::raw("
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )) AS distance_km
            "), [$lat, $lng, $lat])
            ->orderBy('distance_km')
            ->limit(5)
            ->withCount('buses')
            ->withCount(['buses as available_buses_count' => function ($query) {
                $query->where('status', 'available');
            }])
            ->get();

        return response()->json([
            'stop' => [
                'id' => $stop->id,
                'name' => $stop->name,
                'lat' => $stop->lat,
                'lng' => $stop->lng,
            ],
            'depots' => $depots,
        ], 200);
    }
}
