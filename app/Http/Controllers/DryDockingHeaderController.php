<?php

namespace App\Http\Controllers;

use App\Models\DryDockingDetail;
use App\Models\DryDockingHeader;
use App\Models\Vessel;
use Illuminate\Http\Request;

class DryDockingHeaderController extends Controller
{
    public function index()
    {
        $headers = DryDockingHeader::with('vessel')->latest()->get();

        return view('shipping.dry_docking.index', compact('headers'));
    }

    public function create()
    {
        $vessels = Vessel::orderBy('vessel_name')->get();

        return view('shipping.dry_docking.create', compact('vessels'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vessel_id' => 'required|exists:vessels,id',
            'arrival_date' => 'nullable|date',
            'docking_date' => 'nullable|date',
            'laydays' => 'nullable|integer|min:0',
            'undocking_date' => 'nullable|date',
            'vessel_manager' => 'nullable|string|max:255',
            'status' => 'required|string|max:255',
        ]);

        DryDockingHeader::create([
            ...$data,
            'is_shipyard' => $request->boolean('is_shipyard'),
            'is_inhouse' => $request->boolean('is_inhouse'),
            'create_date' => now(),
        ]);

        return redirect('/shipping/dry-docking')->with('success', 'Dry docking record saved successfully.');
    }

    public function details($id)
    {
        $header = DryDockingHeader::with(['vessel', 'details'])->findOrFail($id);

        return view('shipping.dry_docking.details', compact('header'));
    }

    public function storeDetails(Request $request, $id)
    {
        $request->validate([
            'vessel_id' => 'required|exists:vessels,id',
            'items' => 'required|array|min:1',
            'items.*.scope_of_work' => 'nullable|string|max:255',
            'items.*.plan_duration' => 'nullable|numeric',
            'items.*.actual_duration' => 'nullable|numeric',
            'items.*.status' => 'nullable|string|max:255',
            'items.*.daily_status' => 'nullable|string|max:255',
            'items.*.weight' => 'nullable|numeric',
            'items.*.actual_progress' => 'nullable|numeric',
            'items.*.activity' => 'nullable|string|max:255',
            'items.*.remarks' => 'nullable|string|max:255',
        ]);

        foreach ($request->items as $item) {
            if (blank($item['scope_of_work'] ?? null) && blank($item['activity'] ?? null)) {
                continue;
            }

            DryDockingDetail::create([
                'vessel_id' => $request->vessel_id,
                'dry_dock_id' => $id,
                'scope_of_work' => $item['scope_of_work'] ?? null,
                'plan_duration' => $item['plan_duration'] ?? null,
                'actual_duration' => $item['actual_duration'] ?? null,
                'status' => $item['status'] ?? null,
                'daily_status' => $item['daily_status'] ?? null,
                'weight' => $item['weight'] ?? null,
                'actual_progress' => $item['actual_progress'] ?? null,
                'activity' => $item['activity'] ?? null,
                'remarks' => $item['remarks'] ?? null,
            ]);
        }

        return back()->with('success', 'Dry docking details saved successfully.');
    }
}
