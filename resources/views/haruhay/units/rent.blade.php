@if($unit->monthly_rent !== null)
    <span class="whitespace-nowrap font-bold text-slate-800">₱{{ number_format((float) $unit->monthly_rent, 2) }}</span>
    <span class="mt-1 block text-xs text-slate-500">{{ $unit->rent_basis === 'occupancy_based' ? 'Single occupancy / month' : ($unit->rent_basis === 'per_person' ? 'Per person / month' : 'Per room / month') }}</span>
    @if($unit->rent_basis === 'occupancy_based')<span class="mt-1 block text-xs font-semibold text-teal-800">₱{{ number_format((float) $unit->shared_rate, 2) }} / person when shared</span>@endif
@else
    <span class="text-xs font-semibold text-slate-400">Rate not set</span>
@endif
