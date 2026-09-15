<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\DriverReadiness;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DriverReadinessController extends Controller
{
    public function show(Request $request, ?int $driver = null)
    {
        $id = $driver ?? $request->user()->id;
        $this->authorizeDriver($request, $id);
        return response()->json(['readiness' => (DriverReadiness::where('driver_id', $id)->first()
            ?? new DriverReadiness(['driver_id' => $id, 'health_status' => 'unknown']))->summary()]);
    }

    public function save(Request $request)
    {
        abort_unless((int) $request->user()->role === 2, 403);
        $rules = [
            'sim_number' => 'nullable|string|max:100',
            'health_status' => ['required', Rule::in(['healthy', 'sick'])],
            'health_notes' => 'nullable|required_if:health_status,sick|string|max:2000',
        ];
        foreach (['sim', 'health', 'skck'] as $kind) {
            $rules[$kind . '_valid_until'] = 'nullable|date_format:Y-m-d';
            $rules[$kind . '_file'] = 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120';
        }
        $data = $request->validate($rules);
        $files = []; $oldFiles = [];
        try {
            foreach (['sim', 'health', 'skck'] as $kind) {
                unset($data[$kind . '_file']);
                if ($request->hasFile($kind . '_file')) {
                    $path = $request->file($kind . '_file')->store('driver-readiness/' . $request->user()->id, 'local');
                    if (!$path) throw new \RuntimeException('Dokumen tidak dapat disimpan.');
                    $files[$kind . '_path'] = $path;
                }
            }
            $record = DB::transaction(function () use ($request, $data, $files, &$oldFiles) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $record = DriverReadiness::firstOrNew(['driver_id' => $request->user()->id]);
                foreach ($files as $key => $path) { if ($record->$key) $oldFiles[] = $record->$key; }
                $record->fill(array_merge($data, $files));
                $record->save();
                return $record;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete(array_values($files));
            throw $e;
        }
        Storage::disk('local')->delete($oldFiles);
        return response()->json(['readiness' => $record->summary(), 'message' => 'Kesiapan driver tersimpan dan dapat dilihat admin.']);
    }

    public function document(Request $request, int $driver, string $kind)
    {
        $this->authorizeDriver($request, $driver);
        abort_unless(in_array($kind, ['sim', 'health', 'skck'], true), 404);
        $record = DriverReadiness::where('driver_id', $driver)->firstOrFail();
        $path = $record->{$kind . '_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->download($path, $kind . '.' . pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeDriver(Request $request, int $id): void
    {
        abort_unless((int) $request->user()->role === 0 || ((int) $request->user()->role === 2 && (int) $request->user()->id === $id), 403);
        abort_unless(User::whereKey($id)->where('role', 2)->exists(), 404);
    }
}
