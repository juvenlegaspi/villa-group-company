<?php

namespace App\Http\Controllers;

use App\Models\TechDefect;
use App\Models\ThirdPartySupport;
use App\Models\Vessel;
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

        $report = TechDefect::findOrFail($id);
        $this->authorizeTechDefectAccess($report);

        ThirdPartySupport::create([
            'tech_defect_id' => $id,
            'reason_for_support' => $data['reason_for_support'],
            'spares_required' => $data['spares_required'],
            'tools_required' => $data['tools_required'],
            'status' => $data['status'] ?? 'Ongoing',
        ]);

        $report->update([
            'third_party_required' => 'Yes',
            'status' => 'Waiting 3rd Party',
        ]);

        return back()->with('success', '3rd party support added successfully.');
    }

    protected function authorizeTechDefectAccess(TechDefect $report): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        $allowedVessels = collect();

        if ($user->role === 'manager' && (int) $user->division_id === 2) {
            $allowedVessels = Vessel::pluck('id');
        }

        if ($user->role === 'captain' && (int) $user->division_id === 2) {
            $allowedVessels = Vessel::where('captain_id', $user->id)->pluck('id');
        }

        abort_unless($allowedVessels->contains((int) $report->vessel_id), 403, 'Unauthorized vessel.');
    }
}
