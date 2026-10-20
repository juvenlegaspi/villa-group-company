@extends('layouts.app')
@section('title', 'Daily Production Monitoring | JMV Operations')
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-3 py-5 sm:px-6 lg:px-10"><div class="mx-auto max-w-[1600px]">
    @include('jmv.operations.production.partials.module-header')
    @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><strong>The requested action could not be completed.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <header class="mb-5 overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-villa-900 to-amber-600 p-5 text-white shadow-xl sm:p-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="mb-1 text-xs font-black uppercase tracking-[.18em] text-amber-200">Monitoring workspace</p><h2 class="m-0 text-2xl font-black sm:text-3xl">Production Records</h2><p class="mt-1 text-sm text-white/75">Encode, review, approve, and monitor official daily mine production records.</p></div><div class="flex flex-wrap gap-2"><a href="{{ route('jmv.applications') }}" class="inline-flex min-h-11 items-center rounded-xl border border-white/20 bg-white/10 px-4 text-sm font-bold text-white no-underline">Back</a>@if($canCreate)<button type="button" data-new-production-log data-bs-toggle="modal" data-bs-target="#addProductionLog" class="inline-flex min-h-11 items-center gap-2 rounded-xl border-0 bg-amber-400 px-5 text-sm font-black text-slate-950"><i class="bi bi-plus-lg"></i>Add Log</button>@endif</div></div>
    </header>

    @if($canApprove)
    <a href="{{ route('jmv.operations.production.index', ['status' => \App\Models\JmvDailyProductionLog::STATUS_PENDING_REVIEW]) }}" class="mb-3 flex items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 no-underline shadow-sm"><span class="flex min-w-0 items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-100"><i class="bi bi-inbox"></i></span><span><b class="block leading-tight">Review Queue</b><span class="mt-0.5 block text-xs sm:text-sm">Open logs waiting for supervisor or manager approval.</span></span></span><i class="bi bi-arrow-right-circle shrink-0 text-xl"></i></a>
    @endif

    <div class="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
        @foreach([['Official Logs',$summary['logs'],'bi-journal-check','text-blue-700 bg-blue-50'],['Workers',$summary['workers'],'bi-people','text-violet-700 bg-violet-50'],['Soil Output',$summary['soil'].' Bagon','bi-layers','text-amber-700 bg-amber-50'],['Coal Output',$summary['coal'].' Bagon','bi-gem','text-slate-700 bg-slate-100'],['Fuel',number_format($summary['fuel'],2).' LTRS','bi-fuel-pump','text-emerald-700 bg-emerald-50']] as [$label,$value,$icon,$tone])
        <article class="flex min-w-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $tone }}"><i class="bi {{ $icon }}"></i></span><div class="min-w-0"><p class="mb-0.5 truncate text-[11px] font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</p><p class="m-0 truncate text-lg font-black text-slate-900 sm:text-xl">{{ $value }}</p></div></article>
        @endforeach
    </div>

    <form method="GET" class="mb-3 grid gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-2 lg:grid-cols-[minmax(210px,1.5fr)_145px_145px_145px_165px_auto]">
        <label class="min-w-0"><span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wide text-slate-500">Search records</span><span class="relative block"><i class="bi bi-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i><input name="search" value="{{ request('search') }}" placeholder="Log no., location or personnel" class="min-h-10 w-full rounded-xl border border-slate-300 py-2 pl-9 pr-3 text-sm"></span></label>
        <label><span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wide text-slate-500">Month</span><input type="month" name="month" value="{{ request('month') }}" class="min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"></label>
        <label><span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wide text-slate-500">Location</span><select name="location" class="min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"><option value="">All locations</option>@foreach($locations as $location)<option @selected(request('location')===$location)>{{ $location }}</option>@endforeach</select></label>
        <label><span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wide text-slate-500">Shift</span><select name="shift" class="min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"><option value="">All shifts</option>@foreach($shifts as $shift)<option @selected(request('shift')===$shift)>{{ $shift }}</option>@endforeach</select></label>
        <label><span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wide text-slate-500">Status</span><select name="status" class="min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"><option value="">All statuses</option>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
        <div class="flex items-end gap-2"><button class="grid min-h-10 flex-1 place-items-center rounded-xl border-0 bg-villa-800 px-4 text-sm font-bold text-white" title="Apply filters"><i class="bi bi-funnel-fill lg:hidden"></i><span class="hidden lg:inline">Apply</span></button><a href="{{ route('jmv.operations.production.index') }}" class="grid min-h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-300 bg-white text-slate-600 no-underline" title="Clear filters" aria-label="Clear filters"><i class="bi bi-arrow-counterclockwise"></i></a></div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5"><div class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-villa-100 text-villa-800"><i class="bi bi-journal-text"></i></span><div><h2 class="m-0 text-sm font-black uppercase tracking-wide text-slate-900 sm:text-base">Daily Production Records</h2><p class="mb-0 mt-0.5 text-xs text-slate-500">Click a Log No. to open its complete details.</p></div></div>@if($canExport)<a href="{{ route('jmv.operations.production.export', request()->query()) }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 text-xs font-extrabold text-white no-underline"><i class="bi bi-file-earmark-spreadsheet"></i>Export Completed Logs</a>@endif</div>
        <div class="divide-y divide-slate-100">
        @forelse($logs as $log)
            <article class="grid gap-4 px-4 py-4 transition hover:bg-amber-50/40 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-[minmax(180px,1.15fr)_145px_minmax(155px,1fr)_minmax(185px,1.1fr)_minmax(210px,1.35fr)_minmax(170px,1fr)] xl:items-center">
                <div class="min-w-0">
                    <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Date / Log No.</span>
                    <strong class="block text-sm text-slate-900">{{ $log->log_date->format('M d, Y') }}</strong>
                    <a href="{{ route('jmv.operations.production.show',$log) }}" class="mt-1 inline-flex max-w-full items-center gap-1.5 rounded-lg bg-villa-50 px-2.5 py-1.5 text-xs font-black text-villa-800 no-underline ring-1 ring-inset ring-villa-200 hover:bg-villa-100" title="Open complete log details"><span class="truncate">{{ $log->log_no }}</span><i class="bi bi-arrow-up-right-circle shrink-0"></i></a>
                </div>
                <div><span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Status</span>@include('jmv.operations.production.partials.status-badge',['status'=>$log->status])</div>
                <div><span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Assignment</span><strong class="block text-sm text-slate-900">{{ $log->location }}</strong><span class="mt-0.5 block text-xs text-slate-500"><i class="bi bi-clock me-1"></i>{{ $log->shift }}</span></div>
                <div><span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Production Team</span><div class="grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5 text-xs"><span class="text-slate-400">Leadman</span><b class="truncate text-slate-800">{{ $log->leadman }}</b><span class="text-slate-400">Foreman</span><b class="truncate text-slate-800">{{ $log->foreman }}</b><span class="text-slate-400">Workers</span><b class="text-slate-900">{{ number_format($log->total_workers) }}</b></div></div>
                <div><span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Resources Used</span><div class="flex flex-wrap gap-1.5 text-[11px] font-bold"><span class="rounded-md bg-amber-50 px-2 py-1 text-amber-800">Timber {{ number_format($log->timber_used,2) }} {{ $log->timber_unit }}</span><span class="rounded-md bg-blue-50 px-2 py-1 text-blue-800">Nails {{ number_format($log->nails_used,2) }} {{ $log->nails_unit }}</span><span class="rounded-md bg-emerald-50 px-2 py-1 text-emerald-800">Fuel {{ number_format($log->fuel_consumed,2) }} LTRS</span></div></div>
                <div><span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Production Output</span><div class="grid grid-cols-3 gap-1 text-center"><span class="rounded-lg bg-amber-50 p-1.5"><small class="block text-[9px] font-bold uppercase text-amber-700">Soil</small><b class="text-sm text-amber-900">{{ number_format($log->soil_output) }}</b></span><span class="rounded-lg bg-slate-100 p-1.5"><small class="block text-[9px] font-bold uppercase text-slate-500">Coal</small><b class="text-sm text-slate-900">{{ number_format($log->coal_output) }}</b></span><span class="rounded-lg {{ ($log->excess_deficit ?? 0)<0?'bg-rose-50 text-rose-800':'bg-emerald-50 text-emerald-800' }} p-1.5"><small class="block text-[9px] font-bold uppercase">Ex/Def</small><b class="text-sm">{{ is_null($log->excess_deficit)?'—':(($log->excess_deficit>0?'+':'').number_format($log->excess_deficit)) }}</b></span></div></div>
            </article>
        @empty
            <div class="px-6 py-16 text-center text-slate-500"><i class="bi bi-journal-x mb-2 block text-3xl text-slate-300"></i><b class="block text-slate-700">No production logs found</b><span class="mt-1 block text-sm">Try changing or clearing the active filters.</span></div>
        @endforelse
        </div><div class="border-t border-slate-100 p-4">{{ $logs->links() }}</div>
    </div>
</div></section>

<datalist id="productionLocations">@foreach($locations as $location)<option value="{{ $location }}">@endforeach<option value="Mine 1"><option value="Mine 2"></datalist>
<datalist id="productionShifts">@foreach($shifts as $shift)<option value="{{ $shift }}">@endforeach<option value="8:00 AM - 5:00 PM"><option value="5:00 PM - 2:00 AM"></datalist>

@if($canCreate)<div class="modal fade" id="addProductionLog" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form id="addProductionLogForm" method="POST" enctype="multipart/form-data" autocomplete="off" action="{{ route('jmv.operations.production.store') }}" class="modal-content overflow-hidden rounded-4 border-0 shadow-2xl">@csrf<div class="modal-header border-bottom px-4 py-3"><div><p class="mb-1 text-xs font-black uppercase tracking-wider text-amber-700">JMV Operations</p><h2 class="modal-title fs-4 fw-black">New Daily Production Log</h2><p class="mb-0 mt-1 text-xs text-slate-500">The record will be saved as Draft before submission.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body bg-slate-50 px-4 py-4 sm:px-5">@include('jmv.operations.production.partials.form')</div><div class="modal-footer border-top bg-white"><button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary rounded-3 px-4"><i class="bi bi-save me-2"></i>Save Draft</button></div></form></div></div></div>@endif
@endsection
@push('scripts')
@if($canCreate)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('addProductionLog');
    const form = document.getElementById('addProductionLogForm');
    if (!modalElement || !form) return;
    const clearNewLogForm = () => form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(field => {
        if (field.type === 'file') field.value = '';
        else if (field.tagName === 'SELECT') field.selectedIndex = 0;
        else field.value = '';
    });
    let openingFreshLog = false;
    document.querySelectorAll('[data-new-production-log]').forEach(button => button.addEventListener('click', () => { openingFreshLog = true; clearNewLogForm(); }));
    modalElement.addEventListener('shown.bs.modal', () => { if (openingFreshLog) { clearNewLogForm(); openingFreshLog = false; } });
    modalElement.addEventListener('hidden.bs.modal', clearNewLogForm);
    @if($errors->any()) bootstrap.Modal.getOrCreateInstance(modalElement).show(); @endif
});
</script>
@endif
@endpush
