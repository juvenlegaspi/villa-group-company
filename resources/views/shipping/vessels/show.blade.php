@extends('layouts.app')

@section('title', $vessel->vessel_name.' | Vessel Management')

@section('content')
@php
    $status = strtoupper(trim((string) $vessel->vessel_status));
    $statusClass = match ($status) {
        'OPERATIONAL' => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        'DRY DOCKING' => 'bg-amber-100 text-amber-900 ring-amber-600/20',
        'NON-OPERATIONAL', 'DECOMMISSIONED' => 'bg-rose-100 text-rose-800 ring-rose-600/20',
        default => 'bg-slate-100 text-slate-700 ring-slate-500/20',
    };
@endphp

<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-white to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <div class="mx-auto max-w-7xl">
        <article class="relative mb-5 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-villa-gold via-villa-600 to-villa-900"></div>
            <div class="p-5 sm:p-7">
                <div class="flex flex-col justify-between gap-6 xl:flex-row xl:items-start">
                    <div class="min-w-0 flex-1">
                        <div class="mb-6 flex items-center gap-4">
                            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-villa-50 text-villa-700 ring-1 ring-villa-100">
                                <i class="bi bi-ship text-2xl" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="mb-1 text-xs font-extrabold uppercase tracking-[.14em] text-villa-600">Vessel Information</p>
                                <h1 class="truncate text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">{{ $vessel->vessel_name }}</h1>
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.68rem] font-extrabold uppercase tracking-wider text-slate-400">IMO Number</dt><dd class="mt-1 break-words text-sm font-bold text-slate-800">{{ $vessel->imo_number ?: '—' }}</dd></div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.68rem] font-extrabold uppercase tracking-wider text-slate-400">Call Sign</dt><dd class="mt-1 break-words text-sm font-bold text-slate-800">{{ $vessel->call_sign ?: '—' }}</dd></div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.68rem] font-extrabold uppercase tracking-wider text-slate-400">Vessel Type</dt><dd class="mt-1 break-words text-sm font-bold text-slate-800">{{ $vessel->vessel_type ?: '—' }}</dd></div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.68rem] font-extrabold uppercase tracking-wider text-slate-400">DWT</dt><dd class="mt-1 break-words text-sm font-bold text-slate-800">{{ $vessel->dwt ?: '—' }}</dd></div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.68rem] font-extrabold uppercase tracking-wider text-slate-400">Fuel Type</dt><dd class="mt-1 break-words text-sm font-bold text-slate-800">{{ $vessel->fuel_type ?: '—' }}</dd></div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.68rem] font-extrabold uppercase tracking-wider text-slate-400">Service Speed</dt><dd class="mt-1 text-sm font-bold text-slate-800">{{ $vessel->service_speed ? $vessel->service_speed.' knots' : '—' }}</dd></div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.68rem] font-extrabold uppercase tracking-wider text-slate-400">Status</dt><dd class="mt-1.5"><span class="inline-flex rounded-full px-2.5 py-1 text-[.68rem] font-extrabold uppercase tracking-wide ring-1 ring-inset {{ $statusClass }}">{{ $vessel->vessel_status ?: 'Not set' }}</span></dd></div>
                        </dl>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2 xl:pt-1">
                        <a href="{{ route('vessels.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-extrabold text-slate-700 no-underline shadow-sm hover:border-slate-400 hover:bg-slate-50 hover:text-slate-950 focus:outline-none focus:ring-4 focus:ring-slate-200">
                            <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
                        </a>
                        @if(!$hasOpenVoyage)
                            <a href="{{ url('/shipping/vessels/' . $vessel->id . '/logs/create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-villa-700 px-4 text-sm font-extrabold text-white no-underline shadow-md shadow-villa-900/15 hover:bg-villa-900 hover:text-white focus:outline-none focus:ring-4 focus:ring-villa-100">
                                <i class="bi bi-plus" aria-hidden="true"></i> Add Voyage
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </article>

        <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <form method="GET" action="{{ route('vessels.show', $vessel->id) }}" class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_minmax(180px,240px)_auto_auto] md:items-end">
                <div class="min-w-0">
                    <label class="form-label" for="voyage-search">Search voyages</label>
                    <div class="relative">
                        <i class="bi bi-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input id="voyage-search" type="search" name="search" class="form-control pl-11" placeholder="Voyage, port, or cargo" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="min-w-0">
                    <label class="form-label" for="voyage-sort">Sort records</label>
                    <select id="voyage-sort" name="sort" class="form-select">
                        <option value="">Latest voyage</option>
                        <option value="activity" @selected(request('sort') === 'activity')>Activity</option>
                        <option value="date" @selected(request('sort') === 'date')>Date</option>
                    </select>
                </div>
                <button class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-villa-700 px-5 text-sm font-extrabold text-white shadow-sm hover:bg-villa-900 focus:outline-none focus:ring-4 focus:ring-villa-100 md:w-auto" type="submit">
                    <i class="bi bi-search" aria-hidden="true"></i> Search
                </button>
                <a href="{{ route('vessels.show', $vessel->id) }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-extrabold text-slate-700 no-underline shadow-sm hover:border-slate-400 hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-4 focus:ring-slate-200 md:w-auto">Reset</a>
            </form>
        </div>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div><h2 class="text-base font-extrabold text-slate-900">Voyage Records</h2><p class="mt-0.5 text-xs text-slate-500">Click a record to open its activities and details.</p></div>
                <span class="rounded-full bg-villa-50 px-3 py-1.5 text-xs font-extrabold text-villa-700">{{ $voyages->total() }} {{ Str::plural('voyage', $voyages->total()) }}</span>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] border-collapse text-left text-sm">
                    <thead><tr class="bg-slate-50 text-[.68rem] font-extrabold uppercase tracking-wider text-slate-500">
                        <th class="px-5 py-3.5">Voyage ID</th><th class="px-4 py-3.5">Date Start</th><th class="px-4 py-3.5">Date End</th><th class="px-4 py-3.5">Port Origin</th><th class="px-4 py-3.5">Port Destination</th><th class="px-4 py-3.5">Voyage No.</th><th class="px-4 py-3.5 text-center">Activities</th><th class="px-4 py-3.5">Voyage Hours</th><th class="px-5 py-3.5">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($voyages as $voyage)
                            @php
                                $isCompleted = strtoupper((string) $voyage->status) === 'COMPLETED';
                            @endphp
                            <tr class="voyage-row cursor-pointer transition hover:bg-villa-50/60 focus-within:bg-villa-50/60" data-url="{{ url('/shipping/voyage-logs/' . $voyage->voyage_id) }}" tabindex="0">
                                <td class="whitespace-nowrap px-5 py-4"><a class="inline-flex rounded-lg bg-villa-700 px-2.5 py-1.5 text-xs font-extrabold text-white no-underline hover:bg-villa-900 hover:text-white" href="{{ url('/shipping/voyage-logs/' . $voyage->voyage_id) }}">{{ $voyage->voyage_code }}</a></td>
                                <td class="whitespace-nowrap px-4 py-4 font-semibold text-slate-700">{{ optional($voyage->date_created)->format('M d, Y') ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ optional($voyage->date_completed)->format('M d, Y') ?: '—' }}</td>
                                <td class="px-4 py-4 text-slate-700">{{ $voyage->port_location ?: '—' }}</td>
                                <td class="px-4 py-4 text-slate-700">{{ $voyage->port_destination ?: '—' }}</td>
                                <td class="px-4 py-4 font-semibold text-slate-700">{{ $voyage->voyage_no ?: '—' }}</td>
                                <td class="px-4 py-4 text-center"><span class="inline-flex min-w-8 justify-center rounded-full bg-slate-100 px-2 py-1 text-xs font-extrabold text-slate-700 ring-1 ring-inset ring-slate-200">{{ $voyage->details->count() }}</span></td>
                                <td class="whitespace-nowrap px-4 py-4">@if($isCompleted)<span class="font-semibold text-slate-700">{{ number_format($voyage->total_hours_voyage, 2) }} hrs</span>@else<span class="rounded-full bg-amber-100 px-2.5 py-1 text-[.68rem] font-extrabold text-amber-900 ring-1 ring-inset ring-amber-600/20">ONGOING</span>@endif</td>
                                <td class="whitespace-nowrap px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-[.68rem] font-extrabold uppercase tracking-wide ring-1 ring-inset {{ $isCompleted ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-blue-100 text-blue-800 ring-blue-600/20' }}">{{ $voyage->status ?: 'OPEN' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-6 py-14 text-center"><span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-xl text-slate-400"><i class="bi bi-map"></i></span><p class="font-extrabold text-slate-700">No voyage records found</p><p class="mt-1 text-xs text-slate-500">Try changing the search or sort filters.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($voyages->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $voyages->links() }}</div>@endif
        </article>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.voyage-row').forEach((row) => {
    const openVoyage = () => window.location.assign(row.dataset.url);
    row.addEventListener('click', (event) => { if (!event.target.closest('a, button')) openVoyage(); });
    row.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openVoyage(); } });
});
</script>
@endpush
