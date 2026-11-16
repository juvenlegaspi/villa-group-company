@extends('layouts.app')

@php
    $sections = [
        'tenants' => ['Tenants', 'Tenant records, room assignments and move-in / move-out dates.'],
        'units' => ['Units', 'Room details, rental rates and occupancy status.'],
        'lease-contracts' => ['Lease Contracts', 'Lease agreements, deposits, advances and renewals.'],
        'billing' => ['Billing', 'Monthly rent, utilities, additional charges and balances.'],
        'collections' => ['Collections', 'Rental payments, receipts and collection records.'],
        'reports' => ['Reports', 'Occupancy, billing, collections and property summaries.'],
    ];
    [$name, $description] = $sections[$section ?? 'tenants'];
@endphp

@section('title', $name.' | Haruhay Dormitory')

@section('content')
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-white to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <header class="mx-auto mb-6 flex max-w-6xl items-start justify-between gap-5">
        <div>
            <p class="mb-1 text-xs font-extrabold uppercase tracking-widest text-villa-600">Haruhay / Database Management</p>
            <h1 class="m-0 text-2xl font-extrabold tracking-tight text-villa-900 sm:text-3xl">{{ $name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
        </div>
        <a class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600 no-underline shadow-sm hover:border-villa-500 hover:text-villa-800" href="{{ route('haruhay.applications') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="hidden sm:inline">Back to applications</span></a>
    </header>
    <div class="mx-auto max-w-6xl rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
        <h2 class="text-lg font-extrabold text-slate-800">{{ $name }} coming soon</h2>
        <p class="mb-0 mt-1 text-sm text-slate-500">This module is being prepared. Use the sidebar to switch between sections.</p>
    </div>
</section>
@endsection
