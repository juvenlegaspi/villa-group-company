@extends('layouts.app')

@section('title', 'Applications | JMV Mining & Development')

@section('content')
<section class="relative isolate min-h-[calc(100svh-74px)] overflow-hidden bg-gradient-to-br from-white via-amber-50/30 to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <div class="pointer-events-none absolute -right-36 -top-44 -z-10 h-96 w-96 rounded-full border-[64px] border-amber-500/[.06]"></div>

    <header class="mx-auto mb-7 max-w-6xl overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-villa-900 to-amber-700 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <span class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-2xl bg-white p-2 shadow-lg sm:h-20 sm:w-20">
                    <img class="h-full w-full object-contain" src="{{ asset('images/companies/jmv.webp') }}" alt="JMV Mining & Development logo" width="80" height="80">
                </span>
                <div>
                    <p class="mb-1 text-xs font-extrabold uppercase tracking-[.18em] text-amber-200">JMV Mining &amp; Development</p>
                    <h1 class="m-0 text-2xl font-black tracking-tight sm:text-3xl">Company Applications</h1>
                    <p class="mt-1 max-w-2xl text-sm text-amber-50/80">Choose the inventory workspace you want to open.</p>
                </div>
            </div>
            <a class="inline-flex min-h-11 w-fit shrink-0 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 text-sm font-bold text-white no-underline transition hover:bg-white/20 focus:outline-none focus:ring-4 focus:ring-white/20" href="{{ route('companies') }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i><span>Companies</span>
            </a>
        </div>
    </header>

    @php
        $canViewInventory = auth()->user()->hasPermission('jmv.inventory.view');
        $isExecutiveViewer = auth()->user()->isExecutiveViewer();
        $canMoveStock = $isExecutiveViewer || auth()->user()->hasPermission('jmv.inventory.movements.manage');
        $canViewReports = auth()->user()->hasPermission('jmv.inventory.reports.view');
    @endphp
    <div class="mx-auto grid max-w-6xl grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @if($canViewInventory)
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-amber-400 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-amber-100" href="{{ route('jmv.inventory.index') }}">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-amber-50 text-2xl text-amber-700"><i class="bi bi-box-seam"></i></span>
            <span><span class="block text-sm font-extrabold uppercase text-villa-900">Inventory</span><span class="mt-1 block text-xs leading-5 text-slate-500">View item balances, minimum levels, maximum levels and stock health.</span></span>
        </a>
        @endif

        @if($canMoveStock)
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-emerald-400 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-emerald-100" href="{{ route('jmv.stockin.index') }}">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-2xl text-emerald-700"><i class="bi bi-box-arrow-in-down"></i></span>
            <span><span class="block text-sm font-extrabold uppercase text-villa-900">Stock In</span><span class="mt-1 block text-xs leading-5 text-slate-500">Record received materials and review incoming inventory transactions.</span></span>
        </a>

        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-rose-400 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-rose-100" href="{{ route('jmv.stockout.index') }}">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-rose-50 text-2xl text-rose-700"><i class="bi bi-box-arrow-up"></i></span>
            <span><span class="block text-sm font-extrabold uppercase text-villa-900">Stock Out</span><span class="mt-1 block text-xs leading-5 text-slate-500">Issue materials safely and review outgoing inventory transactions.</span></span>
        </a>
        @endif

        @if($canViewReports)
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-blue-400 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-blue-100" href="{{ route('jmv.inventory.report') }}">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-blue-50 text-2xl text-blue-700"><i class="bi bi-file-earmark-spreadsheet"></i></span>
            <span><span class="block text-sm font-extrabold uppercase text-villa-900">Inventory Report</span><span class="mt-1 block text-xs leading-5 text-slate-500">Export current quantities, locations, stock levels and inventory value.</span></span>
        </a>
        @endif

        @if($isExecutiveViewer || auth()->user()->hasPermission('jmv.inventory.requests.create') || auth()->user()->hasPermission('jmv.inventory.requests.approve'))
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-violet-400 hover:shadow-lg" href="{{ route('jmv.requests.index') }}"><span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-violet-50 text-2xl text-violet-700"><i class="bi bi-clipboard-check"></i></span><span><span class="block text-sm font-extrabold uppercase text-villa-900">Stock Requests</span><span class="mt-1 block text-xs leading-5 text-slate-500">Request, approve and release materials through a controlled workflow.</span></span></a>
        @endif

        @if(auth()->user()->canManageUsers())
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-violet-400 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-violet-100" href="{{ route('users.index') }}">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-violet-50 text-2xl text-violet-700"><i class="bi bi-people"></i></span>
            <span><span class="block text-sm font-extrabold uppercase text-villa-900">User Management</span><span class="mt-1 block text-xs leading-5 text-slate-500">Manage company departments, positions and employee access.</span></span>
        </a>
        @endif
        @unless($canViewInventory || $canMoveStock || $canViewReports || $isExecutiveViewer || auth()->user()->hasPermission('jmv.inventory.requests.create') || auth()->user()->hasPermission('jmv.inventory.requests.approve') || auth()->user()->canManageUsers())
        <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center"><i class="bi bi-shield-lock mb-3 block text-3xl text-slate-400"></i><h2 class="text-lg font-extrabold text-slate-800">No application assigned</h2><p class="mt-1 text-sm text-slate-500">Contact your administrator to assign JMV inventory access to your position.</p></div>
        @endunless
    </div>
</section>
@endsection
