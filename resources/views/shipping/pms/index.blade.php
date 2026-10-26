@extends('layouts.app')
@section('title', 'PMS | Villa Shipping Lines')
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-white to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <header class="mx-auto mb-8 max-w-7xl">
        <p class="mb-1 text-xs font-extrabold uppercase tracking-[.14em] text-villa-600">Operations</p>
        <h1 class="m-0 text-2xl font-extrabold tracking-tight text-villa-900 sm:text-3xl">PMS</h1>
        <p class="mt-1 text-sm text-slate-500">Planned maintenance for each vessel.</p>
    </header>
    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        @forelse($vessels as $vessel)
            @php
                $status = strtolower(trim((string) $vessel->vessel_status));
                $captainName = trim(($vessel->captain->name ?? '').' '.($vessel->captain->lastname ?? '')) ?: 'Not assigned';
            @endphp
            <article class="group relative flex min-h-32 items-center gap-4 rounded-[1.65rem] border-2 border-villa-700 bg-white p-3.5 shadow-sm transition hover:-translate-y-1 hover:shadow-xl sm:p-4">
                <a class="absolute inset-0 z-10 rounded-[1.55rem] focus:outline-none focus:ring-4 focus:ring-villa-100" href="{{ route('shipping.pms.vessels.show', $vessel) }}" aria-label="Open PMS for {{ $vessel->vessel_name }}"></a>
                <span class="relative grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-sky-100 via-blue-50 to-villa-100 text-villa-700 sm:h-28 sm:w-28">
                    <svg class="h-14 w-14 transition group-hover:scale-110" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                        <path d="M32 7v13M22 20h20l5 17 10 5-6 12H13L7 42l10-5 5-17Z" fill="currentColor" opacity=".14"/>
                        <path d="M32 7v13M22 20h20l5 17 10 5-6 12H13L7 42l10-5 5-17ZM17 37h30M32 20v17" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11 48c5 3 9 3 14 0 5 3 9 3 14 0 5 3 9 3 14 0" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                </span>
                <div class="relative min-w-0 flex-1">
                    <div class="mb-2 min-w-0">
                        <h2 class="truncate text-base font-extrabold text-slate-950 sm:text-lg">{{ $vessel->vessel_name }}</h2>
                        <span class="text-[.68rem] font-extrabold uppercase tracking-wider text-slate-400">VN-{{ str_pad($vessel->id, 3, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <dl class="space-y-1 text-xs">
                        <div class="flex gap-1.5"><dt class="font-bold uppercase text-slate-400">Captain:</dt><dd class="truncate font-semibold text-slate-600">{{ $captainName }}</dd></div>
                        <div class="flex items-center gap-1.5"><dt class="font-bold uppercase text-slate-400">Status:</dt><dd @class([
                            'rounded-full px-2 py-0.5 text-[.66rem] font-extrabold uppercase tracking-wide',
                            'bg-emerald-50 text-emerald-700' => $status === 'operational',
                            'bg-amber-50 text-amber-700' => $status === 'dry docking',
                            'bg-rose-50 text-rose-700' => in_array($status, ['non-operational', 'decommissioned'], true),
                            'bg-slate-100 text-slate-600' => !in_array($status, ['operational', 'dry docking', 'non-operational', 'decommissioned'], true),
                        ])>{{ $vessel->vessel_status ?: 'Not set' }}</dd></div>
                    </dl>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                <span class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-villa-50 text-2xl text-villa-700"><i class="bi bi-ship" aria-hidden="true"></i></span>
                <h2 class="text-lg font-extrabold text-slate-800">No vessels found</h2>
                <p class="mt-1 text-sm text-slate-500">No vessels are available for your account.</p>
            </div>
        @endforelse
    </div>
    @if($vessels->hasPages())
        <div class="mx-auto mt-7 max-w-7xl">{{ $vessels->links() }}</div>
    @endif
</section>
@endsection
