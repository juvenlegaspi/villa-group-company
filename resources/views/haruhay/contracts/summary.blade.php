@php($linkedContract = $tenant->currentContract ?? $tenant->latestContract)
@if($linkedContract)
    <a href="{{ route('haruhay.contracts.tenant', $tenant) }}#contract-{{ $linkedContract->id }}" class="block whitespace-nowrap text-xs font-bold text-teal-800 no-underline">{{ $linkedContract->code }}</a>
    <span class="mt-1 block text-xs text-slate-500">{{ $linkedContract->status }}</span>
@else
    <span class="whitespace-nowrap rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-400">No Contract Yet</span>
@endif
