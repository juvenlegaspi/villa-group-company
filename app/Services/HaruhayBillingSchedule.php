<?php

namespace App\Services;

use App\Models\HaruhayLeaseContract;

class HaruhayBillingSchedule
{
    public function periods(HaruhayLeaseContract $contract): array
    {
        $last = $contract->effective_ends_on;
        $anchor = $contract->billing_coverage_starts_on ?? $contract->starts_on;
        $periods = [];
        for ($i = 0; $i < 1200; $i++) {
            $start = $contract->billing_cycle === 'calendar_month'
                ? ($i === 0 ? $anchor->copy() : $anchor->copy()->startOfMonth()->addMonthsNoOverflow($i))
                : $anchor->copy()->addMonthsNoOverflow($i);
            if ($start->gt($last)) break;
            $end = $contract->billing_cycle === 'calendar_month' ? $start->copy()->endOfMonth()->startOfDay() : $anchor->copy()->addMonthsNoOverflow($i + 1)->subDay();
            if ($end->gt($last)) $end = $last->copy();
            $due = $contract->first_due_on->copy()->startOfMonth()->addMonthsNoOverflow($i);
            $day = $contract->billing_cycle === 'lease_anniversary' ? $contract->billing_starts_on->day : $contract->due_day;
            $due->day(min($day, $due->daysInMonth));
            if ($i === 0) $due = $contract->first_due_on->copy();
            $periods[] = ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'due' => $due->toDateString(), 'rent' => $i === 0 ? $contract->first_month_charge : $contract->monthly_rent];
        }
        return $periods;
    }
}
