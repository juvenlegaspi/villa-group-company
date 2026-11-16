@extends('layouts.app')
@section('title', 'Collections | Haruhay Space')
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-3 sm:px-6 lg:px-8"><div class="mx-auto max-w-7xl">
<header class="mb-4 flex flex-col justify-between gap-3 rounded-2xl bg-gradient-to-br from-emerald-950 via-teal-900 to-emerald-800 px-5 py-4 text-white sm:flex-row sm:items-center"><div><p class="mb-1 text-[.65rem] font-bold uppercase tracking-wider text-amber-300">Haruhay Space / Database Management</p><h1 class="m-0 text-xl font-black sm:text-2xl">Collections</h1><p class="mb-0 mt-1 text-xs text-emerald-100">Received payments, receipts and tenant credit.</p></div><a href="{{ route('haruhay.collections.transactions') }}" class="btn w-fit bg-amber-300 text-xs font-bold text-emerald-950">All Transactions</a></header>
<div class="mb-4 grid grid-cols-2 gap-3 xl:grid-cols-5">@foreach($stats as $label => $value)<article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="mb-1 text-[.65rem] font-bold text-slate-500">{{ $label }}</p><p class="m-0 text-xl font-black text-slate-900">{{ $label === 'Transactions This Month' ? number_format($value) : '₱'.number_format((float) $value, 2) }}</p></article>@endforeach</div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 lg:flex-row lg:items-center lg:justify-between"><div class="min-w-0"><h2 class="mb-1 text-base font-black">Tenant Collection Directory</h2><p class="mb-0 text-xs text-slate-500">{{ $tenants->total() }} tenants · Click a tenant to record payment or review receipts</p></div><form method="GET" class="flex w-full items-center gap-2 lg:ml-auto lg:w-auto lg:shrink-0"><input name="search" value="{{ request('search') }}" maxlength="255" placeholder="Tenant ID, name or room" aria-label="Search tenants" class="form-control min-w-0 flex-1 text-xs lg:w-64 lg:flex-none"><button class="btn shrink-0 bg-teal-800 text-white">Filter</button><a href="{{ route('haruhay.collections.index') }}" class="btn shrink-0 btn-outline-secondary">Reset</a></form></div>
<div class="overflow-x-auto"><table class="w-full min-w-[1300px] text-left text-xs"><thead class="bg-slate-50 text-[.65rem] uppercase tracking-wider text-slate-500"><tr>@foreach(['Tenant ID / Name','Property / Room','Current Bill','Next Billing / Due','Remaining to Pay','Collected This Month','Last Payment','Credit','Bill Status'] as $heading)<th class="px-4 py-3">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">@forelse($tenants as $person)
@php($summary = $summaries[$person->id])
@php($bill = $summary['current']['invoice'] ?? null)
<tr class="hover:bg-teal-50/40"><td class="px-4 py-3"><a href="{{ route('haruhay.collections.tenant', $person) }}" class="font-mono font-bold text-teal-800">{{ $person->code }}</a><a href="{{ route('haruhay.collections.tenant', $person) }}" class="mt-1 block font-bold text-slate-900">{{ $person->name }}</a></td><td class="px-4 py-3 text-slate-600">{{ $person->unit->property->name }}<span class="mt-1 block">{{ $person->unit->unit_no }}</span></td><td class="px-4 py-3">{{ $bill?->code ?? ($summary['current'] ? 'Not yet issued' : '—') }}
@if($bill?->finalized_at)
<span class="mt-1 block text-[.65rem] text-slate-500">Full bill: ₱{{ number_format((float) $bill->total, 2) }}</span>
<span class="mt-1 block text-[.65rem] text-emerald-700">Payments / advance applied: ₱{{ number_format($bill->paid_cents / 100, 2) }}</span>
@endif
</td><td class="px-4 py-3">
@if($summary['next'])
<span class="block font-bold text-teal-800">{{ \Carbon\Carbon::parse($summary['next']['period']['start'])->format('M d, Y') }} – {{ \Carbon\Carbon::parse($summary['next']['period']['end'])->format('M d, Y') }}</span>
<span class="mt-1 block text-slate-500">Due {{ \Carbon\Carbon::parse($summary['next']['period']['due'])->format('M d, Y') }}</span>
<span class="mt-1 block">Expected rent: ₱{{ number_format((float) $summary['next']['period']['rent'], 2) }}</span>
<span class="mt-1 block text-[.65rem] text-amber-700">Rent only · Upcoming estimate, not payable until issued</span>
@else
<span class="text-slate-400">No next scheduled bill</span>
@endif
</td><td class="px-4 py-3 font-bold text-teal-800">{{ $bill?->finalized_at ? '₱'.number_format($bill->balance_cents / 100, 2) : '—' }}</td><td class="px-4 py-3">₱{{ number_format((float) $summary['collected'], 2) }}</td><td class="px-4 py-3">{{ $summary['last']?->paid_on->format('M d, Y') ?? '—' }}</td><td class="px-4 py-3">₱{{ number_format($summary['credit'] / 100, 2) }}</td><td class="px-4 py-3">@if($bill)@include('haruhay.billing.status', ['status' => $bill->payment_status])@if($bill->status === 'Overdue')<span class="mt-1 block text-xs text-rose-700">Overdue</span>@endif
@else
<span class="text-slate-500">{{ $summary['current'] ? 'Awaiting issued bill' : 'No current bill' }}</span>@endif</td></tr>
@empty<tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">No matching tenants.</td></tr>@endforelse</tbody></table></div>@if($tenants->hasPages())<div class="border-t px-4 py-3">{{ $tenants->links() }}</div>@endif</div></div></section>
@endsection
