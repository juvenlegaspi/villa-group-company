<?php

namespace App\Http\Controllers;

use App\Models\CrewLocationPortal;
use App\Models\Vessel;
use App\Models\VesselPositionLog;
use App\Models\VoyageLogHeader;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrewVesselLocationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $this->portal($token);
        $vessels = Vessel::query()
            ->whereHas('voyageLogs', fn ($query) => $this->scopeOngoing($query))
            ->orderBy('vessel_name')
            ->get(['id', 'vessel_name']);
        $selectedVesselId = $request->integer('vessel');
        $vessel = $selectedVesselId > 0
            ? $vessels->firstWhere('id', $selectedVesselId)
            : null;
        abort_if($selectedVesselId > 0 && ! $vessel, 404, 'The selected vessel has no ongoing voyage.');
        $voyage = $vessel ? $this->ongoingVoyage($vessel) : null;

        $track = $voyage
            ? $voyage->positionLogs->map(fn (VesselPositionLog $position) => [
                'latitude' => (float) $position->latitude,
                'longitude' => (float) $position->longitude,
                'location_name' => $position->location_name,
                'recorded_at' => $position->recorded_at?->format('M d, Y g:i A'),
                'reporter_name' => $position->reporter_name ?: $position->recorder?->name,
            ])->values()
            : collect();

        $lastPosition = $voyage?->positionLogs->last();
        $lastUpdatedAt = $lastPosition?->recorded_at ?? $voyage?->updated_at;
        $mapPayload = $voyage ? [
            'origin' => [
                'name' => $voyage->port_location,
                'lat' => $voyage->origin_latitude,
                'lng' => $voyage->origin_longitude,
            ],
            'destination' => [
                'name' => $voyage->port_destination,
                'lat' => $voyage->destination_latitude,
                'lng' => $voyage->destination_longitude,
            ],
            'current' => [
                'name' => $voyage->current_location,
                'lat' => $voyage->current_latitude,
                'lng' => $voyage->current_longitude,
            ],
            'track' => $track,
        ] : null;

        return view('shipping.voyage_logs.crew-location', compact(
            'vessel',
            'vessels',
            'voyage',
            'track',
            'lastUpdatedAt',
            'mapPayload',
            'token'
        ));
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $this->portal($token);
        $data = $request->validate([
            'vessel_id' => ['required', 'integer', 'exists:vessels,id'],
            'reporter_name' => ['required', 'string', 'max:120'],
            'location_name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $this->recordLocation($request, $data, 'crew_link', now());

        return redirect()->route('crew-location.show', ['token' => $token, 'vessel' => $data['vessel_id']])
            ->with('success', 'Vessel location updated successfully. Thank you.');
    }

    public function automaticUpdate(Request $request, string $token): JsonResponse
    {
        $this->portal($token);
        $data = $request->validate([
            'vessel_id' => ['required', 'integer', 'exists:vessels,id'],
            'reporter_name' => ['required', 'string', 'max:120'],
            'location_name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'captured_at' => ['required', 'date'],
        ]);
        $capturedAt = Carbon::parse($data['captured_at']);
        if ($capturedAt->isAfter(now()->addMinutes(5)) || $capturedAt->isBefore(now()->subDays(7))) {
            throw ValidationException::withMessages([
                'captured_at' => 'The queued GPS capture time is outside the allowed range.',
            ]);
        }

        $voyage = $this->recordLocation($request, $data, 'crew_auto_gps', $capturedAt);

        return response()->json([
            'message' => 'Automatic GPS location saved.',
            'voyage_id' => $voyage->voyage_id,
            'recorded_at' => $capturedAt->toIso8601String(),
        ]);
    }

    private function recordLocation(Request $request, array $data, string $source, Carbon $recordedAt): VoyageLogHeader
    {
        return DB::transaction(function () use ($request, $data, $source, $recordedAt): VoyageLogHeader {
            $voyage = VoyageLogHeader::query()
                ->where('vessel_id', $data['vessel_id'])
                ->tap(fn ($query) => $this->scopeOngoing($query))
                ->latest('voyage_id')
                ->lockForUpdate()
                ->first();

            abort_unless($voyage, 422, 'This vessel has no ongoing voyage. Its location cannot be updated right now.');

            $locationName = trim($data['location_name']);
            $voyage->update([
                'current_location_id' => null,
                'current_location' => $locationName,
                'current_latitude' => $data['latitude'],
                'current_longitude' => $data['longitude'],
            ]);

            VesselPositionLog::create([
                'vessel_id' => $voyage->vessel_id,
                'voyage_id' => $voyage->voyage_id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'accuracy_meters' => $data['accuracy_meters'] ?? null,
                'location_name' => $locationName,
                'source' => $source,
                'recorded_by' => null,
                'reporter_name' => trim($data['reporter_name']),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'recorded_at' => $recordedAt,
            ]);

            return $voyage;
        });
    }

    public function reverse(Request $request, string $token): JsonResponse
    {
        $this->portal($token);

        return app(MapGeocodingController::class)->reverse($request);
    }

    private function portal(string $token): CrewLocationPortal
    {
        abort_unless(strlen($token) === 64, 404);

        return CrewLocationPortal::query()->where('token', $token)->firstOrFail();
    }

    private function ongoingVoyage(Vessel $vessel): ?VoyageLogHeader
    {
        return VoyageLogHeader::query()
            ->with([
                'positionLogs' => fn ($query) => $query->with('recorder:id,name,lastname')->orderBy('id'),
            ])
            ->where('vessel_id', $vessel->id)
            ->tap(fn ($query) => $this->scopeOngoing($query))
            ->latest('voyage_id')
            ->first();
    }

    private function scopeOngoing($query): void
    {
        $query->whereNull('date_completed')
            ->where(fn ($status) => $status->whereNull('status')->orWhereRaw('UPPER(status) <> ?', ['COMPLETED']));
    }
}
