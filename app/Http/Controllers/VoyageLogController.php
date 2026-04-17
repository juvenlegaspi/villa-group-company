<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use App\Models\VoyageLog;
use App\Models\VoyageLogDetail;
use App\Models\VoyageLogHeader;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VoyageLogController extends Controller
{
    public function create($vesselId)
    {
        $vessel = Vessel::findOrFail($vesselId);
        $lastVoyage = VoyageLogHeader::query()->latest('voyage_id')->first();
        $nextId = $lastVoyage ? $lastVoyage->voyage_id + 1 : 1;
        $voyageCode = 'VL-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

        return view('shipping.voyage_logs.create', compact('vessel', 'voyageCode'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vessel_id' => 'required|exists:vessels,id',
            'cargo_type' => 'required|string|max:255',
            'cargo_volume' => 'required|string|max:255',
            'crew_on_board' => 'required',
            'port_location' => 'required|string|max:255',
            'voyage_no' => 'required|string|max:255',
            'fuel_rob' => 'required|string|max:255',
        ]);

        $voyage = VoyageLogHeader::create([
            ...$data,
            'date_created' => now()->toDateString(),
            'fuel_rob' => $data['fuel_rob'] . ' liters',
            'status' => 'OPEN',
            'created_by' => auth()->id(),
        ]);

        return redirect('/shipping/voyage-logs/' . $voyage->voyage_id);
    }

    public function update(Request $request, $vesselId, $logId)
    {
        $log = VoyageLog::findOrFail($logId);

        $data = $request->validate([
            'date_started' => 'nullable|date',
        ]);

        $log->update($data);

        return redirect('/shipping/vessels/' . $vesselId)
            ->with('success', 'Voyage log updated successfully.');
    }

    public function show($id)
    {
        $voyage = VoyageLogHeader::with(['vessel', 'details'])
            ->withCount('details')
            ->findOrFail($id);

        return view('shipping.voyage_logs.show', compact('voyage'));
    }

    public function addDetail(Request $request, $id)
    {
        $data = $this->validateDetailRequest($request, true);

        VoyageLogDetail::create([
            'voyage_id' => $id,
            ...$data,
            'status' => 'Active',
        ]);

        return back()->with('success', 'Detail added successfully.');
    }

    public function startTrail(Request $request, $id)
    {
        $data = $this->validateTrailRequest($request);

        VoyageLogDetail::create([
            'voyage_id' => $id,
            ...$data,
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

    public function resumeTrail($detailId)
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
    }

    public function completeTrail($detailId)
    {
        $detail = VoyageLogDetail::findOrFail($detailId);
        $detail->update(['status' => 'COMPLETED']);

        return back()->with('success', 'Activity completed successfully.');
    }

    public function completeVoyage($id)
    {
        $voyage = VoyageLogHeader::findOrFail($id);
        $voyage->update([
            'status' => 'COMPLETED',
            'date_completed' => now()->toDateString(),
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
        $voyage = VoyageLogHeader::with(['details', 'creator'])->findOrFail($id);
        $pdf = Pdf::loadView('shipping.voyage_logs.pdf', compact('voyage'));

        return $pdf->download('voyage-log-' . $voyage->voyage_no . '.pdf');
    }

    public function dashboard()
    {
        $totalVoyages = VoyageLogHeader::count();
        $activeVoyages = VoyageLogHeader::where('status', 'OPEN')->count();
        $completedVoyages = VoyageLogHeader::where('status', 'COMPLETED')->count();
        $monthlyVoyages = VoyageLogHeader::selectRaw('MONTH(date_created) as month, COUNT(*) as total')
            ->groupBy('month')
            ->pluck('total', 'month');
        $vesselVoyages = VoyageLogHeader::selectRaw('vessel_id, COUNT(*) as total')
            ->groupBy('vessel_id')
            ->pluck('total', 'vessel_id');
        $portStats = VoyageLogHeader::selectRaw('port_location, COUNT(*) as total')
            ->groupBy('port_location')
            ->pluck('total', 'port_location');
        $activityStats = VoyageLogDetail::selectRaw('voyage_status, COUNT(*) as total')
            ->groupBy('voyage_status')
            ->pluck('total', 'voyage_status');

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

    protected function validateTrailRequest(Request $request): array
    {
        return $request->validate([
            'voyage_status' => 'required|string|max:255',
            'activity' => 'required|string|max:255',
            'remarks' => 'nullable|string|max:255',
        ]);
    }

    protected function validateDetailRequest(Request $request, bool $allowManualDates = false): array
    {
        return $request->validate([
            'voyage_status' => 'required|string|max:255',
            'activity' => 'required|string|max:255',
            'remarks' => 'nullable|string|max:255',
            'date_time_started' => [$allowManualDates ? 'nullable' : 'prohibited', 'date'],
            'date_time_ended' => [$allowManualDates ? 'nullable' : 'prohibited', 'date'],
            'total_hours' => 'nullable|numeric',
        ]);
    }
}
