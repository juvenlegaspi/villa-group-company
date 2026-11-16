<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HaruhayLeaseContractController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => 'nullable|string|max:255', 'status' => ['nullable', Rule::in(\App\Http\Controllers\HaruhayTenantController::STATUSES)]]);
        $tenants = $this->tenants()->with(['unit.property', 'currentContract', 'latestContract'])->withCount('contracts')
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['search'] ?? null), fn ($q) => $q->where(fn ($n) => $n->where('name', 'like', '%'.$filters['search'].'%')->orWhere('code', 'like', '%'.$filters['search'].'%')->orWhereHas('unit', fn ($u) => $u->where('unit_no', 'like', '%'.$filters['search'].'%'))))
            ->orderBy('name')->paginate(10)->withQueryString();
        $base = HaruhayLeaseContract::whereHas('unit.property', fn ($q) => $q->where('division_id', $this->divisionId()));
        $stats = [
            'Total Contracts' => (clone $base)->count(),
            'Active' => (clone $base)->whereDoesntHave('amendment', fn ($q) => $q->whereDate('starts_on', '<=', today()))->whereNull('terminated_on')->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())->count(),
            'Expiring Soon' => (clone $base)->whereDoesntHave('amendment', fn ($q) => $q->whereDate('starts_on', '<=', today()))->whereNull('terminated_on')->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())->whereDate('ends_on', '<=', today()->addDays(30))->count(),
            'Overdue' => (clone $base)->whereDoesntHave('amendment', fn ($q) => $q->whereDate('starts_on', '<=', today()))->whereNull('terminated_on')->whereDate('ends_on', '<', today())->whereDoesntHave('renewal', fn ($q) => $q->whereNull('terminated_on'))->count(),
            'Terminated' => (clone $base)->whereNotNull('terminated_on')->count(),
        ];
        return view('haruhay.contracts.index', compact('tenants', 'stats') + ['statuses' => HaruhayTenantController::STATUSES]);
    }

    public function tenant(Request $request, HaruhayTenant $tenant)
    {
        $this->authorizeTenant($tenant);
        $tenant->load(['unit.property', 'currentContract', 'latestContract']);
        $contracts = $tenant->contracts()->with(['previousContract', 'renewal', 'creator'])->orderByDesc('starts_on')->orderByDesc('id')->get();
        $contracts = $contracts->sortBy(function ($contract) {
            $rank = match ($contract->status) {
                'Active', 'Expiring Soon' => 0, 'Overdue' => 1, 'Scheduled' => 2, 'Renewed' => 3, default => 4,
            };
            return [$rank, $contract->status === 'Scheduled' ? $contract->starts_on->timestamp : -$contract->starts_on->timestamp, -$contract->id];
        })->values();
        $focusContract = $contracts->first(fn ($contract) => in_array($contract->status, ['Active', 'Expiring Soon', 'Overdue', 'Scheduled'], true));
        $request->validate(['page' => 'nullable|integer|min:1']);
        $page = max(1, $request->integer('page', 1));
        $contracts = new \Illuminate\Pagination\LengthAwarePaginator(
            $contracts->forPage($page, 10)->values(), $contracts->count(), 10, $page,
            ['path' => $request->url(), 'query' => $request->except(['page', 'renew'])]
        );
        $renewalOf = null;
        if ($request->filled('renew')) {
            $this->authorizeManagement();
            $request->validate(['renew' => 'required|integer']);
            $renewalOf = $tenant->contracts()->with('renewal')->findOrFail($request->integer('renew'));
            abort_if($renewalOf->renewal, 422, 'This contract already has a renewal.');
            abort_unless($renewalOf->can_renew, 422, 'Renewal is available only when the contract is expiring soon or overdue.');
        }
        return view('haruhay.contracts.tenant', compact('tenant', 'contracts', 'renewalOf', 'focusContract') + ['canManage' => $this->canManage() && !\App\Models\HaruhayReservation::where('tenant_id', $tenant->id)->whereIn('status', ['AwaitingPayment', 'PaymentReview', 'PartiallyPaid'])->exists()]);
    }

    public function store(Request $request, HaruhayTenant $tenant)
    {
        $this->authorizeManagement();
        $this->authorizeTenant($tenant);
        if (\App\Models\HaruhayReservation::where('tenant_id', $tenant->id)->whereIn('status', ['AwaitingPayment', 'PaymentReview', 'PartiallyPaid'])->exists()) {
            $this->invalid('tenant', 'Verify the online reservation advance first. Its original contract is created automatically on confirmation.');
        }
        if ($request->has('duration_months')) {
            $period = $request->validateWithBag('contractForm', [
                'starts_on' => 'required|date_format:Y-m-d',
                'duration_months' => ['required', Rule::in([1, 3, 6, 12])],
            ]);
            $request->merge(['ends_on' => Carbon::parse($period['starts_on'])->addMonthsNoOverflow((int) $period['duration_months'])->subDay()->toDateString()]);
        }
        foreach (['monthly_rent', 'first_month_charge', 'deposit_required', 'advance_required'] as $field) {
            $value = $request->input($field);
            if (is_string($value) && preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/D', $value)) $request->merge([$field => str_replace(',', '', $value)]);
        }
        $money = 'required|numeric|min:0|max:9999999999.99|decimal:0,2';
        $data = $request->validateWithBag('contractForm', [
            'previous_contract_id' => 'nullable|integer',
            'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on',
            'monthly_rent' => $money,
            'billing_starts_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on',
            'first_due_on' => 'required|date_format:Y-m-d|after_or_equal:billing_starts_on',
            'due_day' => 'required|integer|min:1|max:31',
            'billing_cycle' => ['required', Rule::in(['calendar_month', 'lease_anniversary'])],
            'first_month_policy' => ['required', Rule::in(['full_month', 'prorated'])],
            'first_month_charge' => 'nullable|required_if:first_month_policy,prorated|numeric|min:0|max:9999999999.99|decimal:0,2',
            'deposit_required' => $money, 'advance_required' => $money,
            'advance_application' => ['nullable', Rule::requiredIf((float) $request->input('advance_required') > 0), 'string', 'max:2000'],
            'remarks' => 'nullable|string|max:10000', 'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);
        foreach (['billing_starts_on', 'first_due_on'] as $field) {
            if (Carbon::parse($data[$field])->gt(Carbon::parse($data['ends_on'])->addDay())) {
                $this->invalid($field, 'The billing date must be within the lease period or the day immediately after it ends.');
            }
        }
        unset($data['document']);
        if ($data['first_month_policy'] === 'full_month') $data['first_month_charge'] = $data['monthly_rent'];
        if ($data['billing_cycle'] === 'lease_anniversary' && (int) $data['due_day'] !== Carbon::parse($data['billing_starts_on'])->day) {
            $this->invalid('due_day', 'For anniversary billing, the monthly due day must match the billing start day.');
        }
        $path = null;
        try {
            $contract = DB::transaction(function () use ($request, $tenant, $data, &$path) {
                $person = HaruhayTenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $room = HaruhayUnit::whereKey($person->unit_id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $room->property->division_id === $this->divisionId(), 404);
                if ($person->status === 'Cancelled') $this->invalid('tenant', 'A cancelled reservation cannot receive a contract.');
                $existing = $person->contracts()->orderBy('id')->lockForUpdate()->get();
                if ($existing->isNotEmpty() && empty($data['previous_contract_id'])) {
                    $this->invalid('previous_contract_id', 'This tenant already has an original contract. Use Renew when the latest contract is expiring soon or overdue.');
                }
                $previous = null;
                if (! empty($data['previous_contract_id'])) {
                    $previous = $existing->firstWhere('id', (int) $data['previous_contract_id']);
                    if (! $previous || $previous->unit_id !== $room->id) $this->invalid('previous_contract_id', 'Select a previous contract for this tenant and current room.');
                    if ($existing->contains('previous_contract_id', $previous->id)) $this->invalid('previous_contract_id', 'This contract already has a renewal.');
                    if (! $previous->can_renew) $this->invalid('previous_contract_id', 'Renewal is available only when the contract is expiring soon or overdue.');
                    if ($existing->sortByDesc(fn ($c) => [$c->starts_on->timestamp, $c->id])->first()->id !== $previous->id) $this->invalid('previous_contract_id', 'Renew the latest contract in the tenant history.');
                    $effectiveEnd = $previous->terminated_on && $previous->terminated_on->lt($previous->ends_on) ? $previous->terminated_on : $previous->ends_on;
                    if (Carbon::parse($data['starts_on'])->lte($effectiveEnd)) $this->invalid('starts_on', 'A renewal must start after the previous contract ends.');
                }
                foreach ($existing->where('unit_id', $room->id) as $other) {
                    $end = $other->terminated_on && $other->terminated_on->lt($other->ends_on) ? $other->terminated_on : $other->ends_on;
                    if ($end->lt($other->starts_on)) continue;
                    if ($other->starts_on->lte(Carbon::parse($data['ends_on'])) && $end->gte(Carbon::parse($data['starts_on']))) $this->invalid('starts_on', 'These dates overlap an existing contract for this tenant and room.');
                }
                if ($request->hasFile('document') && ! $path) {
                    $path = $request->file('document')->store('haruhay/contracts/'.$person->id, 'local');
                    if (! $path) throw new \RuntimeException('Unable to save the signed contract.');
                }
                $record = $person->contracts()->create($data + [
                    'code' => 'pending-'.str()->uuid(), 'unit_id' => $room->id,
                    'tenant_name' => $person->name, 'property_name' => $room->property->name, 'room_no' => $room->unit_no, 'floor' => $room->floor,
                    'document_path' => $path, 'document_name' => $request->file('document')?->getClientOriginalName(), 'created_by' => auth()->id(),
                ]);
                $record->update(['code' => 'CTR-'.str_pad((string) $record->id, 4, '0', STR_PAD_LEFT)]);
                return $record;
            }, 3);
        } catch (\Throwable $exception) {
            if ($path) Storage::disk('local')->delete($path);
            throw $exception;
        }
        return to_route('haruhay.contracts.tenant', $tenant)->with('success', $contract->code.' saved. The contract terms are preserved in the tenant history.');
    }

    public function document(HaruhayTenant $tenant, HaruhayLeaseContract $contract)
    {
        $this->authorizeTenant($tenant);
        abort_unless($contract->tenant_id === $tenant->id && $contract->document_path && Storage::disk('local')->exists($contract->document_path), 404);
        return Storage::disk('local')->download($contract->document_path, $contract->document_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function terminate(Request $request, HaruhayTenant $tenant, HaruhayLeaseContract $contract)
    {
        $this->authorizeManagement(); $this->authorizeTenant($tenant);
        abort_unless($contract->tenant_id === $tenant->id, 404);
        $data = $request->validateWithBag('termination', ['terminated_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'termination_reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($contract, $data) {
            $locked = HaruhayLeaseContract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->terminated_on, 422, 'This contract is already terminated.');
            abort_if($locked->amendment, 422, 'This agreement was amended. Use the latest agreement.');
            if (Carbon::parse($data['terminated_on'])->gt($locked->ends_on)) throw ValidationException::withMessages(['terminated_on' => 'Termination date cannot be after the lease end date.'])->errorBag('termination');
            $locked->update($data + ['terminated_by' => auth()->id()]);
        });
        return to_route('haruhay.contracts.tenant', $tenant)->with('success', 'Contract terminated. Tenant occupancy is unchanged.');
    }

    public function upload(Request $request, HaruhayTenant $tenant, HaruhayLeaseContract $contract)
    {
        $this->authorizeManagement(); $this->authorizeTenant($tenant);
        abort_unless($contract->tenant_id === $tenant->id, 404);
        $request->validateWithBag('termination', ['document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240']);
        $path = null;
        try {
            DB::transaction(function () use ($request, $contract, &$path) {
                $locked = HaruhayLeaseContract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
                abort_if($locked->document_path, 422, 'A signed document is already attached.');
                $path = $request->file('document')->store('haruhay/contracts/'.$locked->tenant_id, 'local');
                if (! $path) throw new \RuntimeException('Unable to save the signed contract.');
                $locked->update(['document_path' => $path, 'document_name' => $request->file('document')->getClientOriginalName()]);
            });
        } catch (\Throwable $exception) {
            if ($path) Storage::disk('local')->delete($path);
            throw $exception;
        }
        return to_route('haruhay.contracts.tenant', $tenant)->with('success', 'Signed contract attached.');
    }

    private function divisionId(): int { return (int) Division::whereRaw('LOWER(name) = ?', ['haruhay'])->sole()->id; }
    private function tenants() { return HaruhayTenant::whereHas('unit.property', fn ($q) => $q->where('division_id', $this->divisionId())); }
    private function authorizeTenant(HaruhayTenant $tenant): void { abort_unless($this->tenants()->whereKey($tenant->id)->exists(), 404); }
    private function canManage(): bool { return app(\App\Services\HaruhayAccessService::class)->allows(auth()->user(), 'contracts.manage'); }
    private function authorizeManagement(): void { abort_unless($this->canManage(), 403); }
    private function invalid(string $field, string $message): never { throw ValidationException::withMessages([$field => $message])->errorBag('contractForm'); }
}
