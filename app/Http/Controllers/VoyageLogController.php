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

        return view('shipping.voyage_logs.create', compact('vessel', 'voyageCode', 'ports', 'lastVoyage'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vessel_id' => 'required|exists:vessels,id',
            'cargo_type' => 'required|string|max:255',
            'cargo_volume' => 'required|numeric|min:0',
            'cargo_unit' => 'required|string|max:20',
            'crew_on_board' => 'required|integer|min:0',
            'port_id' => 'required|exists:ports,id',
            'port_destination_id' => 'required|exists:ports,id',
            'current_location_id' => 'required|exists:ports,id',
            'voyage_no' => 'required|string|max:255',
            'fuel_rob' => 'required|numeric|min:0',
            'arrival_date' => 'nullable|date',
        ]);

        $vessel = Vessel::findOrFail($data['vessel_id']);
        $this->authorizeVesselAccess($vessel);

        $port = Port::findOrFail($data['port_id']);
        $portDestination = Port::findOrFail($data['port_destination_id']);
        $currentLocation = Port::findOrFail($data['current_location_id']);
        $cargoVolume = $data['cargo_volume'].' '.$data['cargo_unit'];

        $voyage = DB::transaction(function () use ($data, $port, $portDestination, $currentLocation, $cargoVolume) {
            $hasOpenVoyage = VoyageLogHeader::where('vessel_id', $data['vessel_id'])
                ->where('status', '!=', 'COMPLETED')
                ->lockForUpdate()
                ->exists();

            if ($hasOpenVoyage) {
                throw ValidationException::withMessages([
                    'vessel_id' => 'This vessel already has an active voyage.',
                ]);
            }

            return VoyageLogHeader::create([
                ...$data,
                'port_location' => $port->port_name,
                'port_destination' => $portDestination->port_name,
                'current_location' => $currentLocation->port_name,
                'date_created' => now()->toDateString(),
                'fuel_rob' => $data['fuel_rob'].' Liters',
                'cargo_volume' => $cargoVolume,
                'status' => 'OPEN',
                'created_by' => auth()->id(),
            ]);
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
            'port_location_id' => 'required|exists:ports,id',
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

            $selectedPort = Port::findOrFail($data['port_location_id']);
            $lastEndedActivity = VoyageActivity::where('voyage_id', $voyage->voyage_id)
                ->whereNotNull('end_date_time')->latest('end_date_time')->first();
            $quantity = (float) ($data['running_load'] ?? 0);
            $lastLoad = (float) (VoyageActivity::where('voyage_id', $voyage->voyage_id)->whereNotNull('total_load')->latest('activity_id')->value('total_load') ?? 0);
            $lastUnload = (float) (VoyageActivity::where('voyage_id', $voyage->voyage_id)->whereNotNull('total_unload')->latest('activity_id')->value('total_unload') ?? 0);

            VoyageActivity::create([
                'voyage_id' => $detail->voyage_id,
                'voyage_detail_id' => $detail->dtl_id,
                'vessel_id' => $voyage->vessel_id,
                'status_id' => $detail->status,
                'status_activity_id' => $data['activity_id'],
                'port_location' => $selectedPort->port_name,
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
                'current_location_id' => $selectedPort->id,
                'current_location' => $selectedPort->port_name,
            ];

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

    public function completeStatus($id)
    {
        $detail = VoyageLogDetail::with('activities')->findOrFail($id);
        $voyage = VoyageLogHeader::findOrFail($detail->voyage_id);
        $this->authorizeVoyageAccess($voyage);
        abort_if($voyage->status === 'COMPLETED', 422, 'The voyage is already completed.');

        // Prevent completion while an activity is still running.
        if ($detail->activities()->whereNull('end_date_time')->exists()) {
            return back()->with('error', 'Finish all activities first.');
        }
        $totalHours = 0;
        foreach ($detail->activities as $act) {
            if ($act->start_date_time && $act->end_date_time) {
                $start = \Carbon\Carbon::parse($act->start_date_time);
                $end = \Carbon\Carbon::parse($act->end_date_time);
                $totalHours += $start->diffInMinutes($end) / 60;
            }
        }
        DB::transaction(function () use ($detail, $totalHours): void {
            $detail->update([
                'date_complete' => now(),
                'total_hours' => $totalHours,
                'main_status' => 'COMPLETED',
            ]);
            VoyageActivity::where('voyage_detail_id', $detail->dtl_id)
                ->update(['main_status' => 'COMPLETED']);
        });

        return back()->with('success', 'Status completed successfully.')->with('openAddStatus', true);
    }

    public function completeVoyage($id)
    {
        $voyage = VoyageLogHeader::with('details')->findOrFail($id);
        $this->authorizeVoyageAccess($voyage);

        abort_if($voyage->details->isEmpty(), 422, 'Add and complete at least one voyage status first.');
        abort_if(
            $voyage->details->contains(fn ($detail) => $detail->main_status !== 'COMPLETED'),
            422,
            'Complete every voyage status before completing the voyage.'
        );

        DB::transaction(function () use ($voyage): void {
            $totalHours = VoyageActivity::where('voyage_id', $voyage->voyage_id)->sum('total_hours');
            $voyage->update([
                'status' => 'COMPLETED',
                'date_completed' => now()->toDateString(),
                'total_hours_voyage' => $totalHours,
            ]);
        });

        return back()->with('success', 'Voyage completed successfully.');
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
}
