@extends('layouts.app')

@section('title', 'Applications | Yatira Construction')

@section('content')
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-white via-amber-50/30 to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <header class="mx-auto mb-7 max-w-6xl overflow-hidden rounded-3xl bg-gradient-to-r from-villa-900 via-villa-800 to-amber-700 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="mb-1 text-xs font-extrabold uppercase tracking-[.18em] text-amber-200">Yatira Construction, Inc.</p><h1 class="m-0 text-2xl font-black tracking-tight sm:text-3xl">Company Applications</h1><p class="mt-1 max-w-2xl text-sm text-blue-100">Open the workspace assigned to your department and position.</p></div><a class="inline-flex min-h-11 w-fit shrink-0 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 text-sm font-bold text-white no-underline hover:bg-white/20" href="{{ route('companies') }}"><i class="bi bi-arrow-left"></i><span>Companies</span></a></div>
    </header>

    @php
        $canSuppliers = auth()->user()->hasPermission('yatira.suppliers.view');
        $canAssets = auth()->user()->hasPermission('yatira.assets.view');
        $canConsumables = auth()->user()->hasPermission('yatira.consumables.view');
        $canReports = auth()->user()->hasPermission('yatira.reports.view');
        $canSales = auth()->user()->hasPermission('yatira.sales.view');
    @endphp
    <div class="mx-auto grid max-w-6xl grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @if($canSuppliers)
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-amber-400 hover:shadow-lg" href="{{ route('suppliers.index') }}"><span class="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-amber-50 text-2xl text-amber-700"><i class="bi bi-buildings"></i></span><span><span class="block text-sm font-extrabold uppercase text-villa-900">Supplier Management</span><span class="mt-1 block text-xs leading-5 text-slate-500">Supplier profiles, commercial terms, contacts and audit history.</span></span></a>
        @endif
        @if($canAssets)
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-blue-400 hover:shadow-lg" href="{{ route('yatira.inventory.index') }}"><span class="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-blue-50 text-2xl text-blue-700"><i class="bi bi-clipboard-data"></i></span><span><span class="block text-sm font-extrabold uppercase text-villa-900">Fixed Assets</span><span class="mt-1 block text-xs leading-5 text-slate-500">Asset custody, condition, lifecycle, documents and printable tags.</span></span></a>
        @endif
        @if($canConsumables)
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-emerald-400 hover:shadow-lg" href="{{ route('yatira.inventory.index', ['tab' => 'consumables']) }}"><span class="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-2xl text-emerald-700"><i class="bi bi-boxes"></i></span><span><span class="block text-sm font-extrabold uppercase text-villa-900">Consumables</span><span class="mt-1 block text-xs leading-5 text-slate-500">Stock balances, reorder levels and controlled stock movements.</span></span></a>
        @endif
        @if($canReports || auth()->user()->isAdmin())
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-violet-400 hover:shadow-lg" href="{{ route('supplier.report') }}"><span class="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-violet-50 text-2xl text-violet-700"><i class="bi bi-file-earmark-text"></i></span><span><span class="block text-sm font-extrabold uppercase text-villa-900">Reports</span><span class="mt-1 block text-xs leading-5 text-slate-500">Export the current supplier compliance and contact register.</span></span></a>
        @endif
        @if($canSales)
        <a class="group relative flex min-h-28 items-center gap-4 overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-rose-400 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-rose-100" href="{{ route('yatira.sales.index') }}">
            <span class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-rose-500 to-amber-500"></span>
            <span class="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-rose-50 text-2xl text-rose-700"><i class="bi bi-graph-up-arrow"></i></span>
            <span><span class="block text-sm font-extrabold uppercase text-villa-900">Sales Monitoring</span><span class="mt-1 block text-xs leading-5 text-slate-500">Track clients, project leads, sales stages and winning probability.</span><small class="mt-1 block text-[.65rem] font-extrabold uppercase tracking-wider text-rose-600">New module</small></span>
        </a>
        @endif
        @if(auth()->user()->canManageUsers())
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-villa-400 hover:shadow-lg" href="{{ route('users.index') }}"><span class="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-villa-50 text-2xl text-villa-700"><i class="bi bi-people"></i></span><span><span class="block text-sm font-extrabold uppercase text-villa-900">User Management</span><span class="mt-1 block text-xs leading-5 text-slate-500">Manage departments, positions and module permissions.</span></span></a>
        @endif
    </div>
</section>
@endsection
