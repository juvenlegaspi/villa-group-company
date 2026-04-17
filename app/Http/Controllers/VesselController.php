<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vessel;
use App\Models\VoyageLogHeader;
use Illuminate\Http\Request;

class VesselController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $vessels = Vessel::query()
            ->with('captain')
            ->when(! $user->is_admin, fn ($query) => $query->where('captain_id', $user->id))
            ->paginate(10);

        $captains = $this->getCaptains();

        return view('shipping.vessels.index', compact('vessels', 'captains'));
    }

    public function create()
    {
        $captains = $this->getCaptains();

        return view('shipping.vessels.create', compact('captains'));
    }

    public function store(Request $request)
    {
        $data = $this->validateVessel($request);

        Vessel::create($data);

        return redirect()->route('vessels.index')
            ->with('success', 'Vessel added successfully.');
    }

    public function edit($id)
    {
        $vessel = Vessel::findOrFail($id);
        $captains = $this->getCaptains();

        return view('shipping.vessels.edit', compact('vessel', 'captains'));
    }

    public function update(Request $request, $id)
    {
        $vessel = Vessel::findOrFail($id);
        $data = $this->validateVessel($request);

        $vessel->update($data);

        return redirect()->route('vessels.index')
            ->with('success', 'Vessel updated successfully.');
    }

    public function show(Request $request, $id)
    {
        $vessel = Vessel::findOrFail($id);
        $query = VoyageLogHeader::query()->where('vessel_id', $id);

        if ($request->filled('search')) {
            $query->where(function ($voyageQuery) use ($request) {
                $voyageQuery->where('voyage_id', 'like', '%' . $request->search . '%')
                    ->orWhere('port_location', 'like', '%' . $request->search . '%')
                    ->orWhere('cargo_type', 'like', '%' . $request->search . '%');
            });
        }

        match ($request->sort) {
            'date' => $query->orderByDesc('date_created'),
            default => $query->orderByDesc('voyage_id'),
        };

        $voyages = $query->paginate(10)->withQueryString();

        return view('shipping.vessels.show', compact('vessel', 'voyages'));
    }

    protected function getCaptains()
    {
        return User::query()
            ->where('department_id', 1)
            ->where('role', 'captain')
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
}
