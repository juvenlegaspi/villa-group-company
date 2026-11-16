<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\HaruhayProperty;
use App\Models\HaruhayUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HaruhayUnitController extends Controller
{
    public const STATUSES = ['Vacant', 'Occupied', 'Reserved'];
    public const CONDITIONS = ['Ready', 'Under Maintenance', 'Under Construction', 'Out of Service'];
    public const RENT_BASES = ['per_room' => 'Per room', 'per_person' => 'Per person', 'occupancy_based' => 'Single / shared occupancy'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'property_id' => ['nullable', Rule::exists('haruhay_properties', 'id')->where('division_id', $this->divisionId())],
            'floor' => 'nullable|integer|min:0|max:999',
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'room_condition' => ['nullable', Rule::in(self::CONDITIONS)],
        ]);
        $base = HaruhayUnit::whereHas('property', fn ($q) => $q->where('division_id', $this->divisionId()));
        $stats = $this->stats();
        $units = $base->with('property')->withSum([
            'tenants as occupants_count' => fn ($q) => $q->whereIn('status', \App\Models\HaruhayTenant::LIVE_STATUSES),
            'tenants as reservations_count' => fn ($q) => $q->where('status', 'Pending Move-in'),
        ], 'capacity_slots')
            ->when(filled($filters['property_id'] ?? null), fn ($q) => $q->where('property_id', $filters['property_id']))
            ->when(isset($filters['floor']), fn ($q) => $q->where('floor', $filters['floor']))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['room_condition'] ?? null), fn ($q) => $q->where('room_condition', $filters['room_condition']))
            ->when(filled($filters['search'] ?? null), fn ($q) => $q->where(fn ($nested) => $nested
                ->where('unit_no', 'like', '%'.$filters['search'].'%')->orWhere('unit_type', 'like', '%'.$filters['search'].'%')
                ->orWhere('occupied_by', 'like', '%'.$filters['search'].'%')
                ->orWhereHas('property', fn ($p) => $p->where('name', 'like', '%'.$filters['search'].'%'))))
            ->orderBy('property_id')->orderBy('floor')->orderBy('id')->paginate(10)->withQueryString();
        return view('haruhay.units.index', compact('units', 'stats') + $this->formData());
    }

    public function store(Request $request)
    {
        $this->authorizeManagement();
        $data = $this->validated($request);
        \Illuminate\Support\Facades\DB::transaction(fn () => HaruhayUnit::create($data + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]));
        return to_route('haruhay.units.index')->with('success', 'Room added successfully.');
    }

    public function show(HaruhayUnit $unit)
    {
        $this->authorizeUnit($unit);
        $unit->load('property');
        return view('haruhay.units.show', compact('unit') + $this->formData());
    }

    public function edit(HaruhayUnit $unit)
    {
        $this->authorizeManagement();
        $this->authorizeUnit($unit);
        return view('haruhay.units.index', [
            'units' => HaruhayUnit::whereHas('property', fn ($q) => $q->where('division_id', $this->divisionId()))->with('property')->withSum(['tenants as occupants_count' => fn ($q) => $q->whereIn('status', ['Active', 'On Notice']), 'tenants as reservations_count' => fn ($q) => $q->where('status', 'Pending Move-in')], 'capacity_slots')->orderBy('property_id')->orderBy('floor')->orderBy('id')->paginate(10),
            'stats' => $this->stats(), 'editingUnit' => $unit,
        ] + $this->formData());
    }

    public function update(Request $request, HaruhayUnit $unit)
    {
        $this->authorizeManagement();
        $this->authorizeUnit($unit);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $unit) {
            $locked = HaruhayUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $data = $this->validated($request, $locked);
            $count = $locked->tenants()->whereIn('status', \App\Models\HaruhayTenant::HELD_STATUSES)->orderBy('id')->lockForUpdate()->get()->sum('capacity_slots');
            if ($count > 0 && (int) $data['property_id'] !== (int) $locked->property_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['property_id' => 'Transfer the active tenants before changing this room’s property.'])->errorBag('unitForm');
            }
            if ((int) $data['property_id'] !== (int) $locked->property_id && (\App\Models\HaruhayLeaseContract::where('unit_id', $locked->id)->exists() || \App\Models\HaruhayBilling::where('unit_id', $locked->id)->exists() || \App\Models\HaruhayReservation::where('unit_id', $locked->id)->exists())) {
                throw \Illuminate\Validation\ValidationException::withMessages(['property_id' => 'This room has contract or billing history. Keep its original property to preserve financial records.'])->errorBag('unitForm');
            }
            $effectiveCapacity = array_key_exists('capacity', $data) ? ($data['capacity'] ?? 1) : ($locked->capacity ?? 1);
            if ($count > $effectiveCapacity) {
                throw \Illuminate\Validation\ValidationException::withMessages(['capacity' => 'Capacity cannot be lower than the total occupied and reserved slots.'])->errorBag('unitForm');
            }
            $locked->update($data + ['updated_by' => auth()->id()]);
        });
        return to_route('haruhay.units.index')->with('success', 'Room updated successfully.');
    }

    private function validated(Request $request, ?HaruhayUnit $unit = null): array
    {
        foreach (['monthly_rent', 'shared_rate'] as $field) {
            $amount = $request->input($field);
            if (is_string($amount) && preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/D', $amount)) {
                $request->merge([$field => str_replace(',', '', $amount)]);
            }
        }
        $data = $request->validateWithBag('unitForm', [
            'property_id' => ['required', Rule::exists('haruhay_properties', 'id')->where('division_id', $this->divisionId())],
            'floor' => 'required|integer|min:0|max:999',
            'unit_type' => 'required|string|max:255', 'area' => 'nullable|numeric|decimal:0,2|min:0|max:9999999999.99',
            'capacity' => 'nullable|integer|min:1|max:999',
            'rent_basis' => ['required', Rule::in(array_keys(self::RENT_BASES))],
            'monthly_rent' => 'nullable|required_if:rent_basis,occupancy_based|numeric|decimal:0,2|min:0|max:9999999999.99',
            'shared_rate' => 'nullable|required_if:rent_basis,occupancy_based|numeric|decimal:0,2|min:0|max:9999999999.99',
            'rate_notes' => 'nullable|string|max:2000',
            'room_condition' => ['required', Rule::in(self::CONDITIONS)],
            'remarks' => 'nullable|string|max:10000',
        ]);
        if ($data['rent_basis'] !== 'occupancy_based') $data['shared_rate'] = null;
        if (! $unit) {
            $data['status'] = 'Vacant';
            $data['occupied_by'] = null;
            $data['move_in_date'] = null;
        }
        return $data;
    }

    private function stats(): array
    {
        $base = HaruhayUnit::whereHas('property', fn ($q) => $q->where('division_id', $this->divisionId()));
        $stats = ['Total Units' => (clone $base)->count()];
        foreach (self::STATUSES as $status) $stats[$status] = (clone $base)->where('status', $status)->count();
        $stats['Under Maintenance'] = (clone $base)->where('room_condition', 'Under Maintenance')->count();
        $stats['Available'] = (clone $base)->where('status', 'Vacant')->where('room_condition', 'Ready')->count();
        return $stats;
    }
    private function divisionId(): int { return (int) Division::whereRaw('LOWER(name) = ?', ['haruhay'])->sole()->id; }
    private function authorizeUnit(HaruhayUnit $unit): void { abort_unless((int) $unit->property?->division_id === $this->divisionId(), 404); }
    private function canManage(): bool { return app(\App\Services\HaruhayAccessService::class)->allows(auth()->user(), 'units.manage'); }
    private function authorizeManagement(): void { abort_unless($this->canManage(), 403); }
    private function formData(): array
    {
        return ['properties' => HaruhayProperty::where('division_id', $this->divisionId())->orderBy('name')->get(), 'statuses' => self::STATUSES, 'conditions' => self::CONDITIONS, 'rentBases' => self::RENT_BASES, 'canManage' => $this->canManage()];
    }
}
