<?php

namespace App\Http\Controllers;

use App\Models\DryDockingHeader;
use App\Models\DryDockingDetail;
use App\Models\Vessel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;





class DryDockingHeaderController extends Controller
{
    public function index()
{
    $headers = \App\Models\DryDockingHeader::with('vessel')->latest()->get();

    return view('shipping.dry_docking.index', compact('headers'));
}
    public function create()
    {
        $vessels = Vessel::all();
        return view('shipping.dry_docking.create', compact('vessels'));
    }

    public function store(Request $request)
    {
        DryDockingHeader::create([
            'vessel_id' => $request->vessel_id,
            'arrival_date' => $request->arrival_date,
            'docking_date' => $request->docking_date,
            'laydays' => $request->laydays,
            'undocking_date' => $request->undocking_date,
            'vessel_manager' => $request->vessel_manager,
            'is_shipyard' => $request->has('is_shipyard') ? 1 : 0,
            'is_inhouse' => $request->has('is_inhouse') ? 1 : 0,
            'create_date' => now(),
            'status' => $request->status,
        ]);
        return redirect('/shipping/dry-docking')->with('success', 'Saved!');
    }
    public function details($id)
    {
        $header = DryDockingHeader::findOrFail($id);
        return view('shipping.dry_docking.details', compact('header'));
    }
    public function storeDetails(Request $request, $id)
    {
        foreach ($request->items as $item) {
            DryDockingDetail::create([
                'vessel_id' => $request->vessel_id,
                'dry_dock_id' => $id,
                'scope_of_work' => $item['scope_of_work'],
                'plan_duration' => $item['plan_duration'],
                'actual_duration' => $item['actual_duration'],
                'status' => $item['status'],
                'daily_status' => $item['daily_status'],
                'weight' => $item['weight'],
                'actual_progress' => $item['actual_progress'],
                'activity' => $item['activity'],
                'remarks' => $item['remarks'],
            ]);
        }
        return back()->with('success', 'Details saved!');
    }
    
}
