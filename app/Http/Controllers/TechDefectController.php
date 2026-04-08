<?php

namespace App\Http\Controllers;

use App\Models\TechDefect;
use App\Models\Vessel;
use Illuminate\Http\Request;
use App\Models\ThirdPartySupport;

class TechDefectController extends Controller
{
    public function dashboard()
    {
        $totalReports = \App\Models\TechDefect::count();
        $open = \App\Models\TechDefect::where('status','Open')->count();
        $ongoing = \App\Models\TechDefect::where('status','Ongoing')->count();
        $waiting = \App\Models\TechDefect::where('status','WAITING 3RD PARTY')->count();
        $completed = \App\Models\TechDefect::where('status','Completed')->count();
        $vesselDefects = \App\Models\TechDefect::selectRaw('vessel_id, COUNT(*) as total')->groupBy('vessel_id')->with('vessel')->get();
        $latestReports = \App\Models\TechDefect::with('vessel')->latest()->take(5)->get();
        $monthlyDefects = \App\Models\TechDefect::selectRaw('MONTH(date_identified) as month, COUNT(*) as total')->groupBy('month')->orderBy('month')->get();
        $topVessel = \App\Models\TechDefect::selectRaw('vessel_id, COUNT(*) as total')->groupBy('vessel_id')->orderByDesc('total')->with('vessel')->first();
        $criticalDefects = TechDefect::where('severity_level','critical')->count();
        return view('shipping.tech_defects.dashboard', compact(
            'totalReports',
            'open',
            'ongoing',
            'waiting',
            'completed',
            'vesselDefects',
            'latestReports',
            'monthlyDefects',
            'topVessel',
            'criticalDefects'
        ));
    }
    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->is_admin) {
            $query = TechDefect::with('vessel');
        } else {
            $query = TechDefect::with('vessel')
                ->whereHas('vessel', function ($q) use ($user) {
                    $q->where('captain_id', $user->id);
                });
        }
        // STATUS FILTER
        if ($request->status) {
            $query->where('status', $request->status);
        }
        // SEARCH
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%$search%")
                ->orWhereHas('vessel', function ($v) use ($search) {
                    $v->where('vessel_name', 'like', "%$search%");
                });
            });
        }
        $query->orderByRaw("
            CASE
                WHEN status = 'Open' THEN 1
                WHEN status = 'Ongoing' THEN 2
                WHEN status = 'Waiting 3rd Party' THEN 3
                WHEN status = 'Completed' THEN 4
            END
        ");
        $reports = $query->orderBy('id', 'asc')
                ->paginate(10)
                ->withQueryString();
        return view('shipping.tech_defects.index', compact('reports'));
    }
    public function create()
    {
        $user = auth()->user();
        // ADMIN → tanan vessels
        if ($user->is_admin) {
            $vessels = Vessel::all();
        }
        // MANAGER → tanan vessels sa iya department
        elseif ($user->role == 'manager' && $user->department_id == 1) {
            $vessels = Vessel::where('department_id', $user->department_id)->get();
        }
        // CAPTAIN → assigned vessel ra
        elseif ($user->role == 'captain' && $user->department_id == 1) {
            $vessels = Vessel::where('captain_id', $user->id)->get();
        }
        else {
            $vessels = collect(); // empty
        }
            return view('shipping.tech_defects.create', compact('vessels'));
    }
    // create report of vessel
    public function store(Request $request)
    {
        $user = auth()->user();
        // ✅ VALIDATION
        $request->validate([
            'vessel_id' => 'required|exists:vessels,id',
            'date_identified' => 'required|date',
            'port_location' => 'nullable|string|max:255',
            'reported_by' => 'nullable|string|max:255',
            'system_affected' => 'nullable|string|max:255',
            'defect_description' => 'required|string',
            'initial_cause' => 'nullable|string',
            'severity_level' => 'required',
            'operational_impact' => 'required',
            'temporary_repair' => 'nullable',
            'remarks' => 'nullable|string',
        ]);
        // ✅ SECURITY: vessel restriction
        if ($user->role == 'captain' && $user->department_id == 1) {
            $allowedVessel = Vessel::where('captain_id', $user->id)
                               ->pluck('id')
                               ->toArray();

            if (!in_array($request->vessel_id, $allowedVessel)) {
                abort(403, 'Unauthorized vessel');
            }
        }
        if ($user->role == 'manager' && $user->department_id == 1) {
            $allowedVessel = Vessel::where('department_id', $user->department_id)
                               ->pluck('id')
                               ->toArray();

            if (!in_array($request->vessel_id, $allowedVessel)) {
                abort(403, 'Unauthorized vessel');
            }
        }
        // ✅ SAVE DATA (SAFE VERSION)
        $report = TechDefect::create([
            'vessel_id' => $request->vessel_id,
            'status' => 'Open',
            'date_identified' => $request->date_identified,
            'port_location' => strtoupper($request->port_location),
            'reported_by' => strtoupper($request->reported_by),
            'system_affected' => strtoupper($request->system_affected),
            'defect_description' => strtoupper($request->defect_description),
            'initial_cause' => strtoupper($request->initial_cause),
            'severity_level' => $request->severity_level,
            'operational_impact' => $request->operational_impact,
            'temporary_repair' => $request->temporary_repair,
            'remarks' => strtoupper($request->remarks),
        ]);
        return redirect()
            ->route('tech-defects.show', $report->id)
            ->with('success', 'Report added successfully');
    }
    public function edit($id)
    {
        $user = auth()->user();
        $report = TechDefect::findOrFail($id);
        if ($user->is_admin) {
            $vessels = Vessel::all();
        }
        elseif ($user->role == 'manager' && $user->department_id == 1) {
            $vessels = Vessel::where('department_id', $user->department_id)->get();
        }
        elseif ($user->role == 'captain' && $user->department_id == 1) {
            $vessels = Vessel::where('captain_id', $user->id)->get();
        }
        else {
            $vessels = collect();
        }
        return view('shipping.tech_defects.edit', compact('report','vessels'));
    }
    public function update(Request $request,$id)
    {
        $report = TechDefect::findOrFail($id);
        // START REPAIR
        if($request->action == 'start'){
            $report->status = 'Ongoing';
            $report->save();
            return back()->with('success','Repair Started');
        }
        // ADD 3RD PARTY SUPPORT
        if($request->action == 'add_support'){
            ThirdPartySupport::create([
                'tech_defect_id' => $id,
                'reason_for_support' => $request->reason_for_support,
                'spares_required' => $request->spares_required,
                'tools_required' => $request->tools_required,
                'status' => 'Ongoing'
            ]);
            $report->status = 'Waiting 3rd Party';
            $report->third_party_required = 'Yes';
            $report->save();
            return back()->with('success','3rd Party Support Added');
        }
        // MARK SUPPORT DONE
        if(str_starts_with($request->action,'done_')){
            $supportId = str_replace('done_','',$request->action);
            $support = ThirdPartySupport::findOrFail($supportId);
            $support->status = 'Done';
            $support->save();
            $report = TechDefect::findOrFail($id);
            $pending = ThirdPartySupport::where('tech_defect_id',$id)->where('status','Pending')->count();
            if($pending == 0){
                $report->status = 'Ongoing';
                $report->save();
            }
            return back()->with('success','Support marked as Done');
        }
        // COMPLETE REPORT
        if($request->action == 'complete'){
            $report->status = 'Completed';
            $report->date_completed = now();
            $report->save();
            return back()->with('success','Report Completed');
        }
        // NORMAL UPDATE
        $report->update($request->except('action'));
        return back()->with('success','Report Updated');
    }
    public function destroy($id)
    {
        TechDefect::findOrFail($id)->delete();

        return redirect()->route('tech-defects.index')
        ->with('success','Report deleted');
    }
    public function show($id)
    {
        $report = \App\Models\TechDefect::with(['vessel','supports'])->findOrFail($id);
        $supports = $report->supports;
        $allSupportDone = true;
        foreach($supports as $s){
        if($s->status != 'Done'){
            $allSupportDone = false;
        }
    }
        return view('shipping.tech_defects.show',compact(
            'report',
            'supports',
            'allSupportDone'
        ));
    }
    /*public function storeThirdParty(Request $request)
    {
        ThirdPartySupport::create([
            'tech_defect_id' => $request->tech_defect_id,
            'technician' => $request->technician,
            'spares_required' => $request->spares_required,
            'tools_required' => $request->tools_required,
            'status' => $request->status
        ]);
        return back()->with('success','Third party support saved');
    }*/
}
