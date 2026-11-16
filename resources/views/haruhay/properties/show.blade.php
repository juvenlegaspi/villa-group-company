@extends('layouts.app')
@section('title', $property->name.' | '.$propertyCompany)
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-6 sm:px-7 lg:px-10"><div class="mx-auto max-w-6xl">
    <header class="mb-6 rounded-3xl bg-gradient-to-br from-emerald-950 via-teal-900 to-emerald-800 p-6 text-white sm:p-8"><div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center"><div><p class="mb-2 text-xs font-extrabold uppercase tracking-widest text-amber-300">{{ $propertyCompany }} / {{ $property->code }}</p><h1 class="m-0 break-words text-3xl font-black">{{ $property->name }}</h1><p class="mb-3 mt-2 text-sm text-emerald-100">{{ $property->type }} &middot; {{ $property->location }}</p>@include('haruhay.properties.status', ['status' => $property->status])</div><div class="flex shrink-0 flex-wrap gap-2"><a href="{{ route($propertyRoutes.'index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-white/30 px-4 text-sm font-bold text-white no-underline">All Properties</a>@if($canManage)<button type="button" data-bs-toggle="modal" data-bs-target="#updatePropertyModal" class="inline-flex min-h-11 items-center gap-2 rounded-xl border-0 bg-amber-300 px-4 text-sm font-extrabold text-emerald-950"><i class="bi bi-pencil" aria-hidden="true"></i>Update Property</button>@endif</div></div></header>
    @if(session('success'))<div role="status" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2"><h2 class="mb-5 text-lg font-black text-slate-900">Property Details</h2><dl class="grid gap-6 sm:grid-cols-2">
            @foreach([
                'Registered Owner' => $property->owner,
                'Property Type' => $property->type,
                'Location' => $property->location,


                'Number of Floors' => $property->floors,
                'Lot Area' => $property->lot_area !== null ? number_format((float) $property->lot_area, 2).' sq.m' : null,
                'Floor Area' => $property->floor_area !== null ? number_format((float) $property->floor_area, 2).' sq.m' : null,
            ] as $label => $value)<div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="mb-0 mt-1 break-words text-sm font-semibold text-slate-800">{{ $value ?: '—' }}</dd></div>@endforeach
        </dl><div class="mt-7 border-t border-slate-100 pt-5"><h3 class="text-xs font-bold uppercase tracking-wide text-slate-400">Description / Remarks</h3><p class="mb-0 whitespace-pre-wrap break-words text-sm leading-7 text-slate-600">{{ $property->remarks ?: 'No remarks added.' }}</p></div></section>
        <aside class="space-y-5"><section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="mb-4 text-lg font-black text-slate-900">Property Manager</h2><p class="mb-1 text-sm font-bold text-teal-900">{{ $property->person_in_charge ?: 'Not assigned' }}</p><p class="mb-0 text-sm text-slate-500">{{ $property->contact_number ?: 'No contact number' }}</p></section><section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="mb-4 text-lg font-black text-slate-900">Record Information</h2><p class="text-xs leading-6 text-slate-500">Added by <strong>{{ $property->creator?->name ?? '—' }}</strong><br>{{ $property->created_at->format('M d, Y g:i A') }}</p><p class="mb-0 text-xs leading-6 text-slate-500">Last updated by <strong>{{ $property->updater?->name ?? '—' }}</strong><br>{{ $property->updated_at->format('M d, Y g:i A') }}</p></section></aside>
    </div>
    <section class="mt-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="mb-1 text-lg font-black text-slate-900">Photos &amp; Documents <span class="ml-2 text-sm text-slate-400">{{ $property->attachments->count() }}</span></h2><p class="mb-5 text-xs text-slate-500">Download the supporting files for this property.</p><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@forelse($property->attachments as $attachment)<a href="{{ route($propertyRoutes.'attachment', [$property, $attachment]) }}" class="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 text-slate-700 no-underline hover:border-teal-400 hover:bg-teal-50"><i class="bi bi-file-earmark-text text-2xl text-teal-700" aria-hidden="true"></i><span class="min-w-0"><span class="block break-words text-sm font-bold">{{ $attachment->original_name }}</span><span class="mt-1 block text-xs text-slate-400">{{ number_format($attachment->size / 1024, 1) }} KB &middot; Download</span></span></a>@empty<p class="mb-0 text-sm text-slate-400">No attachments uploaded.</p>@endforelse</div></section>
</div></section>
@if($canManage)
    @include('haruhay.properties.update-modal')
@endif
@endsection
