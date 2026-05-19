<?php

namespace App\Http\Controllers;

use App\Models\FuelRobMonitoring;
use App\Models\VoyageLogDetail;
use App\Models\VoyageLogHeader;
use Illuminate\Http\Request;

class FuelRobMonitoringController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'voyage_id' => 'required',
            'beginning_fuel' => 'required|numeric|min:0',
            'main_engine' => 'required|numeric',
            'auxiliary_engine' => 'required|numeric',
            'others' => 'nullable|numeric',
            'boiler' => 'nullable|numeric',
            'remarks' => 'nullable|string',
        ]);

        $voyage = VoyageLogHeader::findOrFail($data['voyage_id']);
        $this->authorizeVoyageAccess($voyage);

        $detail = VoyageLogDetail::where('voyage_id', $voyage->voyage_id)
            ->latest('dtl_id')
            ->first();

        $totalConsumed =
            ($data['main_engine'] ?? 0)
            + ($data['auxiliary_engine'] ?? 0)
            + ($data['boiler'] ?? 0)
            + ($data['others'] ?? 0);

        $remainingFuel = $data['beginning_fuel'] - $totalConsumed;

        if ($remainingFuel <= 0) {
            return back()->with('error', 'Fuel consumption exceeds remaining fuel.');
        }

        $voyage->update([
            'fuel_rob' => $remainingFuel.' Liters',
        ]);

        FuelRobMonitoring::create([
            'voyage_id' => $voyage->voyage_id,
            'voyage_detail_id' => $detail?->dtl_id,
            'vessel_id' => $voyage->vessel_id,
            'beginning_fuel' => $data['beginning_fuel'],
            'main_engine' => $data['main_engine'],
            'auxiliary_engine' => $data['auxiliary_engine'],
            'boiler' => $data['boiler'] ?? 0,
            'others' => $data['others'] ?? 0,
            'total_consumed' => $totalConsumed,
            'remaining_fuel' => $remainingFuel,
            'remarks' => $data['remarks'] ?? null,
            'status_id' => $detail?->status,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Fuel ROB updated successfully.');
    }

    public function fuelBunkering(Request $request)
    {
        $data = $request->validate([
            'voyage_id' => 'required',
            'beginning_fuel' => 'required|numeric|min:0',
            'received_fuel' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        $voyage = VoyageLogHeader::findOrFail($data['voyage_id']);
        $this->authorizeVoyageAccess($voyage);

        $detail = VoyageLogDetail::where('voyage_id', $voyage->voyage_id)
            ->latest('dtl_id')
            ->first();

        $currentFuel = (float) $data['beginning_fuel'];
        $receivedFuel = (float) $data['received_fuel'];
        $newFuel = $currentFuel + $receivedFuel;

        FuelRobMonitoring::create([
            'voyage_id' => $voyage->voyage_id,
            'voyage_detail_id' => $detail?->dtl_id,
            'vessel_id' => $voyage->vessel_id,
            'beginning_fuel' => $currentFuel,
            'received_fuel' => $receivedFuel,
            'main_engine' => 0,
            'auxiliary_engine' => 0,
            'others' => 0,
            'boiler' => 0,
            'total_consumed' => 0,
            'remaining_fuel' => $newFuel,
            'remarks' => $data['remarks'] ?? null,
            'status_id' => $detail?->status,
            'status_activity_id' => $request->activity_id,
            'created_by' => auth()->id(),
        ]);

        $voyage->update([
            'fuel_rob' => $newFuel.' Liters',
        ]);

        return back()->with('success', 'Fuel bunkering added successfully.');
    }

    protected function authorizeVoyageAccess(VoyageLogHeader $voyage): void
    {
        $user = auth()->user();

        if ($user->isAdmin() || $user->role === 'manager') {
            return;
        }

        $voyage->loadMissing('vessel');

        abort_unless($voyage->vessel?->captain_id === $user->id, 403);
    }
}
