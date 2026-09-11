@extends('layouts.app')

@section('title', $supplier->name.' | Supplier')

@section('content')
<main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:py-8">
    <a href="{{ route('suppliers.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-bold text-villa-700 no-underline hover:text-villa-900"><i class="bi bi-arrow-left"></i> Back to suppliers</a>
    <header class="mb-5 rounded-3xl bg-gradient-to-r from-villa-900 to-villa-700 px-6 py-6 text-white shadow-xl sm:px-8">
        <p class="mb-1 text-xs font-extrabold uppercase tracking-[.18em] text-amber-200">Supplier profile</p>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h1 class="mb-1 text-2xl font-black sm:text-3xl">{{ $supplier->name }}</h1><p class="mb-0 text-sm text-blue-100">TIN {{ $supplier->tin ?: 'Requires review' }} &middot; {{ $supplier->business_type }}</p></div><span class="w-fit rounded-full px-3 py-1.5 text-xs font-black {{ $supplier->status ? 'bg-emerald-400/20 text-emerald-100 ring-1 ring-emerald-300/40' : 'bg-slate-400/20 text-slate-200 ring-1 ring-slate-300/30' }}">{{ $supplier->status ? 'Active' : 'Inactive' }}</span></div>
    </header>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="mb-5 text-lg font-black text-slate-900">Supplier information</h2><dl class="grid gap-5 sm:grid-cols-2">
            @foreach([['Products', $supplier->products], ['Address', $supplier->address], ['Contact person', $supplier->contact_person], ['Email', $supplier->email], ['Telephone', $supplier->telephone], ['Mobile', $supplier->mobile], ['Tax type', $supplier->tax_type], ['Lead time', is_null($supplier->lead_time) ? null : $supplier->lead_time.' days'], ['Credit term', is_null($supplier->credit_term) ? null : $supplier->credit_term.' days'], ['Advance limit', is_null($supplier->limit_advances) ? null : number_format($supplier->limit_advances, 2)]] as [$label,$value])
                <div><dt class="text-xs font-extrabold uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="mb-0 mt-1 break-words text-sm font-bold text-slate-800">{{ filled($value) ? $value : 'Not provided' }}</dd></div>
            @endforeach
        </dl><div class="mt-6 border-t border-slate-100 pt-4 text-xs text-slate-500">Created by {{ $supplier->user?->name ?? 'System' }} on {{ optional($supplier->created_at)->format('M d, Y h:i A') }} &middot; Last updated by {{ $supplier->updater?->name ?? $supplier->user?->name ?? 'System' }}</div></section>

        <details open class="h-fit rounded-3xl border border-slate-200 bg-white shadow-sm"><summary class="cursor-pointer list-none px-5 py-4 text-sm font-black text-slate-900">Audit trail <span class="float-right rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ $supplier->audits->count() }}</span></summary><div class="max-h-[520px] space-y-4 overflow-y-auto border-t border-slate-100 p-5">@forelse($supplier->audits as $audit)<article class="border-l-2 border-amber-300 pl-3"><p class="mb-0 text-xs font-black capitalize text-slate-800">{{ str_replace('_', ' ', $audit->action) }}</p><p class="mb-0 mt-1 text-[11px] text-slate-500">{{ $audit->user?->name ?? 'System' }} &middot; {{ optional($audit->created_at)->format('M d, Y h:i A') }}</p>@if($audit->changes)<details class="mt-2"><summary class="cursor-pointer text-[11px] font-bold text-villa-700">View recorded changes</summary><pre class="mt-2 max-w-full overflow-x-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-2 text-[10px] text-slate-600">{{ json_encode($audit->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>@endif</article>@empty<p class="mb-0 text-sm text-slate-500">No audit activity recorded.</p>@endforelse</div></details>
    </div>
</main>
@endsection
