@extends('layouts.app')
@section('title', 'Units / Rooms | Haruhay Space')
@push('styles')
<style>.property-unit-filter { min-height: 38px; height: 38px; padding: 7px 10px; font-size: 12px; }</style>
@endpush
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-3 sm:px-6 lg:px-8"><div class="mx-auto max-w-7xl">
    <header class="mb-4 rounded-2xl bg-gradient-to-br from-emerald-950 via-teal-900 to-emerald-800 px-5 py-4 text-white shadow-sm"><div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><p class="mb-1 text-[.65rem] font-extrabold uppercase tracking-wider text-amber-300">Haruhay Space / Database Management</p><h1 class="m-0 text-xl font-black sm:text-2xl">Units / Rooms</h1><p class="mb-0 mt-1 text-xs leading-5 text-emerald-100">A clear view of every room, its rental rate and occupancy.</p></div>@if($canManage)
    @if(isset($editingUnit))<a href="{{ route('haruhay.units.index', ['add' => 1]) }}" class="inline-flex min-h-10 w-fit items-center gap-2 rounded-xl bg-amber-300 px-4 text-xs font-extrabold text-emerald-950 no-underline">Add Room</a>@else<button type="button" data-bs-toggle="modal" data-bs-target="#unitModal" class="inline-flex min-h-10 w-fit shrink-0 items-center gap-2 rounded-xl border-0 bg-amber-300 px-4 text-xs font-extrabold text-emerald-950 shadow-sm hover:bg-amber-200"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add Room</button>@endif
    @endif</div></header>
    @if(session('success'))<div role="status" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif
    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach($stats as $label => $value)
        <article class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-teal-50 text-teal-700"><i class="bi bi-{{ $label === 'Under Maintenance' ? 'tools' : 'house' }}" aria-hidden="true"></i></span><div><p class="mb-1 text-[.65rem] font-bold leading-4 text-slate-500">{{ $label }}</p><p class="m-0 text-2xl font-black text-slate-900">{{ number_format($value) }}</p></div></article>
        @endforeach
    </div>
    @if($properties->isEmpty())<div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No Haruhay property is available yet. Contact the Villa Group property administrator to set up the property before adding rooms.</div>@endif
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3"><h2 class="mb-1 text-base font-black text-slate-900">Room Directory</h2><p class="mb-3 text-xs text-slate-500">{{ number_format($units->total()) }} {{ Str::plural('room', $units->total()) }} matching your filters</p>
            <form method="GET" action="{{ route('haruhay.units.index') }}" class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <label class="text-xs font-bold text-slate-600 xl:col-span-2">Search<input type="search" name="search" value="{{ request('search') }}" placeholder="Room, property, type or tenant..." maxlength="255" class="form-control mt-1 property-unit-filter"></label>
                <label class="text-xs font-bold text-slate-600">Property<select name="property_id" class="form-control mt-1 property-unit-filter"><option value="">All properties</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected((string) request('property_id') === (string) $property->id)>{{ $property->name }}</option>@endforeach</select></label>
                <label class="text-xs font-bold text-slate-600">Floor<input type="number" min="0" max="999" name="floor" value="{{ request('floor') }}" placeholder="All floors" class="form-control mt-1 property-unit-filter"></label>
                <label class="text-xs font-bold text-slate-600">Occupancy<select name="status" class="form-control mt-1 property-unit-filter"><option value="">All statuses</option>@foreach($statuses as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></label>
                <label class="text-xs font-bold text-slate-600">Room Condition<select name="room_condition" class="form-control mt-1 property-unit-filter"><option value="">All conditions</option>@foreach($conditions as $condition)<option @selected(request('room_condition') === $condition)>{{ $condition }}</option>@endforeach</select></label>
                <div class="flex gap-2"><button class="btn bg-teal-800 text-white">Filter</button><a href="{{ route('haruhay.units.index') }}" class="btn btn-outline-secondary">Reset</a></div>
            </form>
            @if($errors->getBag('default')->any())<p role="alert" class="mb-0 mt-3 text-sm text-rose-700">{{ $errors->getBag('default')->first() }}</p>@endif
        </div>
        <div class="overflow-x-auto"><table class="w-full min-w-[950px] text-left text-xs"><thead class="border-b border-slate-200 bg-slate-50 text-[.65rem] uppercase tracking-wider text-slate-500"><tr>@foreach(['Room No.', 'Property', 'Floor', 'Monthly Rent', 'Occupancy', 'Occupied By', 'Room Condition'] as $heading)<th class="px-4 py-3 font-extrabold">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($units as $unit)<tr class="hover:bg-teal-50/40">
                <td class="whitespace-nowrap px-4 py-3"><a href="{{ route('haruhay.units.show', $unit) }}" class="font-extrabold text-teal-900 no-underline">{{ $unit->unit_no }}</a></td>
                <td class="max-w-48 px-4 py-3"><span class="font-semibold text-slate-700">{{ $unit->property->name }}</span></td>
                <td class="px-4 py-3 text-slate-600">{{ $unit->floor === 0 ? 'Ground' : $unit->floor }}</td>
                <td class="px-4 py-3">@include('haruhay.units.rent')</td>
                <td class="px-4 py-3">@include('haruhay.units.status', ['status' => $unit->status])<span class="mt-1 block whitespace-nowrap text-xs text-slate-500">{{ $unit->occupants_count ?? 0 }} occupied · {{ $unit->reservations_count ?? 0 }} reserved</span><span class="mt-1 block text-xs font-semibold text-teal-800">{{ $unit->available_slots }} available {{ Str::plural('slot', $unit->available_slots) }}</span></td>
                <td class="min-w-36 max-w-56 whitespace-pre-line break-words px-4 py-3 font-semibold text-slate-700">{{ $unit->occupied_by ?: '—' }}</td>
                <td class="px-4 py-3">@include('haruhay.units.condition', ['condition' => $unit->room_condition])</td>

            </tr>@empty<tr><td colspan="7" class="px-6 py-10 text-center"><span class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-2xl bg-teal-50 text-3xl text-teal-700"><i class="bi bi-house" aria-hidden="true"></i></span><h3 class="text-lg font-black text-slate-800">No rooms to display</h3><p class="mb-0 text-sm text-slate-500">Add your first room or adjust your filters.</p></td></tr>@endforelse
        </tbody></table></div>
        @if($units->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $units->links() }}</div>@endif
    </div>
    @if($canManage)@include('haruhay.units.modal', ['room' => $editingUnit ?? new \App\Models\HaruhayUnit])@endif
</div></section>
@endsection
