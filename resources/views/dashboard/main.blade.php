@extends('layouts.app')

@section('title', 'Companies | Villa Group')

@section('content')
<section class="relative isolate min-h-[calc(100svh-74px)] overflow-hidden bg-gradient-to-br from-white to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <div class="pointer-events-none absolute -bottom-48 -right-36 -z-10 h-[430px] w-[430px] rounded-full border-[70px] border-villa-900/[.035]"></div>
    <header class="mx-auto mb-6 flex max-w-6xl flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="mb-2 flex items-center gap-2 text-xs font-extrabold uppercase tracking-[.15em] text-villa-600 before:h-0.5 before:w-6 before:rounded before:bg-villa-gold before:content-['']">Villa Group Network</p>
            <h1 class="m-0 text-2xl font-extrabold tracking-tight text-villa-900 sm:text-3xl">Choose a company</h1>
            <p class="mt-1 max-w-xl text-sm leading-6 text-slate-500">Access the tools, reports and daily operations assigned to your account.</p>
        </div>
        <div class="inline-flex items-center gap-2 whitespace-nowrap rounded-full border border-villa-900/10 bg-white/80 px-3 py-2 text-xs font-bold text-villa-700 shadow-sm">
            <i class="bi bi-buildings" aria-hidden="true"></i>{{ $divisions->count() }} {{ Str::plural('company', $divisions->count()) }}
        </div>
    </header>

    @if ($divisions->isEmpty())
        <div class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">No company is assigned to your account. Please contact the administrator.</div>
    @else
        <div class="mx-auto grid max-w-6xl grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($divisions as $division)
                @php
                    $key = strtolower(trim($division->name));
                    $isShipping = str_contains($key, 'shipping');
                    $isYatira = str_contains($key, 'yatira');
                    $isJmv = str_contains($key, 'jmv');
                    $isHyve = str_contains($key, 'hyve');
                    $isVillaGroup = str_contains($key, 'corporate') || str_contains($key, 'villa group');
                    $displayName = match (true) {
                        $isShipping => 'Villa Shipping Lines, Inc.', $isYatira => 'Yatira Construction, Inc.',
                        $isJmv => 'JMV Mining & Development', $isVillaGroup => 'Villa Group',
                        $isHyve => 'HYVE', default => $division->name,
                    };
                    $logoFile = match (true) {
                        $isShipping => 'villa-shipping-lines.webp', $isYatira => 'yatira.webp', $isJmv => 'jmv.webp',
                        $isVillaGroup => 'villa-group.webp', $isHyve => 'hyve.webp', default => 'villa-group.webp',
                    };
                    $description = match (true) {
                        $isShipping => 'Maritime Operations', $isYatira => 'Construction Operations',
                        $isJmv => 'Mining & Development', $isVillaGroup => 'Admin, IT & R&D',
                        $isHyve => 'Hospitality & Venue', default => 'Company Workspace',
                    };
                    $target = match (true) {
                        $isShipping => route('shipping.applications'),
                        $isYatira => route('yatira.applications'),
                        $isJmv => route('jmv.applications'),
                        $isHyve => route('hyve.projects.index'),
                        $isVillaGroup && auth()->user()->canManageUsers() => route('users.index'),
                        default => route('profile'),
                    };
                @endphp

                <a class="group relative flex min-h-32 overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-800 no-underline shadow-sm transition hover:-translate-y-1 hover:border-villa-500 hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-villa-100 md:min-h-44 md:flex-col"
                   href="{{ $target }}" aria-label="Open {{ $displayName }}">
                    <span class="absolute inset-x-0 top-0 h-1 origin-left scale-x-[.3] bg-gradient-to-r from-villa-gold to-villa-900 transition group-hover:scale-x-100"></span>
                    <span class="grid w-28 shrink-0 place-items-center bg-gradient-to-b from-slate-50 to-white p-3 md:min-h-28 md:w-full md:p-4">
                        <img @class(['block rounded-xl object-contain transition group-hover:scale-[1.03]', 'h-20 w-24 md:h-24 md:w-36' => $isShipping, 'h-20 w-20 md:h-24 md:w-24 shadow-sm' => !$isShipping])
                             src="{{ asset('images/companies/'.$logoFile) }}" alt="{{ $displayName }} logo" width="132" height="132">
                    </span>
                    <span class="flex min-w-0 flex-1 items-center justify-between gap-3 p-4">
                        <span class="min-w-0"><span class="block text-sm font-extrabold uppercase leading-snug text-villa-900">{{ $displayName }}</span><span class="mt-1 block text-xs font-semibold text-slate-400">{{ $description }}</span></span>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-villa-700 transition group-hover:translate-x-1 group-hover:bg-villa-700 group-hover:text-white" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</section>
@endsection
