<?php

namespace App\Http\Controllers;

use App\Models\TechDefect;
use App\Models\ThirdPartySupport;
use Illuminate\Http\Request;

class ThirdPartyController extends Controller
{
    public function store(Request $request, $id)
    {
        $data = $request->validate([
            'reason_for_support' => 'required|string',
            'spares_required' => 'required|string|max:255',
            'tools_required' => 'required|string|max:255',
            'status' => 'nullable|string|max:255',
        ]);

        ThirdPartySupport::create([
            'tech_defect_id' => $id,
            'reason_for_support' => $data['reason_for_support'],
            'spares_required' => $data['spares_required'],
            'tools_required' => $data['tools_required'],
            'status' => $data['status'] ?? 'Ongoing',
        ]);

        $report = TechDefect::findOrFail($id);
        $report->update([
            'third_party_required' => 'Yes',
            'status' => 'Waiting 3rd Party',
        ]);

        return back()->with('success', '3rd party support added successfully.');
    }
}
