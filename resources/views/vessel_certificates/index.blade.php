@extends('layouts.app')

@section('content')
<main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:py-8">
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="mb-1 text-xs font-bold uppercase tracking-[.2em] text-villa-600">Villa Shipping Lines</p>
            <h1 class="mb-1 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Certificate monitoring</h1>
            <p class="mb-0 text-sm text-slate-500">Select a vessel to review its active, expiring, and historical certificates.</p>
        </div>
        @if($canManage)
            <span class="inline-flex w-fit items-center rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200">Certificate manager access</span>
        @endif
    </header>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($vessels as $vessel)
            <a href="{{ route('vessel.certificates.show', $vessel) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 no-underline shadow-sm transition hover:-translate-y-0.5 hover:border-villa-300 hover:shadow-lg">
                <div class="flex items-start justify-between gap-4">
                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-villa-50 text-villa-700 ring-1 ring-villa-100">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-7 4h8M9 4h6l3 3v13H6V4h3Zm6 0v4h4"/></svg>
                    </div>
                    <svg class="h-5 w-5 text-slate-300 transition group-hover:translate-x-1 group-hover:text-villa-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg>
                </div>
                <h2 class="mb-1 mt-4 text-lg font-extrabold text-slate-900">{{ $vessel->vessel_name }}</h2>
                <p class="mb-4 text-sm text-slate-500">{{ $vessel->certificate_count }} current {{ Str::plural('certificate', $vessel->certificate_count) }}</p>
                <div class="flex flex-wrap gap-2">
                    @if($vessel->expired_count)
                        <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700 ring-1 ring-rose-200">{{ $vessel->expired_count }} expired</span>
                    @endif
                    @if($vessel->expiring_count)
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 ring-1 ring-amber-200">{{ $vessel->expiring_count }} expiring</span>
                    @endif
                    @if(!$vessel->expired_count && !$vessel->expiring_count)
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200">No immediate expiry risk</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No vessels are available within your assignment.</div>
        @endforelse
    </section>
</main>
@endsection
