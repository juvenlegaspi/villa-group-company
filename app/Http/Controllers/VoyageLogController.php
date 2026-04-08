<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use App\Models\VoyageLog;
use Illuminate\Http\Request;
use App\Models\VoyageLogHeader;
use App\Models\VoyageLogDetail;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class VoyageLogController extends Controller
{
    public function create($vesselId)
    {
        $vessel = Vessel::findOrFail($vesselId);
        $last = VoyageLogHeader::latest()->first();
        $nextId = $last ? $last->voyage_id + 1 : 1;
        $voyageCode = 'VL-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
        return view('shipping.voyage_logs.create', compact('vessel', 'voyageCode'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cargo_type' => 'required',
            'cargo_volume' => 'required',
            'crew_on_board' => 'required',
            'port_location' => 'required',
            'voyage_no' => 'required',
            'fuel_rob' => 'required',
        ]);
        $voyage = VoyageLogHeader::create([
            'date_created' => DATE('Y-m-d'),
            'cargo_type' => $request->cargo_type,
            'cargo_volume' => $request->cargo_volume,
            'port_location' => $request->port_location,
            'voyage_no' => $request->voyage_no,
            'crew_on_board' => $request->crew_on_board,
            'fuel_rob' => $request->fuel_rob.'liters',
            'status' => 'OPEN',
            'created_by' => auth()->id(),
            'vessel_id' => $request->vessel_id
        ]);
            return redirect('/shipping/voyage-logs/'.$voyage->voyage_id);
    }
    

    public function update(Request $request, $vesselId, $logId)
    {
        $log = VoyageLog::findOrFail($logId);

        $request->validate([
            'date_started' => 'nullable|date',
        ]);

        $log->update($request->all());

        return redirect('/shipping/vessels/'.$vesselId)
            ->with('success', 'Voyage log updated successfully.');
    }
    
    /*public function index()
    {
        $voyages = VoyageLogHeader::with('vessel')
            ->latest()
            ->paginate(10);
        return view('shipping.voyage_logs.index',compact('voyages'));
    }*/
    public function show($id)
    {
        $voyage = VoyageLogHeader::with('vessel')
            ->withCount('details') 
            ->findOrFail($id);
        return view('shipping.voyage_logs.show', compact('voyage'));
    }
    public function addDetail(Request $request,$id)
    {
        VoyageLogDetail::create([
            'voyage_id' => $id,
            'voyage_status' => $request->voyage_status,
            'activity' => $request->activity,
            'remarks' => $request->remarks,
            'date_time_started' => $request->date_time_started,
            'date_time_ended' => $request->date_time_ended,
            'total_hours' => $request->total_hours,
            'status' => 'Active'
        ]);
            return back()->with('success','Detail added');
    }
    public function startTrail(Request $request,$id)
    {
        VoyageLogDetail::create([
            'voyage_id' => $id,
            'voyage_status' => $request->voyage_status,
            'activity' => $request->activity,
            'remarks' => $request->remarks,
            'date_time_started' => now(),
            'status' => 'ACTIVE'
        ]);
            return back();
    }
    public function pauseTrail($detailId)
    {
        $detail = VoyageLogDetail::findOrFail($detailId);
        $detail->update([
            'is_paused' => true,
            'pause_at' => now()
        ]);
        return back();
    }
    public function resumeTrail($detailId)
    {
        $detail = VoyageLogDetail::findOrFail($detailId);
        if ($detail->pause_at) {
            $pausedMinutes = now()->diffInMinutes($detail->pause_at);
            $detail->update([
                'total_pause' => $detail->total_pause + $pausedMinutes,
                'pause_at' => null,
                'is_paused' => false
            ]);
        }
        return back();
    }
    public function endTrail($detailId)
    {
        $detail = VoyageLogDetail::findOrFail($detailId);
        $start = Carbon::parse($detail->date_time_started);
        $end = Carbon::now();
        $totalMinutes = $start->diffInMinutes($end);
        $totalPause = $detail->total_pause;
        if ($detail->is_paused && $detail->pause_at) {
            $pausedMinutes = $end->diffInMinutes($detail->pause_at);
            $totalPause += $pausedMinutes;
        }
        $actualMinutes = $totalMinutes - $totalPause;
        $detail->update([
            'date_time_ended' => $end,
            'total_hours' => round($actualMinutes / 60, 2),
            'is_paused' => false,
            'pause_at' => null
        ]);
        return back();
    }
    public function completeTrail($detailId)
    {
        $detail = VoyageLogDetail::findOrFail($detailId);
        $detail->status = 'COMPLETED';
        $detail->save();
        return back();
    }
    public function completeVoyage($id)
    {   
        $voyage = VoyageLogHeader::findOrFail($id);
        $voyage->status = 'COMPLETED';
        $voyage->date_completed = now()->toDateString();
        $voyage->save();
        return back();
    }
    public function updateTrail(Request $request,$detailId)
    {
        $detail = VoyageLogDetail::findOrFail($detailId);
        $detail->voyage_status = $request->voyage_status;
        $detail->activity = $request->activity;
        $detail->remarks = $request->remarks;
        $detail->save();
        return back();
    }
    public function exportPdf($id)
    {
        $voyage = VoyageLogHeader::with('details','creator')->findOrFail($id);
        $pdf = Pdf::loadView('shipping.voyage_logs.pdf', compact('voyage'));
        return $pdf->download('voyage-log-'.$voyage->voyage_no.'.pdf');
    }
    public function dashboard()
    {
        $totalVoyages = \App\Models\VoyageLogHeader::count();
        $activeVoyages = \App\Models\VoyageLogHeader::where('status','OPEN')->count();
        $completedVoyages = \App\Models\VoyageLogHeader::where('status','COMPLETED')->count();
        $monthlyVoyages = \App\Models\VoyageLogHeader::selectRaw('MONTH(date_created) as month, COUNT(*) as total')->groupBy('month')->pluck('total','month');
        // Voyages per vessel
        $vesselVoyages = \App\Models\VoyageLogHeader::selectRaw('vessel_id, COUNT(*) as total')->groupBy('vessel_id')->pluck('total','vessel_id');
        // Most used ports
        $portStats = \App\Models\VoyageLogHeader::selectRaw('port_location, COUNT(*) as total')->groupBy('port_location')->pluck('total','port_location');
        // Activity status distribution
        $activityStats = \App\Models\VoyageLogDetail::selectRaw('voyage_status, COUNT(*) as total')->groupBy('voyage_status')->pluck('total','voyage_status');
        return view('shipping.voyage_logs.dashboard', compact(
            'totalVoyages',
            'activeVoyages',
            'completedVoyages',
            'monthlyVoyages',
            'vesselVoyages',
            'portStats',
            'activityStats'
        ));
    }
}