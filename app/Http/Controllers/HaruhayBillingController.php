<?php

namespace App\Http\Controllers;

use App\Models\{Division, HaruhayBilling, HaruhayBillingPayment, HaruhayLeaseContract, HaruhayTenant};
use App\Services\HaruhayBillingSchedule;
use App\Services\HaruhayBillingWorkflow;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HaruhayBillingController extends Controller
{
    public const CHARGES = ['water' => 'Water', 'electric' => 'Electric', 'utilities_other' => 'Other Utilities', 'parking' => 'Parking', 'addons_other' => 'Other Add-ons', 'penalty' => 'Penalty', 'discount' => 'Discount'];

    public function index(Request $request, HaruhayBillingWorkflow $workflow)
    {
        if ($request->filled('tenant_id')) return to_route('haruhay.billing.tenant', $request->integer('tenant_id'));
        $filters = $request->validate(['search' => 'nullable|string|max:255']);
        $tenants = $this->tenants()->with('unit.property')->withCount(['billings as draft_count' => fn ($q) => $q->whereNull('finalized_at')])
            ->with(['billings' => fn ($q) => $q->whereNotNull('finalized_at')->withSum('payments', 'amount')])
            ->when(filled($filters['search'] ?? null), fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$filters['search'].'%')->orWhere('code', 'like', '%'.$filters['search'].'%')->orWhereHas('unit', fn ($u) => $u->where('unit_no', 'like', '%'.$filters['search'].'%'))))
            ->orderBy('name')->paginate(10)->withQueryString();
        $nextBills = [];
        foreach ($tenants as $tenant) {
            $unpaid = $workflow->rows($tenant, $this->divisionId())->filter(fn ($row) => !$row['paid'])->values();
            $current = $unpaid->first();
            $nextBills[$tenant->id] = $current && $current['invoice']?->finalized_at ? $unpaid->get(1) : $current;
        }
        return view('haruhay.billing.index', compact('tenants', 'nextBills') + ['stats' => $this->stats()]);
    }

    public function tenant(Request $request, HaruhayTenant $tenant, HaruhayBillingSchedule $schedule)
    {
        abort_unless($this->tenants()->whereKey($tenant->id)->exists(), 404);
        $request->validate(['page' => 'nullable|integer|min:1', 'contract' => 'nullable|integer', 'period' => 'nullable|date_format:Y-m-d', 'tab' => 'nullable|in:current,paid']);
        $tenant->load('unit.property');
        $workflow = app(HaruhayBillingWorkflow::class);
        $rows = $workflow->rows($tenant, $this->divisionId());
        $currentKey = $rows->first(fn ($row) => !$row['paid'])['key'] ?? null;
        $tab = $request->input('tab', 'current');
        $editing = null;
        if ($request->filled('contract') || $request->filled('period')) {
            $editing = $rows->first(fn ($row) => $row['contract']->id === $request->integer('contract') && $row['period']['start'] === $request->input('period') && !$row['invoice']);
            abort_unless($editing && $editing['key'] === $currentKey && $tab === 'current' && $this->canManage(), 403);
        }
        $paidCount = $rows->where('paid', true)->count();
        $currentCount = $rows->where('paid', false)->count();
        $balance = $rows->filter(fn ($row) => $row['invoice']?->finalized_at)->sum(fn ($row) => $row['invoice']->balance_cents);
        $visibleRows = $tab === 'paid' ? $rows->where('paid', true)->sortByDesc('period.start')->values() : $rows->where('paid', false)->values();
        $bills = new LengthAwarePaginator($visibleRows->forPage($request->integer('page', 1), 10)->values(), $visibleRows->count(), 10, $request->integer('page', 1), ['path' => $request->url(), 'query' => $request->except('page', 'contract', 'period')]);
        return view('haruhay.billing.tenant', compact('tenant', 'bills', 'editing', 'currentKey', 'tab', 'paidCount', 'currentCount', 'balance') + ['canManage' => $this->canManage(), 'charges' => self::CHARGES]);
    }
    public function store(Request $request, HaruhayBillingSchedule $schedule)
    {
        abort_unless($this->canManage(), 403);
        $data = $this->validated($request, true);
        $contract = HaruhayLeaseContract::whereHas('unit.property', fn ($q) => $q->where('division_id', $this->divisionId()))->findOrFail($data['contract_id']);
        $invoice = DB::transaction(function () use ($data, $contract, $schedule) {
            $tenant = HaruhayTenant::whereKey($contract->tenant_id)->lockForUpdate()->firstOrFail();
            $contract = HaruhayLeaseContract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            if ($tenant->status === 'Cancelled') $this->invalid('contract_id', 'A cancelled tenant cannot receive a new invoice.');
            $period = collect($schedule->periods($contract))->firstWhere('start', $data['period_start']);
            if (!$period) $this->invalid('period_start', 'Select a billing period covered by this contract.');
            if (HaruhayBilling::where('tenant_id', $tenant->id)->where('unit_id', $contract->unit_id)->where('period_start', '<=', $period['end'])->where('period_end', '>=', $period['start'])->exists()) $this->invalid('period_start', 'This tenant already has an invoice covering this period.');
            $this->ensureCurrent($tenant, $contract->id, $period['start']);
            $invoice = HaruhayBilling::create($data + ['code' => 'pending-'.str()->uuid(), 'tenant_id' => $tenant->id, 'unit_id' => $contract->unit_id, 'tenant_name' => $tenant->name, 'property_name' => $contract->property_name, 'room_no' => $contract->room_no, 'period_end' => $period['end'], 'due_on' => $period['due'], 'rent' => $period['rent'], 'total' => $this->total($period['rent'], $data), 'created_by' => auth()->id()]);
            $invoice->update(['code' => 'INV-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT)]);
            return $invoice;
        }, 3);
        return to_route('haruhay.billing.show', $invoice)->with('success', 'Draft saved. Complete the charges before issuing this bill.');
    }

    public function update(Request $request, HaruhayBilling $billing)
    {
        $this->authorizeBill($billing, true);
        $data = $this->validated($request);
        DB::transaction(function () use ($billing, $data) {
            $tenant = HaruhayTenant::whereKey($billing->tenant_id)->lockForUpdate()->firstOrFail();
            $bill = HaruhayBilling::whereKey($billing->id)->lockForUpdate()->firstOrFail();
            $this->ensureDraft($bill);
            $this->ensureCurrent($tenant, $bill->contract_id, $bill->period_start->toDateString());
            $bill->update($data + ['total' => $this->total($bill->rent, $data)]);
        }, 3);
        return to_route('haruhay.billing.show', $billing)->with('success', 'Draft charges updated.');
    }

    public function issue(HaruhayBilling $billing)
    {
        $this->authorizeBill($billing, true);
        DB::transaction(function () use ($billing) {
            $tenant = HaruhayTenant::whereKey($billing->tenant_id)->lockForUpdate()->firstOrFail();
            $bill = HaruhayBilling::whereKey($billing->id)->lockForUpdate()->firstOrFail();
            $this->ensureDraft($bill);
            $this->ensureCurrent($tenant, $bill->contract_id, $bill->period_start->toDateString());
            if (!$bill->utilities_ready || $bill->water === null || $bill->electric === null) $this->invalid('utilities_ready', 'Complete water and electric charges and confirm that all charges are ready before issuing.');
            $bill->update(['issued_on' => today(), 'finalized_at' => now(), 'finalized_by' => auth()->id()]);
            app(\App\Services\HaruhayCollectionService::class)->applyAvailableCredit($tenant, $bill->id, $this->divisionId());
            app(\App\Services\HaruhayBillingNotificationService::class)->enqueue($bill);
        }, 3);
        return to_route('haruhay.billing.show', $billing)->with('success', 'Bill issued. Tenant credit applied; email and SMS notifications queued. Check their sending status below.');
    }

    public function show(HaruhayBilling $billing)
    {
        $this->authorizeBill($billing);
        $billing->load(['contract', 'tenant', 'payments', 'creator', 'deliveries', 'dueReminders'])->loadSum('payments', 'amount');
        $currentKey = app(HaruhayBillingWorkflow::class)->currentKey($billing->tenant, $this->divisionId());
        $isCurrent = $currentKey === $billing->contract_id.':'.$billing->period_start->toDateString();
        return view('haruhay.billing.show', compact('billing', 'isCurrent') + ['charges' => self::CHARGES, 'canManage' => $this->canManage()]);
    }

    public function retryNotifications(HaruhayBilling $billing)
    {
        $this->authorizeBill($billing, true);
        abort_unless($billing->finalized_at, 422);
        app(\App\Services\HaruhayBillingNotificationService::class)->retryBlocked($billing);
        return back()->with('success', 'Blocked notifications queued again. Already submitted messages will not be sent again.');
    }

    private function validated(Request $request, bool $creating = false): array
    {
        foreach (array_keys(self::CHARGES) as $field) {
            $value = $request->input($field);
            if (is_string($value) && preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/D', $value)) $request->merge([$field => str_replace(',', '', $value)]);
        }
        $rules = ['remarks' => 'nullable|string|max:5000', 'utilities_ready' => 'sometimes|boolean'];
        if ($creating) $rules += ['contract_id' => 'required|integer', 'period_start' => 'required|date_format:Y-m-d'];
        foreach (array_keys(self::CHARGES) as $field) $rules[$field] = (in_array($field, ['water', 'electric']) ? 'nullable' : 'required').'|numeric|min:0|max:99999999.99|decimal:0,2';
        $data = $request->validateWithBag('billingForm', $rules);
        $data['utilities_ready'] = $request->boolean('utilities_ready');
        foreach (['water', 'electric'] as $field) $data[$field] = $data[$field] ?? null;
        if ($data['utilities_ready'] && ($data['water'] === null || $data['electric'] === null)) $this->invalid('utilities_ready', 'Enter water and electric amounts, or 0 if none applies.');
        return $data;
    }
    private function total($rent, array $data): string
    {
        $cents = (int) round((float) $rent * 100);
        foreach (array_keys(self::CHARGES) as $field) $cents += ($field === 'discount' ? -1 : 1) * (int) round((float) ($data[$field] ?? 0) * 100);
        if ($cents < 0) $this->invalid('discount', 'Discount cannot exceed the total charges.');
        if ($cents > 999999999999) $this->invalid('penalty', 'The invoice total exceeds the supported amount.');
        return number_format($cents / 100, 2, '.', '');
    }
    private function stats(): array
    {
        $issued = $this->visible()->whereNotNull('finalized_at');
        $monthly = (clone $issued)->whereDate('issued_on', '>=', today()->startOfMonth())->whereDate('issued_on', '<=', today()->endOfMonth());
        return ['Issued This Month' => (clone $monthly)->sum('total'), 'Collected This Month' => (\App\Models\HaruhayCollection::active()->whereHas('tenant.unit.property', fn ($q) => $q->where('division_id', $this->divisionId()))->whereDate('paid_on', '>=', today()->startOfMonth())->whereDate('paid_on', '<=', today()->endOfMonth())->sum('amount') + HaruhayBillingPayment::active()->whereNull('collection_id')->whereIn('billing_id', (clone $issued)->select('id'))->whereDate('paid_on', '>=', today()->startOfMonth())->whereDate('paid_on', '<=', today()->endOfMonth())->sum('amount')), 'Outstanding · Issued Bills' => max(0, (clone $issued)->sum('total') - HaruhayBillingPayment::active()->whereIn('billing_id', (clone $issued)->select('id'))->sum('amount')), 'Saved Drafts' => $this->visible()->whereNull('finalized_at')->count()];
    }
    private function ensureCurrent(HaruhayTenant $tenant, int $contractId, string $period): void
    {
        if (app(HaruhayBillingWorkflow::class)->currentKey($tenant, $this->divisionId()) !== $contractId.':'.$period) $this->invalid('billing', 'This month is locked. Fully pay the current bill before preparing the next month.');
    }
    private function ensureDraft(HaruhayBilling $bill): void { if ($bill->finalized_at || $bill->payments()->exists()) $this->invalid('billing', 'This bill is already issued or has payments. Its charges are locked.'); }
    private function divisionId(): int { return (int) Division::whereRaw('LOWER(name) = ?', ['haruhay'])->sole()->id; }
    private function tenants() { return HaruhayTenant::whereHas('unit.property', fn ($q) => $q->where('division_id', $this->divisionId())); }
    private function visible() { return HaruhayBilling::whereHas('unit.property', fn ($q) => $q->where('division_id', $this->divisionId())); }
    private function authorizeBill(HaruhayBilling $billing, bool $manage = false): void { if ($manage) abort_unless($this->canManage(), 403); abort_unless($this->visible()->whereKey($billing->id)->exists(), 404); }
    private function canManage(): bool { return app(\App\Services\HaruhayAccessService::class)->allows(auth()->user(), 'billing.manage'); }
    private function invalid(string $field, string $message): never { throw ValidationException::withMessages([$field => $message])->errorBag('billingForm'); }
}
