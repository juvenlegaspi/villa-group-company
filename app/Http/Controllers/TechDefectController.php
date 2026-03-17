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
        $status = $request->status;
        $search = $request->search;
        $query = TechDefect::with('vessel')->orderByRaw("
            CASE
            WHEN status = 'Open' THEN 1
            WHEN status = 'Ongoing' THEN 2
            WHEN status = 'Waiting 3rd Party' THEN 3
            WHEN status = 'Completed' THEN 4
            END
        ")->orderBy('id','asc');
        if($status){
            $query->where('status',$status);
        }
        if($search){
            $query->where(function($q) use ($search){
                $q->where('id','like',"%$search%")->orWhereHas('vessel',function($v) use ($search){
                    $v->where('vessel_name','like',"%$search%");
                });
            });
        }
        $reports = $query->paginate(10)->withQueryString();
        return view('shipping.tech_defects.index',compact('reports','status'));
    }
    public function create()
    {
        $vessels = Vessel::all();
        return view('shipping.tech_defects.create',compact('vessels'));
    }
    // create report of vessel
    public function store(Request $request)
    {
        $report = TechDefect::create($request->all());
        return redirect()->route('tech-defects.show', ['id' => $report->id])
        ->with('success','Report added');
    }

    public function edit($id)
    {
        $report = TechDefect::findOrFail($id);
        $vessels = Vessel::all();

        return view('shipping.tech_defects.edit',compact('report','vessels'));
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
