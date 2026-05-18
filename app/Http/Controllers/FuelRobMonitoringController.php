<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FuelRobMonitoring;
use App\Models\VoyageLogHeader;
use App\Models\VoyageLogDetail;

class FuelRobMonitoringController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'voyage_id' => 'required',
            'main_engine' => 'required|numeric',
            'auxiliary_engine' => 'required|numeric',
            'others' => 'nullable|numeric',
            'boiler' => 'nullable|numeric',
            'remarks' => 'nullable|string',
        ]);
        $voyage = VoyageLogHeader::findOrFail(
            $request->voyage_id
        );
        // kuha latest detail/status
        $detail = VoyageLogDetail::where(
            'voyage_id',
            $voyage->voyage_id
        )->latest('dtl_id')->first();
        $totalConsumed =
            ($request->main_engine ?? 0)
            + ($request->auxiliary_engine ?? 0)
            + ($request->boiler ?? 0)
            + ($request->others ?? 0);
        $remainingFuel =
            $request->beginning_fuel
            - $totalConsumed;
        // update fuel sa voyage header
        $voyage->update([
            'fuel_rob' => $remainingFuel . ' Liters'
        ]);
        if ($remainingFuel <= 0) {
            return back()->with(
                'error',
                'Fuel consumption exceeds remaining fuel.'
            );
        }
        FuelRobMonitoring::create([
            'voyage_id' => $voyage->voyage_id,
            'voyage_detail_id' => $detail?->dtl_id,
            'vessel_id' => $voyage->vessel_id,
            'beginning_fuel' => $request->beginning_fuel,
            'main_engine' => $request->main_engine,
            'auxiliary_engine' => $request->auxiliary_engine,
            'boiler' => $request->boiler ?? 0,
            'others' => $request->others ?? 0,
            'total_consumed' => $totalConsumed,
            'remaining_fuel' => $remainingFuel,
            'remarks' => $request->remarks,
            'status_id' => $detail?->status,
            'created_by' => auth()->id(),
        ]);
        return back()->with(
            'success',
            'Fuel ROB updated successfully.'
        );
    }
    public function fuelBunkering(Request $request)
    {
        $request->validate([
            'voyage_id' => 'required',
            'received_fuel' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        $voyage = VoyageLogHeader::findOrFail($request->voyage_id);

        $detail = VoyageLogDetail::where(
            'voyage_id',
            $voyage->voyage_id
        )->latest('dtl_id')->first();

        $currentFuel = (float) $request->beginning_fuel;

        $receivedFuel = (float) $request->received_fuel;

        $newFuel = $currentFuel + $receivedFuel;

        FuelRobMonitoring::create([
            'voyage_id' => $voyage->voyage_id,
            'voyage_detail_id' => $detail->dtl_id,
            'vessel_id' => $voyage->vessel_id,

            'beginning_fuel' => $currentFuel,
            'received_fuel' => $receivedFuel,

            'main_engine' => 0,
            'auxiliary_engine' => 0,
            'others' => 0,
            'boiler' => 0,
            'total_consumed' => 0,

            'remaining_fuel' => $newFuel,

            'remarks' => $request->remarks,

            'status_id' => $detail->status,
            'status_activity_id' => $request->activity_id,

            'created_by' => auth()->id(),
        ]);

        $voyage->update([
            'fuel_rob' => $newFuel . ' Liters'
        ]);

        return back()->with(
            'success',
            'Fuel bunkering added successfully.'
        );
    }
}