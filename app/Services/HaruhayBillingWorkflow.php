<?php

namespace App\Services;

use App\Models\HaruhayTenant;
use Illuminate\Support\Collection;

class HaruhayBillingWorkflow
{
    public function rows(HaruhayTenant $tenant, int $divisionId): Collection
    {
        $saved = $tenant->billings()->with('contract')->withSum('payments', 'amount')->get();
        $contracts = $tenant->contracts()->whereHas('unit.property', fn ($q) => $q->where('division_id', $divisionId))->orderBy('starts_on')->get();
        $rows = collect();
        foreach ($contracts as $contract) {
            foreach (app(HaruhayBillingSchedule::class)->periods($contract) as $period) {
                $invoice = $saved->first(fn ($b) => (int) $b->contract_id === $contract->id && $b->period_start->toDateString() === $period['start']);
                if (!$invoice && $tenant->status === 'Cancelled') continue;
                $rows->push(['contract' => $contract, 'period' => $period, 'invoice' => $invoice]);
            }
        }
        foreach ($saved as $invoice) {
            if (!$rows->contains(fn ($r) => $r['invoice']?->id === $invoice->id)) $rows->push(['contract' => $invoice->contract, 'period' => [], 'invoice' => $invoice]);
        }
        return $rows->map(function ($row) {
            $invoice = $row['invoice'];
            if ($invoice) $row['period'] = ['start' => $invoice->period_start->toDateString(), 'end' => $invoice->period_end->toDateString(), 'due' => $invoice->due_on->toDateString(), 'rent' => $invoice->rent];
            $row['key'] = $row['contract']->id.':'.$row['period']['start'];
            $row['paid'] = $invoice && $invoice->finalized_at && $invoice->balance_cents === 0;
            return $row;
        })->sortBy(fn ($row) => [$row['period']['start'], $row['contract']->id])->values();
    }

    public function currentKey(HaruhayTenant $tenant, int $divisionId): ?string
    {
        return $this->rows($tenant, $divisionId)->first(fn ($row) => !$row['paid'])['key'] ?? null;
    }
}
