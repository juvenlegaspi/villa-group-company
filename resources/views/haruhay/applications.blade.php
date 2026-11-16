@extends('layouts.app')

@section('title', 'Applications | Haruhay Dormitory')

@section('content')
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-white to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <header class="mx-auto mb-6 flex max-w-6xl items-start justify-between gap-5">
        <div class="flex min-w-0 items-center gap-4">
            <img src="{{ asset('images/companies/haruhay-space-white.png') }}" alt="Haruhay Space logo" width="80" height="80" class="h-16 w-16 shrink-0 rounded-2xl bg-white p-1 object-contain sm:h-20 sm:w-20">
            <div>
                <p class="mb-1 text-xs font-extrabold uppercase tracking-[.14em] text-villa-600">Haruhay Dormitory</p>
                <h1 class="m-0 text-2xl font-extrabold tracking-tight text-villa-900 sm:text-3xl">Applications</h1>
                <p class="mt-1 text-sm text-slate-500">Room rentals, tenants and property management.</p>
            </div>
        </div>
        <a class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600 no-underline shadow-sm hover:border-villa-500 hover:text-villa-800"
           href="{{ route('companies') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="hidden sm:inline">Back to companies</span></a>
    </header>

    @php
        $applications = [
            ['Maintenance', 'Room concerns, repair requests and work progress.', 'tools', 'bg-orange-50 text-orange-700'],
        ];
    @endphp
    <div class="mx-auto grid max-w-6xl grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        <a href="{{ route('haruhay.reservations.index') }}" class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm hover:border-emerald-600"><span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-2xl text-emerald-700"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span><span><span class="block text-sm font-extrabold uppercase text-villa-900">Online Reservations</span><span class="mt-1 block text-xs leading-5 text-slate-500">Monthly requests, 24-hour holds and advance payment verification.</span></span></a>
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-villa-500 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-villa-100" href="{{ route('haruhay.database') }}">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-blue-50 text-2xl text-blue-700"><i class="bi bi-buildings" aria-hidden="true"></i></span>
            <span><span class="block text-sm font-extrabold uppercase text-villa-900">Database Management</span><span class="mt-1 block text-xs leading-5 text-slate-500">Tenants, units, lease contracts, billing, collections and reports.</span></span>
        </a>
        @foreach($applications as [$name, $description, $icon, $color])
            <div role="group" aria-label="{{ $name }} — coming soon" class="flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 shadow-sm">
                <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl text-2xl {{ $color }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                <div>
                    <h2 class="m-0 text-sm font-extrabold uppercase text-villa-900">{{ $name }}</h2>
                    <p class="mb-0 mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                    <span class="mt-1 block text-[.65rem] font-extrabold uppercase tracking-wider text-amber-700">Coming soon</span>
                </div>
            </div>
        @endforeach
        @if(auth()->user()->isSystemAdministrator())
        <a href="{{ route('haruhay.access.index') }}" class="flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm font-extrabold text-villa-900 shadow-sm">Haruhay Staff Access · Positions &amp; Permissions</a>
        @endif
        @if(auth()->user()->canManageUsers())
            <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-violet-400 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-violet-100" href="{{ route('users.index') }}">
                <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-violet-50 text-2xl text-violet-700"><i class="bi bi-people" aria-hidden="true"></i></span>
                <span><span class="block text-sm font-extrabold uppercase text-villa-900">User Management</span><span class="mt-1 block text-xs leading-5 text-slate-500">Manage company departments, positions and employee access.</span></span>
            </a>
        @endif
    </div>
</section>
@endsection
