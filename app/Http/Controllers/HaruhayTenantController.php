<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Services\HaruhayTenantService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HaruhayTenantController extends Controller
{
    public const STATUSES = ['Pending Move-in', 'Active', 'On Notice', 'Moved Out', 'Cancelled'];

    public function index(Request $request, ?HaruhayTenant $editingTenant = null)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255', 'status' => ['nullable', Rule::in(self::STATUSES)],
            'property_id' => ['nullable', Rule::exists('haruhay_properties', 'id')->where('division_id', $this->divisionId())],
        ]);
        $base = $this->visible();
        $stats = ['Total Tenants' => (clone $base)->count()];
        foreach (self::STATUSES as $status) $stats[$status] = (clone $base)->where('status', $status)->count();
        $tenants = $base->with(['unit.property', 'currentContract', 'latestContract'])
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['property_id'] ?? null), fn ($q) => $q->whereHas('unit', fn ($u) => $u->where('property_id', $filters['property_id'])))
            ->when(filled($filters['search'] ?? null), fn ($q) => $q->where(fn ($n) => $n->where('name', 'like', '%'.$filters['search'].'%')->orWhere('code', 'like', '%'.$filters['search'].'%')->orWhere('contact_number', 'like', '%'.$filters['search'].'%')->orWhere('email', 'like', '%'.$filters['search'].'%')->orWhereHas('unit', fn ($u) => $u->where('unit_no', 'like', '%'.$filters['search'].'%'))))
            ->latest('id')->paginate(10)->withQueryString();
        return view('haruhay.tenants.index', compact('tenants', 'stats', 'editingTenant') + $this->formData());
    }

    public function store(Request $request, HaruhayTenantService $service)
    {
        $this->authorizeManagement();
        $service->save($this->validated($request), $this->divisionId());
        return to_route('haruhay.tenants.index')->with('success', 'Tenant added and room occupancy updated.');
    }

    public function show(HaruhayTenant $tenant)
    {
        $this->authorizeTenant($tenant);
        $tenant->load(['unit.property', 'currentContract', 'latestContract', 'audits' => fn ($q) => $q->with('user')->latest('id')]);
        $onlineReservation = \App\Models\HaruhayReservation::where('tenant_id', $tenant->id)->first();
        return view('haruhay.tenants.show', compact('tenant', 'onlineReservation') + $this->formData());
    }

    public function edit(Request $request, HaruhayTenant $tenant)
    {
        $this->authorizeManagement();
        $this->authorizeTenant($tenant);
        return $this->index($request, $tenant);
    }

    public function update(Request $request, HaruhayTenant $tenant, HaruhayTenantService $service)
    {
        $this->authorizeManagement();
        $this->authorizeTenant($tenant);
        $service->save($this->validated($request), $this->divisionId(), $tenant);
        return to_route('haruhay.tenants.show', $tenant)->with('success', 'Tenant updated and room occupancy synchronized.');
    }

    public function confirm(HaruhayTenant $tenant, HaruhayTenantService $service)
    {
        $this->authorizeManagement();
        $this->authorizeTenant($tenant);
        $service->save([], $this->divisionId(), $tenant, 'confirm');
        return to_route('haruhay.tenants.show', $tenant)->with('success', 'Move-in confirmed. The tenant is Active and the room is Occupied.');
    }

    public function cancel(HaruhayTenant $tenant, HaruhayTenantService $service)
    {
        $this->authorizeManagement();
        $this->authorizeTenant($tenant);
        $service->save([], $this->divisionId(), $tenant, 'cancel');
        return to_route('haruhay.tenants.show', $tenant)->with('success', 'Reservation cancelled. The reserved slot has been released.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validateWithBag('tenantForm', [
            'name' => 'required|string|max:255', 'contact_number' => 'required|string|max:50', 'email' => 'nullable|email|max:255',
            'property_id' => ['required', Rule::exists('haruhay_properties', 'id')->where('division_id', $this->divisionId())],
            'unit_id' => ['required', Rule::exists('haruhay_units', 'id')->where('property_id', $request->input('property_id'))],
            'move_in_date' => 'required|date_format:Y-m-d'.(in_array($request->input('status'), ['Pending Move-in', 'Cancelled'], true) ? '' : '|before_or_equal:today'),
            'move_out_date' => 'nullable|required_if:status,Moved Out|date_format:Y-m-d|after_or_equal:move_in_date|before_or_equal:today',
            'status' => ['required', Rule::in(self::STATUSES)],
            'emergency_contact_name' => 'nullable|required_with:emergency_contact_number|string|max:255',
            'emergency_contact_number' => 'nullable|required_with:emergency_contact_name|string|max:50',
            'remarks' => 'nullable|string|max:10000',
        ]);
        unset($data['property_id']);
        if ($data['status'] !== 'Moved Out') $data['move_out_date'] = null;
        return $data;
    }
    private function divisionId(): int { return (int) Division::whereRaw('LOWER(name) = ?', ['haruhay'])->sole()->id; }
    private function visible() { return HaruhayTenant::whereHas('unit.property', fn ($q) => $q->where('division_id', $this->divisionId())); }
    private function authorizeTenant(HaruhayTenant $tenant): void { abort_unless($this->visible()->whereKey($tenant->id)->exists(), 404); }
    private function canManage(): bool { return app(\App\Services\HaruhayAccessService::class)->allows(auth()->user(), 'tenants.manage'); }
    private function authorizeManagement(): void { abort_unless($this->canManage(), 403); }
    private function formData(): array
    {
        return [
            'properties' => HaruhayProperty::where('division_id', $this->divisionId())->orderBy('name')->get(),
            'rooms' => HaruhayUnit::whereHas('property', fn ($q) => $q->where('division_id', $this->divisionId()))->withSum([
                'tenants as occupants_count' => fn ($q) => $q->whereIn('status', HaruhayTenant::LIVE_STATUSES),
                'tenants as reservations_count' => fn ($q) => $q->where('status', 'Pending Move-in'),
            ], 'capacity_slots')->orderBy('property_id')->orderBy('floor')->orderBy('id')->get(),
            'statuses' => self::STATUSES, 'canManage' => $this->canManage(),
        ];
    }
}
