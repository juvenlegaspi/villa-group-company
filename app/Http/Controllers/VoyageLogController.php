<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use App\Models\VoyageLog;
use App\Models\VoyageLogDetail;
use App\Models\VoyageLogHeader;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\ActivityStatusVoyage;
use App\Models\ActivityVoyage;
use App\Models\VoyageActivity;
use App\Models\Port;
use App\Models\FuelRobMonitoring;
use Illuminate\Support\Facades\DB;

class VoyageLogController extends Controller
{
    public function create($vesselId)
    {
        $vessel = Vessel::findOrFail($vesselId);
        $lastVoyage = VoyageLogHeader::query()->latest('voyage_id')->first();
        $nextId = $lastVoyage ? $lastVoyage->voyage_id + 1 : 1;
        $voyageCode = 'VL-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
        $ports = Port::where('status', 'ACTIVE')->orderBy('port_name')->get();
        $lastVoyage = VoyageLogHeader::where('vessel_id', $vesselId)->whereNotNull('fuel_rob')->whereNotNull('date_completed')->latest('voyage_id')->first();
        return view('shipping.voyage_logs.create', compact('vessel', 'voyageCode', 'ports', 'lastVoyage'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vessel_id'     => 'required|exists:vessels,id',
            'cargo_type'    => 'required|string|max:255',
            'cargo_volume'  => 'required|string|max:255',
            'cargo_unit'   => 'required|string|max:20',
            'crew_on_board' => 'required',
            'port_id'       => 'required|exists:ports,id',
            'port_destination_id' => 'required|exists:ports,id',
            'current_location_id' => 'required|exists:ports,id',
            'voyage_no'     => 'required|string|max:255',
            'fuel_rob'      => 'required|string|max:255',
            'arrival_date'  => 'nullable|date',
        ]);

        // kuhaon ang selected port
        $port = Port::find($data['port_id']);
        $portDestination = Port::find($data['port_destination_id']);
        $currentLocation = Port::find($data['current_location_id']);
        $cargoVolume = $data['cargo_volume'] . ' ' . $data['cargo_unit'];

        $voyage = VoyageLogHeader::create([
            ...$data,

            // save pud ang port name
            'port_location' => $port->port_name,
            'port_destination' => $portDestination->port_name,
            'current_location'  => $currentLocation->port_name,

            'date_created' => now()->toDateString(),
            'fuel_rob' => $data['fuel_rob'] . ' Liters',
            'cargo_volume' => $cargoVolume,
            'status' => 'OPEN',
            'created_by' => auth()->id(),
        ]);
        return redirect('/shipping/voyage-logs/' . $voyage->voyage_id);
    }

    /*public function update(Request $request, $vesselId, $logId)
    {
        $log = VoyageLog::findOrFail($logId);

        $data = $request->validate([
            'date_started' => 'nullable|date',
        ]);

        $log->update($data);

        return redirect('/shipping/vessels/' . $vesselId)
            ->with('success', 'Voyage log updated successfully.');
    }*/

    public function show($id)
    {
        $statuses = ActivityStatusVoyage::where('status', 1)->get();

        $activities = \App\Models\ActivityVoyage::where('status', 1)
            ->get();

        $voyage = VoyageLogHeader::with([
        'vessel',
        'details.activities.activity'
    ])->findOrFail($id);

        $ports = Port::where('status', 'ACTIVE')->orderBy('port_name')->get();

        return view('shipping.voyage_logs.show', compact('voyage', 'statuses', 'activities','ports'));
    }

    public function addDetail(Request $request, $id)
    {
        $data = $this->validateDetailRequest($request, true);

        $voyage = VoyageLogHeader::findOrFail($id);

        // create detail
        $detail = VoyageLogDetail::create([
            'voyage_id'      => $voyage->voyage_id,
            'vessel_id'      => $voyage->vessel_id,
            'remarks'        => $data['remarks'] ?? null,
            'status'         => $data['status_id'],
            'main_status' => 'ONGOING',
        ]);
        $statusName = ActivityStatusVoyage::find($data['status_id']);

        $voyage->update([
            'status' => $statusName?->name
        ]);

        return back()->with('success', 'Detail added successfully.');
    }

    public function addActivity(Request $request, $detailId)
    {
        $request->validate([
            'activity_id' => 'required|exists:activity_voyage,id',
        ]);
        $detail = VoyageLogDetail::findOrFail($detailId);
        $voyage = VoyageLogHeader::where('voyage_id', $detail->voyage_id)
            ->first();
        $lastEndedActivity = VoyageActivity::where('vessel_id', $voyage->vessel_id)
            ->whereNotNull('end_date_time')
            ->latest('end_date_time')
            ->first();
        // =========================================
        // DEFAULT VALUES
        // =========================================
        $cargoLoad = null;
        $totalLoad = null;
        $cargoUnload = null;
        $totalUnload = null;
        // =========================================
        // LOADING ACTIVITIES
        // 34 = Loading
        // 36 = Complete Loading
        // =========================================
        if (in_array($request->activity_id, [34, 36])) {
            $cargoLoad = (float) $request->running_load;
            // kuha last total load
            $lastLoad = VoyageActivity::where('voyage_id', $detail->voyage_id)
                ->whereNotNull('total_load')
                ->latest('activity_id')
                ->first();
            $previousTotalLoad = $lastLoad?->total_load ?? 0;
            $totalLoad = $previousTotalLoad + $cargoLoad;
        }
        // =========================================
        // UNLOADING ACTIVITIES
        // 35 = Unloading
        // 37 = Complete Unloading
        // =========================================
        if (in_array($request->activity_id, [35, 37])) {

            $cargoUnload = (float) $request->running_load;
            // kuha last total unload
            $lastUnload = VoyageActivity::where('voyage_id', $detail->voyage_id)
                ->whereNotNull('total_unload')
                ->latest('activity_id')
                ->first();
            $previousTotalUnload = $lastUnload?->total_unload ?? 0;
            $totalUnload = $previousTotalUnload + $cargoUnload;
        }
        $selectedPort = Port::find($request->port_location_id);
        VoyageActivity::create([
            'voyage_id'         => $detail->voyage_id,
            'voyage_detail_id'  => $detail->dtl_id,
            'vessel_id'         => $voyage->vessel_id,
            'status_id'         => $detail->status,
            'status_activity_id'=> $request->activity_id,
            'port_location' => $selectedPort->port_name,
            'remarks'           => $request->remarks,
            'start_date_time'   => $lastEndedActivity?->end_date_time ?? now(),
            // LOADING
            'cargo_load'        => $cargoLoad,
            'total_load'        => $totalLoad,
            // UNLOADING
            'cargo_unload'      => $cargoUnload,
            'total_unload'      => $totalUnload,
            'load_unit' => $request->load_unit,
            'main_status'       => 'ONGOING',
        ]);
        if (in_array($request->activity_id, [34, 36])) {
            preg_match('/[\d.]+/', $voyage->cargo_volume, $matches);
            $currentCargo = (float) ($matches[0] ?? 0);
            $newCargo = $currentCargo + (float) $request->running_load;
            $voyage->update([
                'cargo_volume' => $newCargo . ' ' . $request->load_unit
            ]);
        }
        if (in_array($request->activity_id, [35, 37])) {
            preg_match('/[\d.]+/', $voyage->cargo_volume, $matches);
            $currentCargo = (float) ($matches[0] ?? 0);
            $newCargo = $currentCargo - (float) $request->running_load;
            // para dili negative
            if ($newCargo < 0) {
                $newCargo = 0;
            }
            $voyage->update([
                'cargo_volume' => $newCargo . ' ' . $request->load_unit
            ]);
        }
        $voyage->update(['current_location_id' => $selectedPort->id, 'current_location'    => $selectedPort->port_name,]);
        return back()->with('success', 'Activity added successfully.');
    }

    public function endActivity(Request $request, $id)
    {
        $request->validate([
            'end_date' => 'required|date',
            'end_time' => 'required',
        ]);
        $activity = VoyageActivity::findOrFail($id);
        $endDateTime = $request->end_date . ' ' . $request->end_time;
        $start = \Carbon\Carbon::parse($activity->start_date_time);
        $end = \Carbon\Carbon::parse($endDateTime);
        if ($end->lessThanOrEqualTo($start)) {
            return back()->with('invalidEndTime', true);
        }
        $totalHours = $start->diffInMinutes($end) / 60;
        $activity->update([
            'end_date_time' => $endDateTime,
            'total_hours'   => $totalHours,
            'main_status'   => 'COMPLETED',
        ]);
        return back()
    ->with('activityEnded', true)
    ->with('ended_activity_id', $activity->activity_id);
    }
    public function updateActivity(Request $request, $id)
    {
        $request->validate([
            'end_date' => 'required|date',
            'end_time' => 'required',
            'edit_reason' => 'required',
            'edit_attachment' => 'nullable|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
        ]);

        $activity = VoyageActivity::findOrFail($id);
        $endDateTime = $request->end_date . ' ' . $request->end_time;
        $start = \Carbon\Carbon::parse($activity->start_date_time);
        $end = \Carbon\Carbon::parse($endDateTime);
        $totalHours = $start->diffInMinutes($end) / 60;
        $attachment = $activity->edit_attachment;

        if ($request->hasFile('edit_attachment')) {

            $file = $request->file('edit_attachment');

            $filename = time() . '_' . $file->getClientOriginalName();

            $file->storeAs(
                'voyage_activity_edits',
                $filename,
                'public'
            );

            $attachment = 'voyage_activity_edits/' . $filename;
        }

        $activity->update([
            'edited_end_date_time' => $endDateTime,
            'edit_reason' => $request->edit_reason,
            'edit_attachment' => $attachment,
            'edited_at' => now(),

            'end_date_time' => $endDateTime,
            'total_hours' => $totalHours,
        ]);

        return back()->with('success', 'Activity updated successfully.');
    }
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status_id' => 'required|exists:activity_status_voyage,id'
        ]);

        $detail = VoyageLogDetail::findOrFail($id);

        $detail->update([
            'status' => $request->status_id
        ]);

        return back()->with('success', 'Status updated successfully.');
    }
    public function completeStatus($id)
    {
        $detail = VoyageLogDetail::with('activities')->findOrFail($id);
        // prevent complete if naa pay running activity
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
        // update voyage_logs_details
        $detail->update([
            'date_complete' => now(),
            'total_hours'   => $totalHours,
            'main_status'   => 'COMPLETED'

        ]);
        // update all activities under this status
        VoyageActivity::where('voyage_detail_id', $detail->dtl_id)
            ->update([
                'main_status' => 'COMPLETED'
            ]);
        return back()->with('success', 'Status completed successfully.')->with('openAddStatus', true);
    }

    public function startTrail(Request $request, $id)
    {
        $data = $this->validateTrailRequest($request);

        VoyageLogDetail::create([
            'voyage_id' => $id,
            'activity_status_voyage_id' => $data['activity_status_voyage_id'],
            'activity_voyage_id' => $data['activity_voyage_id'],
            'remarks' => $data['remarks'] ?? null,
            'date_time_started' => now(),
            'status' => 'ACTIVE',
        ]);

        return back()->with('success', 'Activity started successfully.');
    }

    public function pauseTrail($detailId)
    {
        $detail = VoyageLogDetail::findOrFail($detailId);
        $detail->update([
            'is_paused' => true,
            'pause_at' => now(),
        ]);

        return back()->with('success', 'Activity paused.');
    }

    /*public function resumeTrail($detailId)
    {
        $detail = VoyageLogDetail::findOrFail($detailId);

        if ($detail->pause_at) {
            $pausedMinutes = now()->diffInMinutes($detail->pause_at);

            $detail->update([
                'total_pause' => $detail->total_pause + $pausedMinutes,
                'pause_at' => null,
                'is_paused' => false,
            ]);
        }

        return back()->with('success', 'Activity resumed.');
    }

    public function endTrail($detailId)
    {
        $detail = VoyageLogDetail::findOrFail($detailId);
        $start = Carbon::parse($detail->date_time_started);
        $end = now();
        $totalMinutes = $start->diffInMinutes($end);
        $totalPause = $detail->total_pause;

        if ($detail->is_paused && $detail->pause_at) {
            $totalPause += $end->diffInMinutes($detail->pause_at);
        }

        $detail->update([
            'date_time_ended' => $end,
            'total_hours' => round(($totalMinutes - $totalPause) / 60, 2),
            'is_paused' => false,
            'pause_at' => null,
        ]);

        return back()->with('success', 'Activity ended successfully.');
    }*/

    public function completeTrail($detailId)
    {
        $detail = VoyageLogDetail::findOrFail($detailId);
        $detail->update(['status' => 'COMPLETED']);

        return back()->with('success', 'Activity completed successfully.');
    }

    public function completeVoyage($id)
    {
        $voyage = VoyageLogHeader::findOrFail($id);

        // total tanan activity hours
        $totalHours = \App\Models\VoyageActivity::where('voyage_id', $voyage->voyage_id)
            ->sum('total_hours');

        // update header
        $voyage->update([
            'status'              => 'COMPLETED',
            'date_completed'      => now()->toDateString(),
            'total_hours_voyage'  => $totalHours,
        ]);

        return back()->with('success', 'Voyage completed successfully.');
    }

    public function updateTrail(Request $request, $detailId)
    {
        $detail = VoyageLogDetail::findOrFail($detailId);
        $data = $this->validateTrailRequest($request);

        $detail->update($data);

        return back()->with('success', 'Trail updated successfully.');
    }

    public function exportPdf($id)
    {
        $voyage = VoyageLogHeader::with([
            'details.activities.activity',
            'details.statusRelation',
            'vessel',
            'creator',
            'fuelMonitorings'
        ])->findOrFail($id);

        $pdf = Pdf::loadView(
            'shipping.voyage_logs.pdf',
            compact('voyage')
        )->setPaper('a4', 'portrait');

        return $pdf->download(
            'voyage-log-' . $voyage->voyage_no . '.pdf'
        );
    }

    // ===============================
// VOYAGE LOG DASHBOARD CONTROLLER
// ===============================

public function dashboard()
{
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

    $monthlyVoyageTrend = VoyageLogHeader::selectRaw('MONTH(date_created) as month_num, COUNT(*) as total')
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

    protected function validateTrailRequest(Request $request): array
    {
        return $request->validate([
            'remarks' => 'nullable|string|max:255',
        ]);
    }

    protected function validateDetailRequest(Request $request, bool $allowManualDates = false): array
    {
        return $request->validate([
            'status_id' => 'required|exists:activity_status_voyage,id',
            'remarks' => 'nullable|string|max:255',
        ]);
    }
}
