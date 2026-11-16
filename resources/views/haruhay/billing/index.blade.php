@extends('layouts.app')
@section('title', 'Billing | Haruhay Space')
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-3 sm:px-6 lg:px-8"><div class="mx-auto max-w-7xl">
<header class="mb-4 rounded-2xl bg-gradient-to-br from-emerald-950 via-teal-900 to-emerald-800 px-5 py-4 text-white"><p class="mb-1 text-[.65rem] font-bold uppercase tracking-wider text-amber-300">Haruhay Space / Database Management</p><h1 class="m-0 text-xl font-black sm:text-2xl">Billing</h1><p class="mb-0 mt-1 text-xs text-emerald-100">Select a tenant to view monthly bills and complete pending charges.</p></header>
<div class="mb-4 grid grid-cols-2 gap-3 xl:grid-cols-4">@foreach($stats as $label => $value)<article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="mb-1 text-[.65rem] font-bold text-slate-500">{{ $label }}</p><p class="m-0 text-xl font-black text-slate-900">{{ $loop->last ? number_format($value) : '₱'.number_format((float) $value, 2) }}</p></article>@endforeach</div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-4 py-3"><h2 class="mb-1 text-base font-black">Tenant Billing Directory</h2><p class="mb-3 text-xs text-slate-500">{{ $tenants->total() }} tenants · Balances include issued bills only</p><form method="GET" class="flex flex-wrap items-center gap-2"><input name="search" value="{{ request('search') }}" maxlength="255" placeholder="Tenant ID, name or room" aria-label="Search tenants" class="form-control w-full text-xs sm:w-80"><button class="btn bg-teal-800 text-white">Filter</button><a href="{{ route('haruhay.billing.index') }}" class="btn btn-outline-secondary">Reset</a></form></div>
<div class="overflow-x-auto"><table class="w-full min-w-[1100px] text-left text-xs"><thead class="bg-slate-50 text-[.65rem] uppercase tracking-wider text-slate-500"><tr>@foreach(['Tenant ID','Tenant','Property / Room','Tenant Status','Saved Drafts','Next Billing / Due','Total Balance','Next Unpaid Due'] as $heading)<th class="px-4 py-3">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">@forelse($tenants as $person)
@php($unpaid = $person->billings->filter(fn ($bill) => $bill->balance_cents > 0)->sortBy('due_on'))
<tr class="hover:bg-teal-50/40"><td class="px-4 py-3"><a href="{{ route('haruhay.billing.tenant', $person) }}" class="font-mono font-bold text-teal-800">{{ $person->code }}</a></td><td class="px-4 py-3"><a href="{{ route('haruhay.billing.tenant', $person) }}" class="font-bold text-slate-900">{{ $person->name }}</a></td><td class="px-4 py-3 text-slate-600">{{ $person->unit->property->name }}<span class="mt-1 block text-teal-800">{{ $person->unit->unit_no }}</span></td><td class="px-4 py-3">@include('haruhay.tenants.status', ['status' => $person->status])</td><td class="px-4 py-3">{{ $person->draft_count }}</td>
<td class="px-4 py-3">
@if($nextBills[$person->id])
@php($nextBill = $nextBills[$person->id])
<span class="block font-bold text-teal-800">{{ \Carbon\Carbon::parse($nextBill['period']['start'])->format('M d, Y') }} – {{ \Carbon\Carbon::parse($nextBill['period']['end'])->format('M d, Y') }}</span>
<span class="mt-1 block text-slate-500">Due {{ \Carbon\Carbon::parse($nextBill['period']['due'])->format('M d, Y') }}</span>
<span class="mt-1 block">Expected rent: ₱{{ number_format((float) $nextBill['period']['rent'], 2) }}</span>
<span class="mt-1 block text-[.65rem] text-amber-700">Rent only · Utilities and other charges not included</span>
@else
<span class="text-slate-400">No next scheduled bill</span>
@endif
</td><td class="px-4 py-3 font-bold text-teal-800">₱{{ number_format($unpaid->sum('balance_cents') / 100, 2) }}</td><td class="px-4 py-3">{{ $unpaid->first()?->due_on->format('M d, Y') ?? '—' }}</td></tr>
@empty<tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">No matching tenants.</td></tr>@endforelse</tbody></table></div>@if($tenants->hasPages())<div class="border-t px-4 py-3">{{ $tenants->links() }}</div>@endif</div>
</div></section>
@endsection
