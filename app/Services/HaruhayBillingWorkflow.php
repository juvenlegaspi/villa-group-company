<?php

namespace App\Services;

use App\Models\HaruhayBilling;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayTenant;
use Illuminate\Support\Collection;

class HaruhayBillingWorkflow
{
    public function rows(HaruhayTenant $tenant, int $divisionId, ?Collection $saved = null, ?Collection $contracts = null): Collection
    {
        $saved ??= $tenant->billings()->with('contract.amendment')->withSum('payments', 'amount')->get();
        $contracts ??= $tenant->contracts()->whereHas('unit.property', fn ($q) => $q->where('division_id', $divisionId))->with('amendment')->orderBy('starts_on')->get();
        $rows = collect();
        foreach ($contracts as $contract) {
            foreach (app(HaruhayBillingSchedule::class)->periods($contract) as $period) {
                $invoice = $saved->first(fn ($b) => (int) $b->contract_id === $contract->id && $b->period_start->toDateString() === $period['start']);
                if (! $invoice && $tenant->status === 'Cancelled') {
                    continue;
                }
                $rows->push(['contract' => $contract, 'period' => $period, 'invoice' => $invoice]);
            }
        }
        foreach ($saved as $invoice) {
            if (! $rows->contains(fn ($r) => $r['invoice']?->id === $invoice->id)) {
                $rows->push(['contract' => $invoice->contract, 'period' => [], 'invoice' => $invoice]);
            }
        }

        return $rows->map(function ($row) {
            $invoice = $row['invoice'];
            if ($invoice) {
                $row['period'] = ['start' => $invoice->period_start->toDateString(), 'end' => $invoice->period_end->toDateString(), 'due' => $invoice->due_on->toDateString(), 'rent' => $invoice->rent];
            }
            $row['key'] = $row['contract']->id.':'.$row['period']['start'];
            $row['paid'] = $invoice && $invoice->finalized_at && $invoice->balance_cents === 0;

            return $row;
        })->sortBy(fn ($row) => [$row['period']['start'], $row['contract']->id])->values();
    }

    /**
     * Fetch billing schedules for the current directory page in batches.
     */
    public function forTenants(Collection $tenants, int $divisionId): Collection
    {
        if ($tenants->isEmpty()) {
            return collect();
        }
        $ids = $tenants->pluck('id');
        $saved = HaruhayBilling::whereIn('tenant_id', $ids)->with('contract.amendment')->withSum('payments', 'amount')->get()->groupBy('tenant_id');
        $contracts = HaruhayLeaseContract::whereIn('tenant_id', $ids)
            ->whereHas('unit.property', fn ($query) => $query->where('division_id', $divisionId))
            ->with('amendment')->orderBy('starts_on')->get()->groupBy('tenant_id');

        return $tenants->mapWithKeys(fn (HaruhayTenant $tenant) => [$tenant->id => $this->rows($tenant, $divisionId, $saved->get($tenant->id, collect()), $contracts->get($tenant->id, collect()))]);
    }

    public function currentKey(HaruhayTenant $tenant, int $divisionId): ?string
    {
        return $this->rows($tenant, $divisionId)->first(fn ($row) => ! $row['paid'])['key'] ?? null;
    }
}
