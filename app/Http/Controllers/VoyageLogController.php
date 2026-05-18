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

class VoyageLogController extends Controller
{
    public function create($vesselId)
    {
        $vessel = Vessel::findOrFail($vesselId);
        $lastVoyage = VoyageLogHeader::query()->latest('voyage_id')->first();
        $nextId = $lastVoyage ? $lastVoyage->voyage_id + 1 : 1;
        $voyageCode = 'VL-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
        $ports = Port::where('status', 'ACTIVE')->orderBy('port_name')->get();
        $lastVoyage = VoyageLogHeader::whereNotNull('fuel_rob')->latest('voyage_id')->first();
        

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
            'voyage_no'     => 'required|string|max:255',
            'fuel_rob'      => 'required|string|max:255',
            'arrival_date'  => 'nullable|date',
        ]);

        // kuhaon ang selected port
        $port = Port::find($data['port_id']);
        $cargoVolume = $data['cargo_volume'] . ' ' . $data['cargo_unit'];

        $voyage = VoyageLogHeader::create([
            ...$data,

            // save pud ang port name
            'port_location' => $port->port_name,

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
            'activity_id'   => 'required|exists:activity_voyage,id',
            'port_location' => 'nullable|string',
        ]);

        $detail = VoyageLogDetail::findOrFail($detailId);

        $voyage = VoyageLogHeader::where('voyage_id', $detail->voyage_id)
            ->first();

        VoyageActivity::create([
            'voyage_id'         => $detail->voyage_id,
            'voyage_detail_id'  => $detail->dtl_id,
            'vessel_id'         => $voyage->vessel_id,
            'status_id'         => $detail->status,
            'status_activity_id'=> $request->activity_id,
            'port_location'     => $request->port_location,
            'remarks'         => $request->remarks,
            'start_date_time'   => now(),
            'main_status'       => 'ONGOING',
        ]);

        $voyage->update([
                'port_location' => $request->port_location
        ]);

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

    $totalVoyages = VoyageLogHeader::count();

    $activeVoyages = VoyageLogHeader::where(
        'status',
        'OPEN'
    )->count();

    $completedVoyages = VoyageLogHeader::where(
        'status',
        'COMPLETED'
    )->count();

    // ===============================
    // MONTHLY VOYAGES
    // ===============================
    $monthlyVoyages = VoyageLogHeader::selectRaw(
        'MONTH(date_created) as month, COUNT(*) as total'
    )
    ->groupBy('month')
    ->pluck('total', 'month');

    // ===============================
    // VOYAGES PER VESSEL
    // ===============================
    $vesselVoyages = VoyageLogHeader::selectRaw(
        'vessel_id, COUNT(*) as total'
    )
    ->groupBy('vessel_id')
    ->pluck('total', 'vessel_id');

    // ===============================
    // MOST USED PORTS
    // ===============================
    $portStats = VoyageLogHeader::selectRaw(
        'port_location, COUNT(*) as total'
    )
    ->groupBy('port_location')
    ->pluck('total', 'port_location');

    // ===============================
    // ACTIVITY STATUS DISTRIBUTION
    // ===============================
    $activityStats = VoyageLogDetail::whereNotNull('main_status')
        ->selectRaw('main_status, COUNT(*) as total')
        ->groupBy('main_status')
        ->pluck('total', 'main_status');

    // ===============================
    // TODAY COUNTS
    // ===============================
    $activitiesToday = VoyageActivity::whereDate(
        'created_at',
        today()
    )->count();

    $fuelUpdatesToday = FuelRobMonitoring::whereDate(
        'created_at',
        today()
    )->count();

    $activeVessels = VoyageLogHeader::where(
        'status',
        'OPEN'
    )
    ->distinct('vessel_id')
    ->count('vessel_id');

    $completedToday = VoyageLogHeader::whereDate(
        'date_completed',
        today()
    )->count();

    // ===============================
    // TOP ACTIVITIES
    // ===============================
    $topActivities = VoyageActivity::with('activity')
        ->selectRaw('status_activity_id, COUNT(*) as total')
        ->groupBy('status_activity_id')
        ->orderByDesc('total')
        ->take(5)
        ->get();

    // ===============================
    // FUEL SUMMARY
    // ===============================
    $totalFuelConsumed = FuelRobMonitoring::sum(
        'total_consumed'
    );

    $totalFuelReceived = FuelRobMonitoring::sum(
        'received_fuel'
    );

    $averageFuel = FuelRobMonitoring::avg(
        'total_consumed'
    );

    // ===============================
    // RECENT ACTIVITIES
    // ===============================
    $recentActivities = VoyageActivity::with([
        'vessel',
        'activity',
        'detail'
    ])
    ->latest()
    ->take(10)
    ->get();

    // ===============================
    // LOW FUEL WARNING
    // ===============================
    $lowFuelVoyages = VoyageLogHeader::whereRaw("
        CAST(
            REPLACE(fuel_rob, ' Liters', '')
            AS DECIMAL(10,2)
        ) < 1000
    ")->get();

    return view(
        'shipping.voyage_logs.dashboard',
        compact(
            'totalVoyages',
            'activeVoyages',
            'completedVoyages',
            'monthlyVoyages',
            'vesselVoyages',
            'portStats',
            'activityStats',

            'activitiesToday',
            'fuelUpdatesToday',
            'activeVessels',
            'completedToday',

            'topActivities',

            'totalFuelConsumed',
            'totalFuelReceived',
            'averageFuel',

            'recentActivities',

            'lowFuelVoyages'
        )
    );
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
