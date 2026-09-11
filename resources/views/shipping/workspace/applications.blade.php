@extends('layouts.app')

@section('title', 'Applications | Villa Shipping Lines')

@section('content')
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-white to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <header class="mx-auto mb-6 flex max-w-6xl items-start justify-between gap-5">
        <div>
            <p class="mb-1 text-xs font-extrabold uppercase tracking-[.14em] text-villa-600">Villa Shipping Lines</p>
            <h1 class="m-0 text-2xl font-extrabold tracking-tight text-villa-900 sm:text-3xl">Applications</h1>
            <p class="mt-1 text-sm text-slate-500">Choose the workspace you want to open.</p>
        </div>
        <a class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600 no-underline shadow-sm hover:border-villa-500 hover:text-villa-800"
           href="{{ route('companies') }}"><i class="bi bi-arrow-left"></i><span class="hidden sm:inline">Back to companies</span></a>
    </header>

    <div class="mx-auto grid max-w-6xl grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        @if(auth()->user()->hasPermission('shipping.operations.access'))
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-villa-500 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-villa-100" href="{{ route('shipping.operations') }}">
            <span class="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-blue-50 text-2xl text-blue-700"><i class="bi bi-gear-wide-connected"></i></span>
            <span><span class="block text-sm font-extrabold uppercase text-villa-900">Operations</span><span class="mt-1 block text-xs leading-5 text-slate-500">Vessel management, defects, certificates and dry docking.</span></span>
        </a>
        @endif
        @if(auth()->user()->hasPermission('shipping.procurement.access'))
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-amber-400 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-amber-100" href="{{ route('shipping.application.placeholder', 'procurement') }}">
            <span class="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-amber-50 text-2xl text-amber-700"><i class="bi bi-cart-check"></i></span>
            <span><span class="block text-sm font-extrabold uppercase text-villa-900">Procurement</span><span class="mt-1 block text-xs leading-5 text-slate-500">Purchase orders, suppliers and material sourcing.</span><small class="mt-1 block text-[.65rem] font-extrabold uppercase tracking-wider text-amber-700">Coming soon</small></span>
        </a>
        @endif
        @if(auth()->user()->hasPermission('shipping.inventory.access'))
        <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-emerald-400 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-emerald-100" href="{{ route('shipping.application.placeholder', 'inventory') }}">
            <span class="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-2xl text-emerald-700"><i class="bi bi-clipboard-data"></i></span>
            <span><span class="block text-sm font-extrabold uppercase text-villa-900">Inventory</span><span class="mt-1 block text-xs leading-5 text-slate-500">Stock movements and warehouse management.</span><small class="mt-1 block text-[.65rem] font-extrabold uppercase tracking-wider text-amber-700">Coming soon</small></span>
        </a>
        @endif
        @if(auth()->user()->canManageUsers())
            <a class="group flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-violet-400 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-violet-100" href="{{ route('users.index') }}">
                <span class="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-violet-50 text-2xl text-violet-700"><i class="bi bi-people"></i></span>
                <span><span class="block text-sm font-extrabold uppercase text-villa-900">User Management</span><span class="mt-1 block text-xs leading-5 text-slate-500">Manage organization assignments, positions, access profiles, and approvers.</span></span>
            </a>
        @endif
        @unless(auth()->user()->hasPermission('shipping.operations.access') || auth()->user()->hasPermission('shipping.procurement.access') || auth()->user()->hasPermission('shipping.inventory.access') || auth()->user()->canManageUsers())
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center"><i class="bi bi-shield-lock mb-3 block text-3xl text-slate-400"></i><h2 class="text-lg font-extrabold text-slate-800">No application assigned</h2><p class="mt-1 text-sm text-slate-500">Contact your company administrator if your department requires application access.</p></div>
        @endunless
    </div>
</section>
@endsection
