<?php

namespace App\Http\Controllers;

use App\Models\DryDockingDetail;
use App\Models\DryDockingHeader;
use App\Models\Vessel;
use App\Services\VesselAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DryDockingHeaderController extends Controller
{
    public function __construct(private readonly VesselAccessService $vesselAccess) {}

    public function index()
    {
        $vesselIds = $this->vesselAccess->scopeAccessible(Vessel::query(), auth()->user())->select('id');
        $headers = DryDockingHeader::with('vessel')->whereIn('vessel_id', $vesselIds)->latest()->get();

        return view('shipping.dry_docking.index', compact('headers'));
    }

    public function create()
    {
        abort_unless($this->vesselAccess->canAccessAllVessels(auth()->user()), 403);
        $vessels = $this->vesselAccess->scopeAccessible(Vessel::query(), auth()->user())->orderBy('vessel_name')->get();

        return view('shipping.dry_docking.create', compact('vessels'));
    }

    public function store(Request $request)
    {
        abort_unless($this->vesselAccess->canAccessAllVessels(auth()->user()), 403);
        $data = $request->validate([
            'vessel_id' => 'required|exists:vessels,id',
            'arrival_date' => 'nullable|date',
            'docking_date' => 'nullable|date',
            'laydays' => 'nullable|integer|min:0',
            'undocking_date' => 'nullable|date|after_or_equal:docking_date',
            'vessel_manager' => 'nullable|string|max:255',
            'status' => 'required|in:pending,ongoing,completed,operational,non-operational',
        ]);
        $this->vesselAccess->authorize(auth()->user(), (int) $data['vessel_id']);

        DryDockingHeader::create([
            ...$data,
            'is_shipyard' => $request->boolean('is_shipyard'),
            'is_inhouse' => $request->boolean('is_inhouse'),
            'create_date' => now(),
        ]);

        return redirect('/shipping/dry-docking')->with('success', 'Dry docking record saved successfully.');
    }

    public function details($id)
    {
        $header = DryDockingHeader::with(['vessel', 'details'])->findOrFail($id);
        $this->vesselAccess->authorize(auth()->user(), $header->vessel);

        return view('shipping.dry_docking.details', compact('header'));
    }

    public function storeDetails(Request $request, $id)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.scope_of_work' => 'nullable|string|max:1000',
            'items.*.plan_duration' => 'nullable|integer|min:0',
            'items.*.actual_duration' => 'nullable|integer|min:0',
            'items.*.status' => 'nullable|in:completed,near completion,not started,ongoing',
            'items.*.daily_status' => 'nullable|in:ahead of schedule,on schedule,not started,delayed',
            'items.*.weight' => 'nullable|numeric|min:0|max:100',
            'items.*.actual_progress' => 'nullable|numeric|min:0|max:100',
            'items.*.activity' => 'nullable|string|max:255',
            'items.*.remarks' => 'nullable|string|max:255',
        ]);

        $header = DryDockingHeader::findOrFail($id);
        abort_unless($this->vesselAccess->canAccessAllVessels(auth()->user()), 403);
        $this->vesselAccess->authorize(auth()->user(), (int) $header->vessel_id);
        DB::transaction(function () use ($data, $header): void {
            foreach ($data['items'] as $item) {
                if (blank($item['scope_of_work'] ?? null) && blank($item['activity'] ?? null)) {
                    continue;
                }

                DryDockingDetail::create([
                    'vessel_id' => $header->vessel_id,
                    'dry_dock_id' => $header->id,
                    ...$item,
                ]);
            }
        });

        return back()->with('success', 'Dry docking details saved successfully.');
    }
}
