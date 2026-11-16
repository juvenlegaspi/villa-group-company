@extends('layouts.app')
@section('title', 'Property Database | '.$propertyCompany)
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-5 sm:px-6 lg:px-8"><div class="mx-auto max-w-7xl">
    <header class="mb-5 flex flex-col justify-between gap-4 rounded-2xl bg-villa-900 px-5 py-4 text-white shadow-sm sm:flex-row sm:items-center">
        <div><h1 class="m-0 text-xl font-black tracking-tight sm:text-2xl">PROPERTY MANAGEMENT SYSTEM</h1><p class="mb-0 mt-1 text-sm font-extrabold uppercase tracking-wider text-amber-300">Property Database</p></div>
        <div class="flex flex-wrap gap-2">@if($canManage)<button type="button" data-bs-toggle="modal" data-bs-target="#addPropertyModal" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 text-xs font-bold text-white hover:bg-white/20"><i class="bi bi-plus-lg" aria-hidden="true"></i>New Property</button>@endif<a href="{{ route($propertyRoutes.'index') }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 text-xs font-bold text-white no-underline hover:bg-white/20"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i>Refresh</a></div>
    </header>
    @if(session('success'))<div role="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif
    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
        @foreach([
            ['Total Properties', number_format($stats['total']), 'buildings', 'bg-blue-50 text-blue-700'],
            ['Total Units', number_format($stats['total_units']), 'house', 'bg-emerald-50 text-emerald-700'],
            ['Occupied Units', number_format($stats['occupied_units']), 'people', 'bg-violet-50 text-violet-700'],
            ['Vacant Units', number_format($stats['vacant_units']), 'house', 'bg-amber-50 text-amber-700'],
            ['Avg. Occupancy Rate', number_format($stats['occupancy_rate'], 2).'%', 'bar-chart-line', 'bg-teal-50 text-teal-700'],
        ] as [$label, $value, $icon, $tone])
        <article class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-xl {{ $tone }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><div><p class="mb-1 text-[.65rem] font-extrabold uppercase leading-4 text-slate-500">{{ $label }}</p><p class="m-0 text-2xl font-black text-villa-900">{{ $value }}</p></div></article>
        @endforeach
    </div>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-4"><h2 class="sr-only">Property Directory</h2>
            <form method="GET" action="{{ route($propertyRoutes.'index') }}" class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_180px_190px_auto]">
                <label class="text-xs font-extrabold uppercase text-villa-900">Search Property<input type="search" name="search" value="{{ request('search') }}" maxlength="255" placeholder="Property ID, name, manager or location..." class="form-control mt-2"></label>
                <label class="text-xs font-bold text-slate-600">Property Type<select name="type" class="form-control mt-2"><option value="">All types</option>@foreach($types as $type)<option @selected(request('type') === $type)>{{ $type }}</option>@endforeach</select></label>
                <label class="text-xs font-bold text-slate-600">Status<select name="status" class="form-control mt-2"><option value="">All statuses</option>@foreach($statuses as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></label>
                <div class="flex gap-2"><button class="btn bg-villa-900 text-white">Search</button><a href="{{ route($propertyRoutes.'index') }}" class="btn btn-outline-secondary">Reset</a></div>
            </form>
            @if($errors->any())<p role="alert" class="mb-0 mt-3 text-sm text-rose-700">{{ $errors->first() }}</p>@endif
        </div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1250px] text-left text-xs">
            <thead class="bg-villa-900 text-white"><tr>@foreach(['Property ID', 'Property Name', 'Location', 'Property Type', 'No. of Units', 'Occupied Units', 'Vacant Units', 'Occupancy Rate', 'Property Manager', 'Status'] as $heading)<th class="whitespace-nowrap border-r border-white/10 px-3 py-3 font-bold">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($properties as $property)
                @php($occupancyRate = $property->units_count ? $property->occupied_units_count / $property->units_count * 100 : 0)
                <tr class="transition even:bg-slate-50/50 hover:bg-blue-50/50">
                    <td class="whitespace-nowrap px-3 py-4 font-mono font-bold text-villa-800"><a href="{{ route($propertyRoutes.'show', $property) }}" class="text-villa-800 underline decoration-villa-200 underline-offset-4 hover:text-villa-600" aria-label="View property {{ $property->code }}">{{ $property->code }}</a></td>
                    <td class="max-w-52 px-3 py-4"><a href="{{ route($propertyRoutes.'show', $property) }}" class="font-bold text-slate-800 no-underline hover:text-villa-700">{{ $property->name }}</a></td>
                    <td class="max-w-52 px-3 py-4 leading-5 text-slate-600">{{ $property->location }}</td>
                    <td class="px-3 py-4 text-slate-600">{{ $property->type }}</td>
                    <td class="px-3 py-4 text-center font-bold text-slate-700">{{ number_format($property->units_count) }}</td>
                    <td class="px-3 py-4 text-center text-slate-600">{{ number_format($property->occupied_units_count) }}</td>
                    <td class="px-3 py-4 text-center text-slate-600">{{ number_format($property->vacant_units_count) }}</td>
                    <td class="px-3 py-4"><span class="block text-center font-bold text-villa-800">{{ number_format($occupancyRate, 2) }}%</span><span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-teal-600" style="width:{{ round($occupancyRate, 2) }}%"></span></span></td>
                    <td class="px-3 py-4 text-slate-600">{{ $property->person_in_charge ?: '—' }}</td>
                    <td class="px-3 py-4">@include('haruhay.properties.status', ['status' => $property->status])</td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-6 py-12 text-center"><h3 class="text-base font-black text-slate-800">No properties to display</h3><p class="mb-0 text-sm text-slate-500">Add your first property or adjust your filters.</p></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <footer class="space-y-3 border-t border-slate-200 px-4 py-3"><p class="mb-0 text-xs text-slate-500">Showing {{ $properties->firstItem() ?? 0 }} to {{ $properties->lastItem() ?? 0 }} of {{ $properties->total() }} records · Occupancy is based on rooms, not headcount.</p>@if($properties->hasPages()){{ $properties->links() }}@endif</footer>
    </section>
</div></section>
@if($canManage)
    @include('haruhay.properties.create-modal')
@endif
@endsection
