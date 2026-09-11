@extends('layouts.app')

@section('title', 'Dashboard | Villa Group')

@section('content')
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-white to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <header class="mx-auto mb-6 flex max-w-6xl flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="mb-1 text-xs font-extrabold uppercase tracking-[.14em] text-villa-600">Management workspace</p>
        <h1 class="m-0 text-2xl font-extrabold tracking-tight text-villa-900 sm:text-3xl">Division dashboards</h1>
        <p class="mt-1 text-sm text-slate-500">Select a division to view its dashboard and reports.</p></div>
        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-villa-100 bg-white px-3 py-2 text-xs font-extrabold text-villa-700 shadow-sm"><i class="bi {{ auth()->user()->isExecutiveViewer() ? 'bi-eye' : 'bi-shield-check' }}"></i>{{ auth()->user()->isExecutiveViewer() ? 'Read-only executive view' : 'Administrator view' }}</span>
    </header>

    <div class="mx-auto grid max-w-6xl grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($divisions as $division)
            @php
                $key = strtolower(trim($division->name));
                $isShipping = str_contains($key, 'shipping');
                $isYatira = str_contains($key, 'yatira');
                $isJmv = str_contains($key, 'jmv');
                $isHyve = str_contains($key, 'hyve');
                $isVilla = str_contains($key, 'villa group') || str_contains($key, 'corporate');
                $name = match(true) {
                    $isShipping => 'Villa Shipping Lines, Inc.', $isYatira => 'Yatira Construction, Inc.',
                    $isJmv => 'JMV Mining & Development', $isVilla => 'Villa Group', $isHyve => 'HYVE',
                    default => $division->name,
                };
                $logo = match(true) {
                    $isShipping => 'villa-shipping-lines.webp', $isYatira => 'yatira.webp', $isJmv => 'jmv.webp',
                    $isVilla => 'villa-group.webp', $isHyve => 'hyve.webp', default => 'villa-group.webp',
                };
            @endphp
            <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-villa-500 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-villa-100"
               href="{{ route('division.dashboard', $division->name) }}">
                <img class="h-16 w-16 shrink-0 rounded-xl object-contain" src="{{ asset('images/companies/'.$logo) }}" alt="{{ $name }} logo" width="64" height="64">
                <span class="min-w-0">
                    <span class="block text-sm font-extrabold uppercase leading-snug text-villa-900">{{ $name }}</span>
                    <span class="mt-1 block text-xs font-semibold text-slate-400">View analytics dashboard</span>
                </span>
                <i class="bi bi-chevron-right ml-auto text-slate-400 transition group-hover:translate-x-1 group-hover:text-villa-700"></i>
            </a>
        @endforeach
    </div>
</section>
@endsection
