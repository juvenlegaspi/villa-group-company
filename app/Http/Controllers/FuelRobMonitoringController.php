<?php

namespace App\Http\Controllers;

use App\Models\FuelRobMonitoring;
use App\Models\VoyageLogDetail;
use App\Models\VoyageLogHeader;
use App\Services\VesselAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FuelRobMonitoringController extends Controller
{
    public function __construct(private readonly VesselAccessService $vesselAccess) {}

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

        abort_if($voyage->status === 'COMPLETED', 422, 'Fuel cannot be changed on a completed voyage.');

        DB::transaction(function () use ($data, $voyage): void {
            $lockedVoyage = VoyageLogHeader::whereKey($voyage->voyage_id)->lockForUpdate()->firstOrFail();
            $detail = VoyageLogDetail::where('voyage_id', $lockedVoyage->voyage_id)->latest('dtl_id')->first();
            $latestFuel = FuelRobMonitoring::where('voyage_id', $lockedVoyage->voyage_id)
                ->latest('fuel_id')->lockForUpdate()->first();
            $beginningFuel = $latestFuel ? (float) $latestFuel->remaining_fuel : (float) $data['beginning_fuel'];
            $totalConsumed = (float) $data['main_engine'] + (float) $data['auxiliary_engine']
                + (float) ($data['boiler'] ?? 0) + (float) ($data['others'] ?? 0);
            $remainingFuel = $beginningFuel - $totalConsumed;

            if ($remainingFuel < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'main_engine' => 'Fuel consumption exceeds the current fuel balance.',
                ]);
            }

            FuelRobMonitoring::create([
                'voyage_id' => $lockedVoyage->voyage_id,
                'voyage_detail_id' => $detail?->dtl_id,
                'vessel_id' => $lockedVoyage->vessel_id,
                'beginning_fuel' => $beginningFuel,
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
            $lockedVoyage->update(['fuel_rob' => $remainingFuel.' Liters']);
        });

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

        abort_if($voyage->status === 'COMPLETED', 422, 'Fuel cannot be changed on a completed voyage.');

        DB::transaction(function () use ($data, $request, $voyage): void {
            $lockedVoyage = VoyageLogHeader::whereKey($voyage->voyage_id)->lockForUpdate()->firstOrFail();
            $detail = VoyageLogDetail::where('voyage_id', $lockedVoyage->voyage_id)->latest('dtl_id')->first();
            $latestFuel = FuelRobMonitoring::where('voyage_id', $lockedVoyage->voyage_id)
                ->latest('fuel_id')->lockForUpdate()->first();
            $currentFuel = $latestFuel ? (float) $latestFuel->remaining_fuel : (float) $data['beginning_fuel'];
            $receivedFuel = (float) $data['received_fuel'];
            $newFuel = $currentFuel + $receivedFuel;

            FuelRobMonitoring::create([
                'voyage_id' => $lockedVoyage->voyage_id,
                'voyage_detail_id' => $detail?->dtl_id,
                'vessel_id' => $lockedVoyage->vessel_id,
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
                'status_activity_id' => $request->integer('activity_id') ?: null,
                'created_by' => auth()->id(),
            ]);
            $lockedVoyage->update(['fuel_rob' => $newFuel.' Liters']);
        });

        return back()->with('success', 'Fuel bunkering added successfully.');
    }

    protected function authorizeVoyageAccess(VoyageLogHeader $voyage): void
    {
        $voyage->loadMissing('vessel');
        $this->vesselAccess->authorize(auth()->user(), $voyage->vessel);
    }
}
