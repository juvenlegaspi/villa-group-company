@extends('layouts.app')
@section('title', 'Contract History · '.$tenant->name)
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-6 sm:px-7 lg:px-10"><div class="mx-auto max-w-6xl">
    <header class="mb-4 rounded-2xl bg-gradient-to-br from-emerald-950 via-teal-900 to-emerald-800 px-4 py-3 text-white"><div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><p class="mb-1 text-[.65rem] font-extrabold uppercase tracking-widest text-amber-300">Haruhay Space / Lease Contracts / {{ $tenant->code }}</p><h1 class="m-0 break-words text-xl font-black sm:text-2xl">{{ $tenant->name }}</h1><p class="mb-1 mt-1 text-xs text-emerald-100">{{ $tenant->unit->property->name }} · {{ $tenant->unit->unit_no }}</p>@include('haruhay.tenants.status', ['status' => $tenant->status])</div><div class="flex flex-wrap gap-2"><a href="{{ route('haruhay.contracts.index') }}" class="inline-flex min-h-9 items-center rounded-xl border border-white/30 px-3 text-xs font-bold text-white no-underline">All Tenants</a>@if($canManage && $tenant->status !== 'Cancelled')@if($renewalOf)<a href="{{ route('haruhay.contracts.tenant', $tenant) }}" class="btn bg-amber-300 text-emerald-950">Contract History</a>@elseif($contracts->total() === 0)<button type="button" data-bs-toggle="modal" data-bs-target="#contractModal" class="inline-flex min-h-9 items-center gap-2 rounded-xl border-0 bg-amber-300 px-3 text-xs font-extrabold text-emerald-950"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add Contract</button>@endif @endif</div></div></header>
    @if(session('success'))<div role="status" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->termination->any())<div role="alert" class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->termination->first() }}</div>@endif
    @if($errors->contractForm->any() && $contracts->total() > 0 && !$renewalOf)<div role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->contractForm->first() }}</div>@endif
    <section class="mb-4 rounded-xl border border-teal-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
            <div class="min-w-0"><div class="flex flex-wrap items-center gap-x-3 gap-y-1"><span class="text-[.65rem] font-bold uppercase tracking-wider text-slate-400">Current Contract</span>
            @if($focusContract)
                <h2 class="m-0 text-sm font-black text-teal-900">{{ $focusContract->code }}</h2>
                @include('haruhay.contracts.status', ['contract' => $focusContract])
                </div><p class="mb-0 mt-1 text-xs text-slate-500">Ends {{ $focusContract->ends_on->format('M d, Y') }}@if($focusContract->status === 'Overdue') · <strong class="text-rose-700">Overdue — Needs Renewal</strong>@elseif($focusContract->status === 'Expiring Soon') · {{ $focusContract->days_to_expire }} days remaining · Active, renewal due soon @elseif($focusContract->status === 'Scheduled') · Starts {{ $focusContract->starts_on->format('M d, Y') }}@endif</p>
            @else
                <h2 class="m-0 text-sm font-black text-slate-700">No current contract</h2></div>
            @endif
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500"><span class="font-bold text-slate-700">{{ $contracts->total() }} {{ Str::plural('contract', $contracts->total()) }} in history</span><a href="{{ route('haruhay.tenants.show', $tenant) }}" class="font-bold text-teal-800">View Tenant Details</a></div>
        </div>
    </section>
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3"><h2 class="m-0 text-base font-black text-slate-900">Contract History</h2><p class="mb-0 mt-1 text-xs text-slate-500">Current agreement first, followed by upcoming contracts and previous agreements.</p></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[800px] text-left text-xs">
            <thead class="bg-slate-50 text-[.65rem] uppercase tracking-wider text-slate-500"><tr>@foreach(['Contract No.', 'Lease Period', 'Monthly Rent', 'Due Day', 'Status', 'Actions'] as $heading)<th class="px-4 py-3">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($contracts as $contract)
                <tr id="contract-{{ $contract->id }}" class="scroll-mt-6 {{ $focusContract?->id === $contract->id ? 'bg-teal-50/60' : 'hover:bg-slate-50' }}">
                    <td class="px-4 py-3"><span class="font-extrabold text-teal-900">{{ $contract->code }}</span><span class="mt-1 block text-xs text-slate-400">{{ $contract->previous_contract_id ? 'Renewal of '.$contract->previousContract?->code : 'Original Agreement' }}</span><span class="mt-1 block text-xs text-slate-500">{{ $contract->room_no }}</span></td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $contract->starts_on->format('M d, Y') }}<span class="block text-xs text-slate-400">to {{ $contract->ends_on->format('M d, Y') }}</span></td>
                    <td class="whitespace-nowrap px-4 py-3 font-bold text-slate-800">₱{{ number_format((float) $contract->monthly_rent, 2) }}</td>
                    <td class="px-4 py-3 text-slate-600">Day {{ $contract->due_day }}</td>
                    <td class="px-4 py-3">@include('haruhay.contracts.status')@if($contract->status === 'Overdue')<span class="mt-1 block text-xs text-rose-700">Needs Renewal</span>@endif</td>
                    <td class="whitespace-nowrap px-4 py-3"><button type="button" data-bs-toggle="modal" data-bs-target="#contractView{{ $contract->id }}" class="border-0 bg-transparent p-0 text-sm font-bold text-teal-800">View</button>@if($canManage && $contract->can_renew && $contract->unit_id === $tenant->unit_id && $tenant->status !== 'Cancelled' && $contract->status !== 'Terminated')<a href="{{ route('haruhay.contracts.tenant', ['tenant' => $tenant, 'renew' => $contract->id]) }}" class="ml-3 font-bold text-slate-600 no-underline">Renew</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-14 text-center"><h3 class="text-lg font-black text-slate-800">No contracts recorded</h3><p class="mb-0 text-sm text-slate-500">Add the first agreement to start this tenant's contract history.</p></td></tr>
            @endforelse
            </tbody>
        </table></div>
        @if($contracts->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $contracts->links() }}</div>
        @endif
    </section>
    @foreach($contracts as $contract)
        @include('haruhay.contracts.view-modal')
    @endforeach
    @if($canManage && $tenant->status !== 'Cancelled' && ($contracts->total() === 0 || $renewalOf))
        @include('haruhay.contracts.modal')
    @endif
</div></section>
@endsection
