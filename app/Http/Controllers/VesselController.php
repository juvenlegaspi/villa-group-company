<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use Illuminate\Http\Request;
use App\Models\VoyageLog;
use App\Models\VoyageLogHeader;

class VesselController extends Controller
{
    public function index()
    {
        $vessels = Vessel::all();
        return view('shipping.vessels.index', compact('vessels'));
    }

    public function create()
    {
        return view('shipping.vessels.create');
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