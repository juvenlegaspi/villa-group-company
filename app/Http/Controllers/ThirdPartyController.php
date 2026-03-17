<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ThirdPartySupport;
use App\Models\TechDefect;

class ThirdPartyController extends Controller
{
    public function store(Request $request,$id)
    {
        ThirdPartySupport::create([
            'tech_defect_id' => $id,
            'reason_for_support' => $request->reason_for_support,
            'spares_required' => $request->spares_required,
            'tools_required' => $request->tools_required,
            'status' => $request->status
            ]);
        // UPDATE sa tech_defects table
        $report = TechDefect::findOrFail($id);
        $report->update([
            'third_party_required' => 'Yes'
        ]);
        return back()->with('success','3rd Party Support Added');
    }
}