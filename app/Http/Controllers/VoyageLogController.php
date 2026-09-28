<?php

namespace App\Http\Controllers;

use App\Models\ActivityStatusVoyage;
use App\Models\ActivityVoyage;
use App\Models\FuelRobMonitoring;
use App\Models\Port;
use App\Models\Vessel;
use App\Models\VoyageActivity;
use App\Models\VoyageLogDetail;
use App\Models\VoyageLogHeader;
use App\Models\VesselPositionLog;
use App\Services\VesselAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VoyageLogController extends Controller
{
    public function __construct(private readonly VesselAccessService $vesselAccess) {}

    public function create($vesselId)
    {
        $vessel = Vessel::findOrFail($vesselId);
        $this->authorizeVesselAccess($vessel);

        abort_if(
            VoyageLogHeader::where('vessel_id', $vessel->id)
                ->where('status', '!=', 'COMPLETED')
                ->exists(),
            422,
            'This vessel already has an active voyage.'
        );
        $lastVoyage = VoyageLogHeader::query()->latest('voyage_id')->first();
        $nextId = $lastVoyage ? $lastVoyage->voyage_id + 1 : 1;
        $voyageCode = 'VL-'.str_pad($nextId, 5, '0', STR_PAD_LEFT);
        $ports = Port::where('status', 'ACTIVE')->orderBy('port_name')->get();
        $lastVoyage = VoyageLogHeader::where('vessel_id', $vesselId)->whereNotNull('fuel_rob')->whereNotNull('date_completed')->latest('voyage_id')->first();
        $lastCompletedVoyage = VoyageLogHeader::query()
            ->where('vessel_id', $vesselId)
            ->where('status', 'COMPLETED')
            ->latest('voyage_id')
            ->first();

        return view('shipping.voyage_logs.create', compact('vessel', 'voyageCode', 'ports', 'lastVoyage', 'lastCompletedVoyage'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vessel_id' => 'required|exists:vessels,id',
            'cargo_type' => 'required|string|max:255',
            'cargo_volume' => 'required|numeric|min:0',
            'cargo_unit' => 'required|string|max:20',
            'crew_on_board' => 'required|integer|min:0',
            'port_id' => 'nullable|exists:ports,id',
            'port_destination_id' => 'nullable|exists:ports,id',
            'current_location_id' => 'nullable|exists:ports,id',
            'origin_location_name' => 'nullable|required_without:port_id|string|max:255',
            'destination_location_name' => 'nullable|required_without:port_destination_id|string|max:255',
            'current_location_name' => 'nullable|required_without:current_location_id|string|max:255',
            'origin_latitude' => 'required|numeric|between:-90,90',
            'origin_longitude' => 'required|numeric|between:-180,180',
            'destination_latitude' => 'required|numeric|between:-90,90',
            'destination_longitude' => 'required|numeric|between:-180,180',
            'current_latitude' => 'required|numeric|between:-90,90',
            'current_longitude' => 'required|numeric|between:-180,180',
            'current_accuracy_meters' => 'nullable|numeric|min:0|max:100000',
            'current_position_source' => 'nullable|in:map_pin,device_gps,port,previous_voyage',
            'voyage_no' => 'required|string|max:255',
            'fuel_rob' => 'required|numeric|min:0',
            'arrival_date' => 'nullable|date',
        ]);

        $vessel = Vessel::findOrFail($data['vessel_id']);
        $this->authorizeVesselAccess($vessel);

        $port = filled($data['port_id'] ?? null) ? Port::findOrFail($data['port_id']) : null;
        $portDestination = filled($data['port_destination_id'] ?? null) ? Port::findOrFail($data['port_destination_id']) : null;
        $currentLocation = filled($data['current_location_id'] ?? null) ? Port::findOrFail($data['current_location_id']) : null;
        $originName = trim((string) ($data['origin_location_name'] ?? $port?->port_name));
        $destinationName = trim((string) ($data['destination_location_name'] ?? $portDestination?->port_name));
        $currentName = trim((string) ($data['current_location_name'] ?? $currentLocation?->port_name));
        $cargoVolume = $data['cargo_volume'].' '.$data['cargo_unit'];

        $voyage = DB::transaction(function () use ($data, $port, $portDestination, $currentLocation, $originName, $destinationName, $currentName, $cargoVolume) {
            $hasOpenVoyage = VoyageLogHeader::where('vessel_id', $data['vessel_id'])
                ->where('status', '!=', 'COMPLETED')
                ->lockForUpdate()
                ->exists();

            if ($hasOpenVoyage) {
                throw ValidationException::withMessages([
                    'vessel_id' => 'This vessel already has an active voyage.',
                ]);
            }

            $voyage = VoyageLogHeader::create([
                ...$data,
                'port_id' => $port?->id,
                'port_destination_id' => $portDestination?->id,
                'current_location_id' => $currentLocation?->id,
                'port_location' => $originName,
                'port_destination' => $destinationName,
                'current_location' => $currentName,
                'date_created' => now()->toDateString(),
                'fuel_rob' => $data['fuel_rob'].' Liters',
                'cargo_volume' => $cargoVolume,
                'status' => 'OPEN',
                'created_by' => auth()->id(),
            ]);

            VesselPositionLog::create([
                'vessel_id' => $voyage->vessel_id,
                'voyage_id' => $voyage->voyage_id,
                'latitude' => $data['current_latitude'],
                'longitude' => $data['current_longitude'],
                'accuracy_meters' => $data['current_accuracy_meters'] ?? null,
                'location_name' => $currentName,
                'source' => $data['current_position_source'] ?? 'map_pin',
                'recorded_by' => auth()->id(),
                'recorded_at' => now(),
            ]);

            // A port's coordinates are stable master data. Populate only empty
            // coordinates so an older voyage cannot silently move a known port.
            if ($port) {
                $this->rememberPortCoordinates($port, $data['origin_latitude'], $data['origin_longitude']);
            }
            if ($portDestination) {
                $this->rememberPortCoordinates($portDestination, $data['destination_latitude'], $data['destination_longitude']);
            }

            return $voyage;
        });

        return redirect('/shipping/voyage-logs/'.$voyage->voyage_id);
    }

    public function show($id)
    {
        $statuses = ActivityStatusVoyage::where('status', 1)->get();

        $activities = \App\Models\ActivityVoyage::where('status', 1)
            ->get();

        $voyage = VoyageLogHeader::with([
            'vessel',
            'details.activities.activity',
            // A point may carry a backdated operational timestamp. The route
            // itself must follow the order in which points were saved, or a
            // newly created voyage can jump to an old point after completion.
            'positionLogs' => fn ($query) => $query->orderBy('id'),
        ])->findOrFail($id);
        $this->authorizeVoyageAccess($voyage);

        $ports = Port::where('status', 'ACTIVE')->orderBy('port_name')->get();

        return view('shipping.voyage_logs.show', compact('voyage', 'statuses', 'activities', 'ports'));
    }

    public function addDetail(Request $request, $id)
    {
        $data = $this->validateDetailRequest($request, true);

        $voyage = VoyageLogHeader::findOrFail($id);
        $this->authorizeVoyageAccess($voyage);
        abort_if($voyage->status === 'COMPLETED', 422, 'A completed voyage cannot be changed.');

        DB::transaction(function () use ($data, $voyage): void {
            $lockedVoyage = VoyageLogHeader::whereKey($voyage->voyage_id)->lockForUpdate()->firstOrFail();

            abort_if(
                $lockedVoyage->details()->where('main_status', '!=', 'COMPLETED')->exists(),
                422,
                'Complete the current voyage status before adding another one.'
            );

            VoyageLogDetail::create([
                'voyage_id' => $lockedVoyage->voyage_id,
                'vessel_id' => $lockedVoyage->vessel_id,
                'remarks' => $data['remarks'] ?? null,
                'status' => $data['status_id'],
                'main_status' => 'ONGOING',
            ]);

            $statusName = ActivityStatusVoyage::findOrFail($data['status_id']);
            $lockedVoyage->update(['status' => $statusName->name]);
        });

        return back()->with('success', 'Detail added successfully.');
    }

    public function addActivity(Request $request, $detailId)
    {
        $data = $request->validate([
            'activity_id' => 'required|exists:activity_voyage,id',
            'cargo_type' => 'nullable|string|max:255',
            'port_location_id' => 'nullable|exists:ports,id',
            'activity_location_name' => 'required|string|max:255',
            'activity_latitude' => 'required|numeric|between:-90,90',
            'activity_longitude' => 'required|numeric|between:-180,180',
            'running_load' => 'nullable|numeric|min:0.01',
            'load_unit' => 'nullable|string|max:20',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $activityDefinition = ActivityVoyage::findOrFail($data['activity_id']);
        $cargoMovementType = $activityDefinition->cargoMovementType();
        $isLoading = $cargoMovementType === 'loading';
        $isUnloading = $cargoMovementType === 'unloading';

        if (($isLoading || $isUnloading) && (empty($data['running_load']) || empty($data['load_unit']))) {
            throw ValidationException::withMessages([
                'running_load' => 'Cargo quantity and unit are required for loading or unloading.',
            ]);
        }

        DB::transaction(function () use ($data, $detailId, $isLoading, $isUnloading): void {
            $detail = VoyageLogDetail::whereKey($detailId)->lockForUpdate()->firstOrFail();
            $voyage = VoyageLogHeader::whereKey($detail->voyage_id)->lockForUpdate()->firstOrFail();
            $this->authorizeVoyageAccess($voyage);

            abort_if($voyage->status === 'COMPLETED' || $detail->main_status === 'COMPLETED', 422, 'Completed records cannot be changed.');
            abort_if(
                VoyageActivity::where('voyage_id', $voyage->voyage_id)->whereNull('end_date_time')->exists(),
                422,
                'End the running activity before starting another one.'
            );

            $selectedPort = ! empty($data['port_location_id']) ? Port::find($data['port_location_id']) : null;
            $activityLocationName = trim($data['activity_location_name']);
            $activityLatitude = (float) $data['activity_latitude'];
            $activityLongitude = (float) $data['activity_longitude'];
            // Preserve the vessel's operational timeline across voyages. For a
            // new voyage, its first activity starts where the vessel's last
            // ended activity from the previous voyage stopped.
            $lastEndedActivity = VoyageActivity::where('vessel_id', $voyage->vessel_id)
                ->whereNotNull('end_date_time')->latest('end_date_time')->first();
            $quantity = (float) ($data['running_load'] ?? 0);
            $lastLoad = (float) (VoyageActivity::where('voyage_id', $voyage->voyage_id)->whereNotNull('total_load')->latest('activity_id')->value('total_load') ?? 0);
            $lastUnload = (float) (VoyageActivity::where('voyage_id', $voyage->voyage_id)->whereNotNull('total_unload')->latest('activity_id')->value('total_unload') ?? 0);

            $activity = VoyageActivity::create([
                'voyage_id' => $detail->voyage_id,
                'voyage_detail_id' => $detail->dtl_id,
                'vessel_id' => $voyage->vessel_id,
                'status_id' => $detail->status,
                'status_activity_id' => $data['activity_id'],
                'port_location' => $activityLocationName,
                'remarks' => $data['remarks'] ?? null,
                'start_date_time' => $lastEndedActivity?->end_date_time ?? now(),
                'cargo_load' => $isLoading ? $quantity : null,
                'total_load' => $isLoading ? $lastLoad + $quantity : null,
                'cargo_unload' => $isUnloading ? $quantity : null,
                'total_unload' => $isUnloading ? $lastUnload + $quantity : null,
                'load_unit' => $data['load_unit'] ?? null,
                'main_status' => 'ONGOING',
            ]);

            $updateData = [
                'current_location_id' => $selectedPort?->id,
                'current_location' => $activityLocationName,
            ];

            if ($activityLatitude !== null && $activityLongitude !== null) {
                $updateData['current_latitude'] = $activityLatitude;
                $updateData['current_longitude'] = $activityLongitude;

                VesselPositionLog::create([
                    'vessel_id' => $voyage->vessel_id,
                    'voyage_id' => $voyage->voyage_id,
                    'voyage_activity_id' => $activity->activity_id,
                    'latitude' => $activityLatitude,
                    'longitude' => $activityLongitude,
                    'location_name' => $activityLocationName,
                    'source' => 'map_pin',
                    'recorded_by' => auth()->id(),
                    'recorded_at' => $activity->start_date_time,
                ]);
            }

            if ($selectedPort) {
                $this->rememberPortCoordinates($selectedPort, $activityLatitude, $activityLongitude);
            }

            if ($isLoading || $isUnloading) {
                preg_match('/[\d.]+/', (string) $voyage->cargo_volume, $matches);
                $currentCargo = (float) ($matches[0] ?? 0);
                $newCargo = $isLoading ? $currentCargo + $quantity : max(0, $currentCargo - $quantity);
                $updateData['cargo_volume'] = $newCargo.' '.$data['load_unit'];
                if (! empty($data['cargo_type'])) {
                    $updateData['cargo_type'] = strtoupper($data['cargo_type']);
                }
            }

            $voyage->update($updateData);
        });

        return back()->with('success', 'Activity added successfully.');
    }

    public function endActivity(Request $request, $id)
    {
        $data = $request->validate([
            'end_date' => 'required|date',
            'end_time' => 'required|date_format:H:i',
        ]);

        $activity = VoyageActivity::with('voyage.vessel')->findOrFail($id);
        $this->authorizeVoyageAccess($activity->voyage);
        abort_if($activity->end_date_time, 422, 'This activity has already ended.');

        $endDateTime = $data['end_date'].' '.$data['end_time'];
        $start = Carbon::parse($activity->start_date_time);
        $end = Carbon::parse($endDateTime);
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'end_time' => 'The end date and time must be after the activity start.',
            ]);
        }
        $totalHours = $start->diffInMinutes($end) / 60;
        $activity->update([
            'end_date_time' => $endDateTime,
            'total_hours' => $totalHours,
            'main_status' => 'COMPLETED',
        ]);

        return back()
            ->with('activityEnded', true)
            ->with('ended_activity_id', $activity->activity_id);
    }

    public function updateActivity(Request $request, $id)
    {
        $data = $request->validate([
            'end_date' => 'required|date',
            'end_time' => 'required|date_format:H:i',
            'edit_reason' => 'required|string|max:1000',
            'edit_attachment' => 'nullable|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
        ]);

        $activity = VoyageActivity::with('voyage.vessel')->findOrFail($id);
        $this->authorizeVoyageAccess($activity->voyage);
        $endDateTime = $data['end_date'].' '.$data['end_time'];
        $start = Carbon::parse($activity->start_date_time);
        $end = Carbon::parse($endDateTime);

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'end_time' => 'The end date and time must be after the activity start.',
            ]);
        }

        $totalHours = $start->diffInMinutes($end) / 60;
        $attachment = $activity->edit_attachment;

        if ($request->hasFile('edit_attachment')) {
            $file = $request->file('edit_attachment');
            $filename = (string) str()->uuid().'.'.$file->getClientOriginalExtension();
            Storage::disk('local')->putFileAs('voyage_activity_edits', $file, $filename);
            $attachment = 'voyage_activity_edits/'.$filename;
        }

        $activity->update([
            'edited_end_date_time' => $endDateTime,
            'edit_reason' => $data['edit_reason'],
            'edit_attachment' => $attachment,
            'edited_at' => now(),

            'end_date_time' => $endDateTime,
            'total_hours' => $totalHours,
        ]);

        return back()->with('success', 'Activity updated successfully.');
    }

    public function downloadActivityAttachment($id): BinaryFileResponse
    {
        $activity = VoyageActivity::with('voyage.vessel')->findOrFail($id);
        $this->authorizeVoyageAccess($activity->voyage);
        abort_unless($activity->edit_attachment, 404);

        if (Storage::disk('local')->exists($activity->edit_attachment)) {
            return response()->download(Storage::disk('local')->path($activity->edit_attachment));
        }

        $legacyPath = storage_path('app/public/'.ltrim($activity->edit_attachment, '/'));
        abort_unless(is_file($legacyPath), 404);

        return response()->download($legacyPath);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status_id' => 'required|exists:activity_status_voyage,id',
        ]);

        $detail = VoyageLogDetail::findOrFail($id);
        $voyage = VoyageLogHeader::findOrFail($detail->voyage_id);
        $this->authorizeVoyageAccess($voyage);
        abort_if($detail->main_status === 'COMPLETED' || $voyage->status === 'COMPLETED', 422, 'Completed records cannot be changed.');

        $detail->update([
            'status' => $request->status_id,
        ]);

        return back()->with('success', 'Status updated successfully.');
    }

    public function completeStatus(Request $request, $id)
    {
        $data = $request->validate([
            'completion_location_name' => 'required|string|max:255',
            'completion_latitude' => 'required|numeric|between:-90,90',
            'completion_longitude' => 'required|numeric|between:-180,180',
        ]);

        DB::transaction(function () use ($data, $id): void {
            $detail = VoyageLogDetail::whereKey($id)->lockForUpdate()->firstOrFail();
            $voyage = VoyageLogHeader::whereKey($detail->voyage_id)->lockForUpdate()->firstOrFail();
            $this->authorizeVoyageAccess($voyage);
            abort_if($voyage->status === 'COMPLETED', 422, 'The voyage is already completed.');
            abort_if($detail->main_status === 'COMPLETED', 422, 'This voyage status is already completed.');

            if ($detail->activities()->whereNull('end_date_time')->exists()) {
                throw ValidationException::withMessages([
                    'completion_location_name' => 'Finish all running activities before completing this status.',
                ]);
            }

            $totalHours = $detail->activities()
                ->whereNotNull('start_date_time')
                ->whereNotNull('end_date_time')
                ->get()
                ->sum(fn ($activity) => Carbon::parse($activity->start_date_time)->diffInMinutes(Carbon::parse($activity->end_date_time)) / 60);
            $latestEnteredEnd = $detail->activities()->whereNotNull('end_date_time')->max('end_date_time');

            $detail->update([
                'date_complete' => $latestEnteredEnd ?: now(),
                'total_hours' => $totalHours,
                'main_status' => 'COMPLETED',
            ]);
            VoyageActivity::where('voyage_detail_id', $detail->dtl_id)
                ->update(['main_status' => 'COMPLETED']);

            $voyage->update([
                'current_location_id' => null,
                'current_location' => trim($data['completion_location_name']),
                'current_latitude' => $data['completion_latitude'],
                'current_longitude' => $data['completion_longitude'],
            ]);

            VesselPositionLog::create([
                'vessel_id' => $voyage->vessel_id,
                'voyage_id' => $voyage->voyage_id,
                'latitude' => $data['completion_latitude'],
                'longitude' => $data['completion_longitude'],
                'location_name' => trim($data['completion_location_name']),
                'source' => 'status_completion',
                'recorded_by' => auth()->id(),
                'recorded_at' => $latestEnteredEnd ?: now(),
            ]);
        });

        return back()->with('success', 'Status completed successfully.')->with('openAddStatus', true);
    }

    public function completeVoyage(Request $request, $id)
    {
        $data = $request->validate([
            'final_location_name' => 'required|string|max:255',
            'final_latitude' => 'required|numeric|between:-90,90',
            'final_longitude' => 'required|numeric|between:-180,180',
        ]);

        DB::transaction(function () use ($data, $id): void {
            $voyage = VoyageLogHeader::with('details')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorizeVoyageAccess($voyage);
            abort_if(strtoupper((string) $voyage->status) === 'COMPLETED', 422, 'The voyage is already completed.');
            abort_if($voyage->details->isEmpty(), 422, 'Add and complete at least one voyage status first.');
            abort_if(
                $voyage->details->contains(fn ($detail) => $detail->main_status !== 'COMPLETED'),
                422,
                'Complete every voyage status before completing the voyage.'
            );

            $finalLocationName = trim($data['final_location_name']);
            $totalHours = VoyageActivity::where('voyage_id', $voyage->voyage_id)->sum('total_hours');
            $latestEnteredEnd = VoyageActivity::where('voyage_id', $voyage->voyage_id)
                ->whereNotNull('end_date_time')
                ->max('end_date_time');
            $voyage->update([
                'status' => 'COMPLETED',
                'date_completed' => $latestEnteredEnd
                    ? Carbon::parse($latestEnteredEnd)->toDateString()
                    : now()->toDateString(),
                'total_hours_voyage' => $totalHours,
                'current_location_id' => null,
                'current_location' => $finalLocationName,
                'current_latitude' => $data['final_latitude'],
                'current_longitude' => $data['final_longitude'],
            ]);

            VesselPositionLog::create([
                'vessel_id' => $voyage->vessel_id,
                'voyage_id' => $voyage->voyage_id,
                'latitude' => $data['final_latitude'],
                'longitude' => $data['final_longitude'],
                'location_name' => $finalLocationName,
                'source' => 'voyage_completion',
                'recorded_by' => auth()->id(),
                'recorded_at' => $latestEnteredEnd ?: now(),
            ]);
        });

        return back()->with('success', 'Voyage completed successfully.');
    }

    public function updateCurrentLocation(Request $request, $id)
    {
        $data = $request->validate([
            'location_name' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        DB::transaction(function () use ($data, $id): void {
            $voyage = VoyageLogHeader::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorizeVoyageAccess($voyage);
            abort_if(
                strtoupper((string) $voyage->status) === 'COMPLETED',
                422,
                'A completed voyage location can no longer be changed.'
            );

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
                'location_name' => $locationName,
                'source' => 'manual_update',
                'recorded_by' => auth()->id(),
                'recorded_at' => now(),
            ]);
        });

        return back()->with('success', 'Current vessel location updated and added to the tracking history.');
    }

    public function exportPdf($id)
    {
        $voyage = VoyageLogHeader::with([
            'details.activities.activity',
            'details.statusRelation',
            'vessel',
            'creator',
            'fuelMonitorings',
        ])->findOrFail($id);
        $this->authorizeVoyageAccess($voyage);

        $pdf = Pdf::loadView(
            'shipping.voyage_logs.pdf',
            compact('voyage')
        )->setPaper('a4', 'portrait');

        return $pdf->download(
            'voyage-log-'.$voyage->voyage_no.'.pdf'
        );
    }

    // ===============================
    // VOYAGE LOG DASHBOARD CONTROLLER
    // ===============================

    public function dashboard()
    {
        abort_unless(
            auth()->user()->isExecutiveViewer() || $this->vesselAccess->canAccessAllVessels(auth()->user()),
            403,
            'The consolidated voyage dashboard is available only to executive viewers and authorized managers.'
        );
        $monthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%m', date_created)",
            'pgsql' => 'EXTRACT(MONTH FROM date_created)',
            default => 'MONTH(date_created)',
        };
        $currentMonthStart = now()->copy()->startOfMonth();
        $currentMonthEnd = now()->copy()->endOfMonth();
        $currentMonthLabel = strtoupper($currentMonthStart->format('F'));
        $normalizeLocation = fn ($value) => filled(trim((string) $value)) ? trim((string) $value) : 'Unassigned';

        $totalVoyages = VoyageLogHeader::count();
        $activeVoyages = VoyageLogHeader::where('status', 'OPEN')->count();
        $completedVoyages = VoyageLogHeader::where('status', 'COMPLETED')->count();
        $monthlyVoyageSummary = VoyageLogHeader::whereBetween('date_created', [
            $currentMonthStart->toDateString(),
            $currentMonthEnd->toDateString(),
        ])->count();

        $monthlyVoyageTrend = VoyageLogHeader::selectRaw("{$monthExpression} as month_num, COUNT(*) as total")
            ->whereYear('date_created', $currentMonthStart->year)
            ->groupBy('month_num')
            ->orderBy('month_num')
            ->get()
            ->map(fn ($row) => [
                'label' => Carbon::create()->month((int) $row->month_num)->format('M'),
                'total' => (int) $row->total,
            ]);

        $monthlyVoyagesPerVessel = VoyageLogHeader::with('vessel')
            ->whereBetween('date_created', [
                $currentMonthStart->toDateString(),
                $currentMonthEnd->toDateString(),
            ])
            ->selectRaw('vessel_id, COUNT(*) as total_voyages, SUM(COALESCE(total_hours_voyage, 0)) as total_voyage_hours')
            ->groupBy('vessel_id')
            ->orderByDesc('total_voyages')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'total_voyages' => (int) ($row->total_voyages ?? 0),
                    'total_voyage_hours' => round((float) ($row->total_voyage_hours ?? 0), 2),
                ];
            });

        $monthlyFuelByVessel = FuelRobMonitoring::with('vessel')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->selectRaw('vessel_id, SUM(total_consumed) as total_consumed, SUM(received_fuel) as total_received, AVG(NULLIF(total_consumed, 0)) as average_consumed')
            ->groupBy('vessel_id')
            ->orderByDesc('total_consumed')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'total_consumed' => round((float) ($row->total_consumed ?? 0), 2),
                    'total_received' => round((float) ($row->total_received ?? 0), 2),
                    'average_consumed' => round((float) ($row->average_consumed ?? 0), 2),
                ];
            });

        $fuelConsumptionByEngine = [
            'Main Engine' => (float) FuelRobMonitoring::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])->sum('main_engine'),
            'Auxiliary' => (float) FuelRobMonitoring::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])->sum('auxiliary_engine'),
            'Boiler' => (float) FuelRobMonitoring::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])->sum('boiler'),
            'Others' => (float) FuelRobMonitoring::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])->sum('others'),
        ];

        $turnaroundPerPort = VoyageLogHeader::query()
            ->whereBetween('date_created', [
                $currentMonthStart->toDateString(),
                $currentMonthEnd->toDateString(),
            ])
            ->selectRaw('port_location, COUNT(*) as total_voyages, AVG(NULLIF(total_hours_voyage, 0)) as average_turnaround_hours, SUM(COALESCE(total_hours_voyage, 0)) as total_turnaround_hours')
            ->groupBy('port_location')
            ->orderByDesc('average_turnaround_hours')
            ->get()
            ->groupBy(fn ($row) => $normalizeLocation($row->port_location))
            ->map(function ($rows, $locationName) {
                $totalVoyages = (int) $rows->sum('total_voyages');
                $totalHours = (float) $rows->sum('total_turnaround_hours');

                return [
                    'location_name' => $locationName,
                    'total_voyages' => $totalVoyages,
                    'average_turnaround_hours' => $totalVoyages > 0 ? round($totalHours / $totalVoyages, 2) : 0,
                    'total_turnaround_hours' => round($totalHours, 2),
                ];
            })
            ->sortByDesc('average_turnaround_hours')
            ->take(8)
            ->values();

        $loadingDurationByVessel = VoyageActivity::with('vessel')
            ->whereBetween('start_date_time', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('activity', function ($query) {
                $query->whereRaw("LOWER(name) LIKE '%load%'")
                    ->whereRaw("LOWER(name) NOT LIKE '%unload%'");
            })
            ->selectRaw('vessel_id, COUNT(*) as total_activities, SUM(COALESCE(total_hours, 0)) as total_duration_hours, AVG(NULLIF(total_hours, 0)) as average_duration_hours')
            ->groupBy('vessel_id')
            ->orderByDesc('total_duration_hours')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'total_activities' => (int) ($row->total_activities ?? 0),
                    'total_duration_hours' => round((float) ($row->total_duration_hours ?? 0), 2),
                    'average_duration_hours' => round((float) ($row->average_duration_hours ?? 0), 2),
                ];
            });

        $unloadingDurationByVessel = VoyageActivity::with('vessel')
            ->whereBetween('start_date_time', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('activity', function ($query) {
                $query->whereRaw("LOWER(name) LIKE '%unload%'");
            })
            ->selectRaw('vessel_id, COUNT(*) as total_activities, SUM(COALESCE(total_hours, 0)) as total_duration_hours, AVG(NULLIF(total_hours, 0)) as average_duration_hours')
            ->groupBy('vessel_id')
            ->orderByDesc('total_duration_hours')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'total_activities' => (int) ($row->total_activities ?? 0),
                    'total_duration_hours' => round((float) ($row->total_duration_hours ?? 0), 2),
                    'average_duration_hours' => round((float) ($row->average_duration_hours ?? 0), 2),
                ];
            });

        $loadingByVesselMap = $loadingDurationByVessel->keyBy('vessel_name');
        $unloadingByVesselMap = $unloadingDurationByVessel->keyBy('vessel_name');
        $loadingUnloadingLabels = $loadingDurationByVessel->pluck('vessel_name')
            ->merge($unloadingDurationByVessel->pluck('vessel_name'))
            ->unique()
            ->values();

        $loadingDurationChartData = $loadingUnloadingLabels
            ->map(fn ($vesselName) => (float) ($loadingByVesselMap->get($vesselName)['total_duration_hours'] ?? 0))
            ->values();

        $unloadingDurationChartData = $loadingUnloadingLabels
            ->map(fn ($vesselName) => (float) ($unloadingByVesselMap->get($vesselName)['total_duration_hours'] ?? 0))
            ->values();

        return view('shipping.voyage_logs.dashboard', [
            'totalVoyages' => $totalVoyages,
            'activeVoyages' => $activeVoyages,
            'completedVoyages' => $completedVoyages,
            'currentMonthLabel' => $currentMonthLabel,
            'monthlyVoyageSummary' => $monthlyVoyageSummary,
            'monthlyVoyageTrend' => $monthlyVoyageTrend,
            'monthlyVoyagesPerVessel' => $monthlyVoyagesPerVessel,
            'monthlyVoyageVesselLabels' => $monthlyVoyagesPerVessel->pluck('vessel_name')->values(),
            'monthlyVoyageVesselData' => $monthlyVoyagesPerVessel->pluck('total_voyages')->values(),
            'monthlyFuelByVessel' => $monthlyFuelByVessel,
            'monthlyFuelVesselLabels' => $monthlyFuelByVessel->pluck('vessel_name')->values(),
            'monthlyFuelVesselData' => $monthlyFuelByVessel->pluck('total_consumed')->values(),
            'fuelEngineLabels' => array_keys($fuelConsumptionByEngine),
            'fuelEngineData' => array_values($fuelConsumptionByEngine),
            'turnaroundPerPort' => $turnaroundPerPort,
            'turnaroundPortLabels' => $turnaroundPerPort->pluck('location_name')->values(),
            'turnaroundPortData' => $turnaroundPerPort->pluck('average_turnaround_hours')->values(),
            'loadingDurationByVessel' => $loadingDurationByVessel,
            'unloadingDurationByVessel' => $unloadingDurationByVessel,
            'loadingUnloadingLabels' => $loadingUnloadingLabels,
            'loadingDurationChartData' => $loadingDurationChartData,
            'unloadingDurationChartData' => $unloadingDurationChartData,
        ]);
    }

    public function fleetMap(Request $request)
    {
        $accessibleVesselIds = $this->vesselAccess
            ->scopeAccessible(Vessel::query(), auth()->user())
            ->pluck('id');

        $backUrl = route('vessels.index');
        $backLabel = 'Back to Vessels';
        $selectedVoyageId = null;
        $selectedVesselId = null;

        if ($request->filled('voyage')) {
            $returnVoyage = VoyageLogHeader::query()
                ->whereKey((int) $request->query('voyage'))
                ->whereIn('vessel_id', $accessibleVesselIds)
                ->first();

            if ($returnVoyage) {
                $backUrl = route('voyages.show', $returnVoyage->voyage_id);
                $backLabel = 'Back to Voyage';
                $selectedVoyageId = $returnVoyage->voyage_id;
            }
        } elseif ($request->filled('vessel')) {
            $returnVessel = $this->vesselAccess
                ->scopeAccessible(Vessel::query(), auth()->user())
                ->whereKey((int) $request->query('vessel'))
                ->first();

            if ($returnVessel) {
                $backUrl = route('vessels.show', $returnVessel->id);
                $backLabel = 'Back to Voyage Records';
                $selectedVesselId = $returnVessel->id;
            }
        }

        $voyageQuery = VoyageLogHeader::query()
            ->with([
                'vessel',
                // Draw the track in persisted sequence. recorded_at remains
                // the operational/reporting time and can legitimately be
                // earlier than a previously saved point.
                'positionLogs' => fn ($query) => $query->orderBy('id'),
                'fuelMonitorings:fuel_id,voyage_id,total_consumed',
                'details.activities',
            ])
            ->whereIn('vessel_id', $accessibleVesselIds)
            ->latest('voyage_id');

        if ($selectedVoyageId !== null) {
            $voyageQuery->whereKey($selectedVoyageId);
        } elseif ($selectedVesselId !== null) {
            $voyageQuery->where('vessel_id', $selectedVesselId);
        }

        // Completed voyages remain available as permanent historical tracks.
        // A new voyage is rendered as a separate track instead of replacing history.
        $voyages = $voyageQuery->get();
        $voyageMetrics = $voyages->mapWithKeys(fn (VoyageLogHeader $voyage) => [
            $voyage->voyage_id => $this->buildVoyageMapMetrics($voyage),
        ]);

        return view('shipping.voyage_logs.fleet-map', compact('voyages', 'voyageMetrics', 'backUrl', 'backLabel', 'selectedVoyageId'));
    }

    protected function buildVoyageMapMetrics(VoyageLogHeader $voyage): array
    {
        $trackPoints = [];
        $appendPoint = function ($latitude, $longitude) use (&$trackPoints): void {
            $latitude = is_numeric($latitude) ? (float) $latitude : null;
            $longitude = is_numeric($longitude) ? (float) $longitude : null;
            if ($latitude === null || $longitude === null) {
                return;
            }

            $last = end($trackPoints);
            if ($last === false || $last[0] !== $latitude || $last[1] !== $longitude) {
                $trackPoints[] = [$latitude, $longitude];
            }
        };

        $appendPoint($voyage->origin_latitude, $voyage->origin_longitude);
        foreach ($voyage->positionLogs as $position) {
            $appendPoint($position->latitude, $position->longitude);
        }
        $appendPoint($voyage->current_latitude, $voyage->current_longitude);

        $distanceNm = 0.0;
        for ($index = 1; $index < count($trackPoints); $index++) {
            $distanceNm += $this->distanceInNauticalMiles($trackPoints[$index - 1], $trackPoints[$index]);
        }

        $activities = $voyage->details
            ->flatMap->activities
            ->filter(fn (VoyageActivity $activity) => $activity->start_date_time !== null);
        $firstActivityAt = $activities->min('start_date_time');
        $finalActivityAt = $activities
            ->whereNotNull('end_date_time')
            ->max('end_date_time');
        // Visual route order is insertion order, while elapsed-time reporting
        // still uses the earliest/latest entered operational timestamps.
        $firstReportAt = $voyage->positionLogs->min('recorded_at');
        $finalReportAt = $voyage->positionLogs->max('recorded_at');
        $elapsedStartAt = $firstActivityAt ? Carbon::parse($firstActivityAt) : $firstReportAt;
        $elapsedEndAt = $finalActivityAt ? Carbon::parse($finalActivityAt) : $finalReportAt;
        $elapsedHours = null;
        if ($elapsedStartAt && $elapsedEndAt && $elapsedEndAt->greaterThan($elapsedStartAt)) {
            $elapsedHours = $elapsedStartAt->diffInSeconds($elapsedEndAt) / 3600;
        }

        $directDistanceNm = null;
        if (
            is_numeric($voyage->origin_latitude) && is_numeric($voyage->origin_longitude)
            && is_numeric($voyage->destination_latitude) && is_numeric($voyage->destination_longitude)
        ) {
            $directDistanceNm = $this->distanceInNauticalMiles(
                [(float) $voyage->origin_latitude, (float) $voyage->origin_longitude],
                [(float) $voyage->destination_latitude, (float) $voyage->destination_longitude]
            );
        }

        $plannedHours = null;
        $plannedStartAt = $elapsedStartAt ?? $voyage->created_at;
        if ($plannedStartAt && $voyage->arrival_date && $voyage->arrival_date->greaterThan($plannedStartAt)) {
            $plannedHours = $plannedStartAt->diffInSeconds($voyage->arrival_date) / 3600;
        }

        $etaPerformance = 'No ETA comparison';
        $etaTone = 'neutral';
        $actualCompletionAt = $elapsedEndAt;
        if ($voyage->arrival_date && $actualCompletionAt && strtoupper((string) $voyage->status) === 'COMPLETED') {
            $varianceMinutes = (int) round($voyage->arrival_date->diffInMinutes($actualCompletionAt, false));
            if ($varianceMinutes > 0) {
                $etaPerformance = 'Delayed by '.$this->formatMinutes($varianceMinutes);
                $etaTone = 'late';
            } else {
                $etaPerformance = $varianceMinutes < 0
                    ? 'Early by '.$this->formatMinutes(abs($varianceMinutes))
                    : 'Arrived on time';
                $etaTone = 'on-time';
            }
        }

        return [
            'distance_nm' => count($trackPoints) > 1 ? round($distanceNm, 2) : null,
            'elapsed' => $elapsedHours !== null
                ? $this->formatMinutes((int) round($elapsedHours * 60))
                : ($voyage->total_hours_voyage !== null ? number_format((float) $voyage->total_hours_voyage, 2).' operational hours' : null),
            'average_speed_knots' => $elapsedHours > 0 && count($trackPoints) > 1 ? round($distanceNm / $elapsedHours, 2) : null,
            'required_speed_knots' => $plannedHours > 0 && $directDistanceNm !== null ? round($directDistanceNm / $plannedHours, 2) : null,
            'fuel_consumed' => $voyage->fuelMonitorings->isNotEmpty()
                ? round((float) $voyage->fuelMonitorings->sum('total_consumed'), 2)
                : null,
            'cargo' => trim(implode(' - ', array_filter([$voyage->cargo_type, $voyage->cargo_volume]))) ?: null,
            'eta' => $voyage->arrival_date?->format('M d, Y h:i A'),
            'completed_at' => $actualCompletionAt?->format('M d, Y h:i A') ?? $voyage->date_completed?->format('M d, Y'),
            'time_basis' => $firstActivityAt && $finalActivityAt ? 'Entered activity times' : 'Position report timestamps',
            'eta_performance' => $etaPerformance,
            'eta_tone' => $etaTone,
        ];
    }

    protected function distanceInNauticalMiles(array $start, array $end): float
    {
        $latitudeDelta = deg2rad($end[0] - $start[0]);
        $longitudeDelta = deg2rad($end[1] - $start[1]);
        $startLatitude = deg2rad($start[0]);
        $endLatitude = deg2rad($end[0]);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($startLatitude) * cos($endLatitude) * sin($longitudeDelta / 2) ** 2;

        return 3440.065 * 2 * asin(min(1, sqrt($haversine)));
    }

    protected function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $hours > 0
            ? $hours.'h '.str_pad((string) $remainingMinutes, 2, '0', STR_PAD_LEFT).'m'
            : $remainingMinutes.'m';
    }

    protected function validateDetailRequest(Request $request, bool $allowManualDates = false): array
    {
        return $request->validate([
            'status_id' => 'required|exists:activity_status_voyage,id',
            'remarks' => 'nullable|string|max:255',
        ]);
    }

    protected function authorizeVesselAccess(Vessel $vessel): void
    {
        $this->vesselAccess->authorize(auth()->user(), $vessel);
    }

    protected function authorizeVoyageAccess(VoyageLogHeader $voyage): void
    {
        $voyage->loadMissing('vessel');
        $this->authorizeVesselAccess($voyage->vessel);
    }

    protected function rememberPortCoordinates(Port $port, float|string $latitude, float|string $longitude): void
    {
        if ($port->latitude === null || $port->longitude === null) {
            $port->update([
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
        }
    }
}
