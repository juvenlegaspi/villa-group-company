<?php

namespace App\Services;

use App\Models\{HaruhayBilling, HaruhayBillingPayment, HaruhayCollection, HaruhayLeaseContract, HaruhayProperty, HaruhayTenant, HaruhayUnit};
use Carbon\Carbon;

class HaruhayReportService
{
    public function build(int $divisionId, array $filters): array
    {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->startOfDay();
        $asOf = $to->lt(today()) ? $to->copy() : today();
        $property = $filters['property_id'] ?? null;
        $tenant = $filters['tenant_id'] ?? null;
        $scope = fn ($q) => $q->where('division_id', $divisionId)->when($property, fn ($q) => $q->whereKey($property));
        $billQuery = HaruhayBilling::whereHas('unit.property', $scope)->when($tenant, fn ($q) => $q->where('tenant_id', $tenant))->whereNotNull('finalized_at');
        $bills = (clone $billQuery)->whereDate('issued_on', '<=', $asOf)->with(['tenant', 'paymentHistory' => fn ($q) => $q->whereDate('paid_on', '<=', $asOf)->whereDate('created_at', '<=', $asOf)->where(fn ($v) => $v->whereNull('voided_at')->orWhereDate('voided_at', '>', $asOf))])->get();
        $balance = fn ($b) => max(0, $this->cents($b->total) - $b->paymentHistory->sum(fn ($p) => $this->cents($p->amount)));
        $cohort = $bills->filter(fn ($b) => $b->issued_on->betweenIncluded($from, $to));
        $receiptsQuery = HaruhayCollection::whereHas('property', $scope)->whereHas('tenant.unit.property', fn ($q) => $q->where('division_id', $divisionId))->when($tenant, fn ($q) => $q->where('tenant_id', $tenant));
        $receipts = (clone $receiptsQuery)->where(fn ($q) => $q->whereNull('voided_at')->orWhereDate('voided_at', '>', $asOf))->whereDate('paid_on', '>=', $from)->whereDate('paid_on', '<=', $to)->with('receiver')->get();
        // Older allocations without a receipt remain visible, counted once.
        $legacy = HaruhayBillingPayment::where(fn ($q) => $q->whereNull('voided_at')->orWhereDate('voided_at', '>', $asOf))->whereNull('collection_id')->whereHas('billing', fn ($q) => $q->whereIn('id', (clone $billQuery)->select('id')))->whereDate('paid_on', '>=', $from)->whereDate('paid_on', '<=', $to)->get();
        $billingTotal = $cohort->sum(fn ($b) => $this->cents($b->total));
        $receivedTotal = $receipts->sum(fn ($r) => $this->cents($r->amount)) + $legacy->sum(fn ($p) => $this->cents($p->amount));
        $outstanding = $bills->sum($balance);
        $applied = $cohort->sum(fn ($b) => min($this->cents($b->total), $b->paymentHistory->sum(fn ($p) => $this->cents($p->amount))));
        $rate = $billingTotal > 0 ? round($applied / $billingTotal * 100, 2) : 0;

        $trend = [];
        $cursor = $filters['group_by'] === 'day' ? $from->copy() : $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $key = $cursor->format($filters['group_by'] === 'day' ? 'Y-m-d' : 'Y-m');
            $trend[$key] = ['period' => $cursor->format($filters['group_by'] === 'day' ? 'M d, Y' : 'M Y'), 'billing' => 0, 'collections' => 0, 'applied' => 0, 'rate' => 0];
            $filters['group_by'] === 'day' ? $cursor->addDay() : $cursor->addMonth();
        }
        foreach ($cohort as $bill) {
            $key = $bill->issued_on->format($filters['group_by'] === 'day' ? 'Y-m-d' : 'Y-m');
            $trend[$key]['billing'] += $this->cents($bill->total);
            $trend[$key]['applied'] += min($this->cents($bill->total), $bill->paymentHistory->sum(fn ($p) => $this->cents($p->amount)));
        }
        foreach ($receipts->concat($legacy) as $receipt) {
            $key = $receipt->paid_on->format($filters['group_by'] === 'day' ? 'Y-m-d' : 'Y-m');
            $trend[$key]['collections'] += $this->cents($receipt->amount);
        }
        foreach ($trend as &$point) $point['rate'] = $point['billing'] > 0 ? round($point['applied'] / $point['billing'] * 100, 2) : 0;
        unset($point);

        $aging = ['Current' => 0, '1–30 Days' => 0, '31–60 Days' => 0, '61–90 Days' => 0, 'Over 90 Days' => 0];
        $accounts = [];
        foreach ($bills as $bill) {
            $remaining = $balance($bill);
            if (!$remaining) continue;
            $days = max(0, (int) $bill->due_on->diffInDays($asOf, false));
            $bucket = $days === 0 ? 'Current' : ($days <= 30 ? '1–30 Days' : ($days <= 60 ? '31–60 Days' : ($days <= 90 ? '61–90 Days' : 'Over 90 Days')));
            $aging[$bucket] += $remaining;
            $key = $bill->tenant_id;
            $accounts[$key] ??= ['tenant_id' => $bill->tenant_id, 'tenant' => $bill->tenant_name, 'property' => $bill->property_name, 'balance' => 0, 'days' => 0];
            $accounts[$key]['balance'] += $remaining;
            $accounts[$key]['days'] = max($accounts[$key]['days'], $days);
        }
        $accounts = collect($accounts)->sortByDesc('balance')->values();

        $units = HaruhayUnit::whereHas('property', $scope)->when($tenant, fn ($q) => $q->whereHas('tenants', fn ($t) => $t->whereKey($tenant)))->with('property')->get();
        $unknownUnits=0; $historicalEvents=null;
        $historySince=\App\Models\HaruhayOccupancyEvent::min('recorded_at');
        if(!empty($filters['occupancy_on'])) {
            $on=Carbon::parse($filters['occupancy_on'])->endOfDay();
            $allEvents=\App\Models\HaruhayOccupancyEvent::where('recorded_at','<=',$on)->orderByDesc('recorded_at')->orderByDesc('id')->get()->unique('unit_id');
            $historicalEvents=$allEvents->filter(fn($e)=>(int)$e->property->division_id===$divisionId && (!$property || (int)$e->property_id===(int)$property) && (!$tenant || in_array((int)$tenant,[...$e->occupied_tenant_ids,...$e->reserved_tenant_ids],true)));
            $unknownUnits=$units->filter(fn($u)=>$u->created_at->lte($on) && !$allEvents->contains('unit_id',$u->id))->count();
            $units=$historicalEvents->map(function($e){$unit=new HaruhayUnit;$unit->setRawAttributes(['id'=>$e->unit_id,'property_id'=>$e->property_id,'status'=>$e->status,'room_condition'=>$e->room_condition]);$unit->setRelation('property',new HaruhayProperty(['name'=>$e->property_name]));return $unit;})->values();
        }
        $unitStatus = ['Occupied' => $units->where('status', 'Occupied')->count(), 'Vacant' => $units->where('status', 'Vacant')->count(), 'Reserved' => $units->where('status', 'Reserved')->count()];
        $maintenance = $units->where('room_condition', 'Under Maintenance')->count();
        $occupancyRate = $units->count() ? round($unitStatus['Occupied'] / $units->count() * 100, 2) : 0;
        $occupancy = $units->groupBy('property_id')->map(function ($rooms) {
            $occupied = $rooms->where('status', 'Occupied')->count();
            return ['property' => $rooms->first()->property->name, 'total' => $rooms->count(), 'occupied' => $occupied, 'vacant' => $rooms->where('status', 'Vacant')->count(), 'reserved' => $rooms->where('status', 'Reserved')->count(), 'maintenance' => $rooms->where('room_condition', 'Under Maintenance')->count(), 'rate' => round($occupied / $rooms->count() * 100, 2)];
        })->values();
        $activeTenants = HaruhayTenant::whereHas('unit.property', $scope)->when($tenant, fn ($q) => $q->whereKey($tenant))->whereIn('status', HaruhayTenant::LIVE_STATUSES)->count();
        if($historicalEvents!==null)$activeTenants=$historicalEvents->flatMap(fn($e)=>$e->occupied_tenant_ids)->unique()->count();
        $leasesQuery = HaruhayLeaseContract::whereHas('unit.property', $scope)->when($tenant, fn ($q) => $q->where('tenant_id', $tenant))->with('renewal');
        $leaseRow = fn ($c) => ['tenant' => $c->tenant_name, 'property' => $c->property_name, 'room' => $c->room_no, 'contract' => $c->code, 'expiry' => $c->ends_on->toDateString(), 'status' => $c->status];
        $upcoming = (clone $leasesQuery)->whereNull('terminated_on')->whereDate('ends_on', '>=', today())->whereDate('ends_on', '<=', today()->addDays(30))->whereDoesntHave('renewal', fn ($q) => $q->whereNull('terminated_on'))->whereDoesntHave('amendment', fn ($q) => $q->whereDate('starts_on','<=',today()))->orderBy('ends_on')->get()->map($leaseRow);
        $leases = (clone $leasesQuery)->whereDate('ends_on', '>=', $from)->whereDate('ends_on', '<=', $to)->orderBy('ends_on')->get()->map($leaseRow);

        $incomeRows = $cohort->sortBy('issued_on')->map(fn ($b) => [$b->code, $b->issued_on->toDateString(), $b->tenant_name, $b->property_name, $b->room_no, $this->money($this->cents($b->total)), $this->money($b->paymentHistory->sum(fn ($p) => $this->cents($p->amount))), $this->money($balance($b))])->values()->all();
        $collectionRows = $receipts->sortBy('paid_on')->map(fn ($r) => [$r->code, $r->paid_on->toDateString(), $r->tenant_name, $r->property_name, $r->method, $r->external_reference ?? '', $this->money($this->cents($r->amount)), $r->receiver?->name ?? 'System'])->values()->all();
        foreach ($legacy as $payment) $collectionRows[] = [$payment->reference, $payment->paid_on->toDateString(), 'Legacy payment', '', 'Legacy', '', $this->money($this->cents($payment->amount)), ''];
        $ledger = collect();
        foreach ($cohort as $b) $ledger->push(['date' => $b->issued_on->toDateString(), 'tenant' => $b->tenant_name, 'ref' => $b->code, 'type' => 'Issued Bill', 'debit' => $this->money($this->cents($b->total)), 'credit' => '0.00']);
        foreach ($receipts as $r) $ledger->push(['date' => $r->paid_on->toDateString(), 'tenant' => $r->tenant_name, 'ref' => $r->code, 'type' => $r->receipt_type, 'debit' => '0.00', 'credit' => $this->money($this->cents($r->amount))]);
        foreach ($legacy as $p) $ledger->push(['date' => $p->paid_on->toDateString(), 'tenant' => 'Legacy payment', 'ref' => $p->reference, 'type' => 'Legacy Payment', 'debit' => '0.00', 'credit' => $this->money($this->cents($p->amount))]);
        $tables = [
            'overview' => ['headers' => ['Period', 'Billing (PHP)', 'Collections (PHP)', 'Collection Rate (%)'], 'rows' => array_map(fn ($p) => [$p['period'], $this->money($p['billing']), $this->money($p['collections']), $p['rate']], array_values($trend))],
            'income' => ['headers' => ['Invoice', 'Issued', 'Tenant', 'Property', 'Room', 'Billed (PHP)', 'Applied by End Date (PHP)', 'Balance (PHP)'], 'rows' => $incomeRows],
            'collections' => ['headers' => ['Receipt', 'Received', 'Tenant', 'Property', 'Method', 'Reference', 'Received (PHP)', 'Received By'], 'rows' => $collectionRows],
            'outstanding' => ['headers' => ['Tenant', 'Property', 'Balance (PHP)', 'Days Overdue'], 'rows' => $accounts->map(fn ($r) => [$r['tenant'], $r['property'], $this->money($r['balance']), $r['days']])->all()],
            'ledger' => ['headers' => ['Date', 'Tenant', 'Reference', 'Entry', 'Billed (PHP)', 'Received (PHP)'], 'rows' => $ledger->sortBy('date')->map(fn ($r) => array_values($r))->values()->all()],
            'occupancy' => ['headers' => ['Property', 'Total Rooms', 'Occupied', 'Vacant', 'Reserved', 'Under Maintenance', 'Occupancy (%)'], 'rows' => $occupancy->map(fn ($r) => array_values($r))->all()],
            'leases' => ['headers' => ['Tenant', 'Property', 'Room', 'Contract', 'Expiry', 'Status Today'], 'rows' => $leases->map(fn ($r) => array_values($r))->all()],
        ];
        $depositEntries=\App\Models\HaruhayDepositEntry::whereHas('tenant.unit.property',fn($q)=>$q->where('division_id',$divisionId))->when($property,fn($q)=>$q->where('property_id',$property))->when($tenant,fn($q)=>$q->where('tenant_id',$tenant))->whereDate('occurred_on','>=',$from)->whereDate('occurred_on','<=',$to)->where(fn($q)=>$q->whereNull('voided_at')->orWhereDate('voided_at','>',$asOf))->orderBy('occurred_on')->get();
        $tables['deposits']=['headers'=>['Entry','Date','Tenant','Property','Type','Amount (PHP)','Reason'],'rows'=>$depositEntries->map(fn($e)=>[$e->code,$e->occurred_on->toDateString(),$e->tenant_name,$e->property_name,$e->type,$this->money($this->cents($e->amount)),$e->reason])->all()];
        return compact('billingTotal', 'receivedTotal', 'outstanding', 'rate', 'trend', 'aging', 'unitStatus', 'maintenance', 'occupancyRate', 'activeTenants', 'accounts', 'upcoming', 'occupancy', 'tables','unknownUnits','historySince') + ['totalUnits' => $units->count(), 'asOf' => $asOf->toDateString()];
    }

    private function cents($value): int { return (int) round((float) $value * 100); }
    private function money(int $cents): string { return number_format($cents / 100, 2, '.', ''); }
}
