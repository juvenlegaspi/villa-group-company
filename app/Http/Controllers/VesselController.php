<?php

namespace App\Http\Controllers;

use App\Models\CrewLocationPortal;
use App\Models\User;
use App\Models\UserVesselAssignment;
use App\Models\Vessel;
use App\Models\VoyageLogHeader;
use App\Services\VesselAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VesselController extends Controller
{
    public function __construct(private readonly VesselAccessService $vesselAccess) {}

    public function index()
    {
        $user = auth()->user();
        $vessels = $this->vesselAccess->scopeAccessible(Vessel::query(), $user)
            ->with('captain')
            ->orderBy('id', 'asc')
            ->paginate(10);
        $captains = $this->getCaptains();
        $canManageLocationPortal = ! $user->isExecutiveViewer() && $this->vesselAccess->canAccessAllVessels($user);
        $crewLocationUrl = $canManageLocationPortal
            ? route('crew-location.show', CrewLocationPortal::current()->token)
            : null;

        return view('shipping.vessels.index', compact('vessels', 'captains', 'canManageLocationPortal', 'crewLocationUrl'));
    }

    public function create()
    {
        $this->authorizeVesselCreation();
        $captains = $this->getCaptains();

        return view('shipping.vessels.create', compact('captains'));
    }

    public function store(Request $request)
    {
        $this->authorizeVesselCreation();
        $data = $this->validateVessel($request);
        $vessel = Vessel::create($data);
        $this->syncCaptainAssignment($vessel);

        return redirect()->route('vessels.index')
            ->with('success', 'Vessel added successfully.');
    }

    public function edit($id)
    {
        $vessel = Vessel::findOrFail($id);
        $this->authorizeVesselManagement();
        $captains = $this->getCaptains();

        return view('shipping.vessels.edit', compact('vessel', 'captains'));
    }

    public function update(Request $request, $id)
    {
        $vessel = Vessel::findOrFail($id);
        $this->authorizeVesselManagement();
        $data = $this->validateVessel($request);
        $previousCaptainId = $vessel->captain_id;
        $vessel->update($data);
        $this->syncCaptainAssignment($vessel, $previousCaptainId);

        return redirect()->route('vessels.index')
            ->with('success', 'Vessel updated successfully.');
    }

    public function show(Request $request, $id)
    {
        $vessel = Vessel::findOrFail($id);
        $this->authorizeVesselAccess($vessel);
        $query = VoyageLogHeader::query()->where('vessel_id', $id);
        if ($request->filled('search')) {
            $query->where(function ($voyageQuery) use ($request) {
                $voyageQuery->where('voyage_id', 'like', '%'.$request->search.'%')
                    ->orWhere('port_location', 'like', '%'.$request->search.'%')
                    ->orWhere('cargo_type', 'like', '%'.$request->search.'%');
            });
        }
        match ($request->sort) {
            'date' => $query->orderByDesc('date_created'),
            default => $query->orderByDesc('voyage_id'),
        };
        $voyages = $query->paginate(10)->withQueryString();
        $hasOpenVoyage = VoyageLogHeader::where('vessel_id', $id)
            ->where(function ($query) {
                $query->whereNull('date_completed')
                    ->orWhere('status', '!=', 'COMPLETED');
            })
            ->exists();

        return view('shipping.vessels.show', compact('vessel', 'voyages', 'hasOpenVoyage'));
    }

    public function regenerateLocationPortalLink()
    {
        abort_if(auth()->user()->isExecutiveViewer(), 403);
        $this->authorizeVesselManagement();
        CrewLocationPortal::current()->update([
            'token' => Str::random(64),
            'generated_by' => auth()->id(),
        ]);

        return redirect()->route('vessels.index')
            ->with('success', 'A new shared crew location link was generated. The previous link no longer works.');
    }

    protected function getCaptains()
    {
        return User::query()
            ->where('status', true)
            ->whereHas('division', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['villa shipping lines']))
            ->where(fn ($query) => $query->where('role', 'captain')
                ->orWhereHas('position', fn ($positions) => $positions->where('code', 'vessel-captain')))
            ->orderBy('name')
            ->get();
    }

    protected function validateVessel(Request $request): array
    {
        return $request->validate([
            'vessel_name' => 'required|string|max:255',
            'captain_id' => 'nullable|exists:users,id',
            'imo_number' => 'nullable|string|max:255',
            'call_sign' => 'nullable|string|max:255',
            'vessel_type' => 'nullable|string|max:255',
            'dwt' => 'nullable|string|max:255',
            'fuel_type' => 'nullable|string|max:255',
            'service_speed' => 'nullable|string|max:255',
            'charter_type' => 'nullable|string|max:255',
            'vessel_status' => 'nullable|string|max:255',
        ]);
    }

    protected function authorizeVesselManagement(): void
    {
        abort_unless($this->vesselAccess->canAccessAllVessels(auth()->user()), 403);
    }

    protected function authorizeVesselCreation(): void
    {
        abort_unless(auth()->user()->isSystemAdministrator(), 403, 'Only a system administrator can add vessels.');
    }

    protected function authorizeVesselAccess(Vessel $vessel): void
    {
        $this->vesselAccess->authorize(auth()->user(), $vessel);
    }

    protected function syncCaptainAssignment(Vessel $vessel, ?int $previousCaptainId = null): void
    {
        if ($previousCaptainId && (int) $previousCaptainId !== (int) $vessel->captain_id) {
            UserVesselAssignment::where(['user_id' => $previousCaptainId, 'vessel_id' => $vessel->id])->update([
                'is_primary' => false,
                'is_active' => false,
                'effective_until' => today(),
            ]);
        }

        if ($vessel->captain_id) {
            UserVesselAssignment::updateOrCreate(
                ['user_id' => $vessel->captain_id, 'vessel_id' => $vessel->id],
                ['assigned_by' => auth()->id(), 'is_primary' => true, 'is_active' => true, 'effective_from' => today(), 'effective_until' => null]
            );
        }
    }
}
