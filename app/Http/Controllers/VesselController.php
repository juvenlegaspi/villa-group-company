<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use Illuminate\Http\Request;
use App\Models\VoyageLog;
use App\Models\VoyageLogHeader;
use App\Models\User;

class VesselController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if ($user->is_admin) {
            // ADMIN → tanan makita
            $vessels = Vessel::with('captain')->paginate(10);
        } else {
            // CAPTAIN → iyaha lang
            $vessels = Vessel::with('captain') ->where('captain_id', $user->id) ->paginate(10);
        }
        $captains = User::where('department_id', 1) ->where('role', 'captain') ->get();
        return view('shipping.vessels.index', compact('vessels', 'captains'));
    }
    public function create()
    {
        $captains = User::where('department_id', 1) ->where('role', 'captain') ->get();
        return view('shipping.vessels.create', compact('captains'));
    }
    public function store(Request $request)
    {
        $request->validate([
            'vessel_name' => 'required',
            'imo_number' => 'nullable',
            'call_sign' => 'nullable',
        ]);

        Vessel::create([
            'vessel_name' => $request->vessel_name,
            'captain_id' => $request->captain_id,
            'imo_number' => $request->imo_number,
            'call_sign' => $request->call_sign,
            'vessel_type' => $request->vessel_type,
            'dwt' => $request->dwt,
            'fuel_type' => $request->fuel_type,
            'service_speed' => $request->service_speed,
            'charter_type' => $request->charter_type,
            'vessel_status' => $request->vessel_status,
        ]);
        return redirect()->route('vessels.index')->with('success','Vessel Added Successfully');
    }
    public function edit($id)
    {
        $vessel = Vessel::findOrFail($id);
        return view('shipping.vessels.edit', compact('vessel'));
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'vessel_name' => 'required',
            'imo_number' => 'nullable',
            'call_sign' => 'nullable',
        ]);
        $vessel = Vessel::findOrFail($id);
        $vessel->update([
            'vessel_name' => $request->vessel_name,
            'captain_id' => $request->captain_id,
            'imo_number' => $request->imo_number,
            'call_sign' => $request->call_sign,
            'vessel_type' => $request->vessel_type,
            'dwt' => $request->dwt,
            'fuel_type' => $request->fuel_type,
            'service_speed' => $request->service_speed,
            'charter_type' => $request->charter_type,
            'vessel_status' => $request->vessel_status,
        ]);
        return redirect()->route('vessels.index')
            ->with('success', 'Vessel Updated Successfully');
    }

    public function show(Request $request, $id)
    {
        $vessel = Vessel::findOrFail($id);
        $query = VoyageLogHeader::where('vessel_id', $id);
        // SEARCH
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('voyage_id', 'like', '%' . $request->search . '%')
                    ->orWhere('port_location', 'like', '%' . $request->search . '%')
                    ->orWhere('cargo_type', 'like', '%' . $request->search . '%');
            });
        }
        // SORT
        if ($request->sort == 'activity') {
            $query->orderBy('voyage_id', 'desc');
        } elseif ($request->sort == 'date') {
            $query->orderBy('date_created', 'desc');
        } else {
            $query->orderBy('voyage_id', 'desc');
        }
        $voyages = $query->paginate(10)->withQueryString();
        return view('shipping.vessels.show', compact('vessel', 'voyages'));
    }
}