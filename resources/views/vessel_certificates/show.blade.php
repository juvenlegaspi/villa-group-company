@extends('layouts.app')

@section('content')
<main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:py-8">
    @if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
    <header class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('vessel-certificates.index') }}" class="mb-3 inline-flex items-center gap-1 text-sm font-bold text-villa-700 no-underline hover:text-villa-900">← All vessels</a>
            <p class="mb-1 text-xs font-bold uppercase tracking-[.2em] text-villa-600">Certificate register</p>
            <h1 class="mb-1 text-2xl font-extrabold text-slate-950 sm:text-3xl">{{ $vessel->vessel_name }}</h1>
            <p class="mb-0 text-sm text-slate-500">Compliance position as of {{ $today->format('F d, Y') }}</p>
        </div>
        @if($canManage)
            <a href="{{ route('vessel-certificates.add', $vessel) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-villa-700 px-4 py-2.5 text-sm font-bold text-white no-underline shadow-md shadow-blue-900/15 hover:bg-villa-800">+ Add certificate</a>
        @endif
    </header>

    <section class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach([['Total', $counts['total'], 'slate'], ['Valid', $counts['valid'], 'emerald'], ['Expiring', $counts['expiring'], 'amber'], ['Expired', $counts['expired'], 'rose']] as [$label,$value,$tone])
            <div class="rounded-2xl border border-{{ $tone }}-200 bg-{{ $tone }}-50 p-4">
                <p class="mb-1 text-xs font-bold uppercase tracking-wide text-{{ $tone }}-700">{{ $label }}</p>
                <p class="mb-0 text-2xl font-extrabold text-slate-950">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" class="grid gap-3 md:grid-cols-[1fr_220px_auto]">
            <input type="search" name="search" value="{{ request('search') }}" class="min-h-11 rounded-xl border border-slate-300 bg-slate-50 px-4 text-sm outline-none focus:border-villa-500 focus:bg-white focus:ring-4 focus:ring-villa-100" placeholder="Search certificate name">
            <select name="filter" class="min-h-11 rounded-xl border border-slate-300 bg-slate-50 px-3 text-sm outline-none focus:border-villa-500 focus:ring-4 focus:ring-villa-100">
                <option value="">Current certificates</option>
                @foreach(['valid'=>'Valid','expiring'=>'Expiring within 30 days','expired'=>'Expired','superseded'=>'Renewal history'] as $value=>$label)
                    <option value="{{ $value }}" @selected(request('filter') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button class="min-h-11 flex-1 rounded-xl bg-slate-900 px-4 text-sm font-bold text-white hover:bg-slate-800">Apply</button>
                <a href="{{ route('vessel.certificates.show', $vessel) }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700 no-underline hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] border-collapse text-left text-sm">
                <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-200"><tr><th class="px-5 py-4">Certificate</th><th class="px-4 py-4">Issue date</th><th class="px-4 py-4">Expiry date</th><th class="px-4 py-4">Remaining</th><th class="px-4 py-4">Status</th><th class="px-4 py-4">Document</th><th class="px-5 py-4 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($certificates as $certificate)
                    @php
                        $days = $certificate->expiry_date ? today()->diffInDays($certificate->expiry_date, false) : null;
                        [$status,$badge] = match($certificate->workflow_status) {
                            'draft' => ['Draft','bg-blue-50 text-blue-700'],
                            'pending_approval' => ['Pending approval','bg-violet-50 text-violet-700'],
                            'superseded' => ['Superseded','bg-slate-100 text-slate-600'],
                            default => $days < 0 ? ['Expired','bg-rose-50 text-rose-700'] : ($days <= 30 ? ['Expiring','bg-amber-50 text-amber-700'] : ['Valid','bg-emerald-50 text-emerald-700']),
                        };
                    @endphp
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4"><p class="mb-0 font-extrabold text-slate-900">{{ $certificate->certificate_name }}</p><p class="mb-0 mt-1 text-xs text-slate-500">Updated {{ optional($certificate->updated_at)->format('M d, Y') }}</p></td>
                        <td class="px-4 py-4 text-slate-700">{{ optional($certificate->issue_date)->format('M d, Y') ?: '—' }}</td>
                        <td class="px-4 py-4 font-semibold text-slate-800">{{ optional($certificate->expiry_date)->format('M d, Y') ?: '—' }}</td>
                        <td class="px-4 py-4 text-slate-600">{{ is_null($days) ? '—' : ($days < 0 ? abs($days).' days overdue' : $days.' days') }}</td>
                        <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $badge }}">{{ $status }}</span></td>
                        <td class="px-4 py-4">@if($certificate->document)<a href="{{ route('vessel-certificates.document', $certificate) }}" class="font-bold text-villa-700 no-underline hover:underline">Download</a>@else<span class="text-slate-400">No file</span>@endif</td>
                        <td class="px-5 py-4"><div class="flex justify-end gap-2">
                            @if($canManage && $certificate->workflow_status === 'active')
                                <a href="{{ route('vessel-certificates.edit', $certificate) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 no-underline hover:bg-slate-50">Edit</a>
                            @endif
                            @if($canManage && $certificate->workflow_status === 'active')
                                <a href="{{ route('vessel-certificates.renew', $certificate) }}" class="rounded-lg bg-villa-700 px-3 py-2 text-xs font-bold text-white no-underline hover:bg-villa-800">Renew</a>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No certificates match the selected filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($certificates->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $certificates->links() }}</div>@endif
    </section>
</main>
@endsection
