@extends('layouts.app')

@section('title', 'Sales Monitoring | Yatira')

@section('content')
<main class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-5 sm:px-7 lg:px-10">
    <section class="mx-auto max-w-7xl">
        <header class="overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-blue-950 to-cyan-700 p-6 text-white shadow-xl sm:p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.2em] text-cyan-200">Yatira Construction, Inc.</p><h1 class="m-0 text-2xl font-black sm:text-4xl">Sales Monitoring</h1><p class="mb-0 mt-2 max-w-2xl text-sm text-blue-100">Register project opportunities, monitor every sales stage, and preserve a complete activity history.</p></div>
                <div class="flex flex-wrap gap-2">
                    <a class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 font-bold text-white no-underline hover:bg-white/20" href="{{ route('yatira.sales.criteria') }}"><i class="bi bi-list-check"></i> Criteria</a>
                    @if($canCreate)<button class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-white px-5 font-extrabold text-blue-950 shadow-lg hover:bg-cyan-50" type="button" data-bs-toggle="modal" data-bs-target="#addLeadModal"><i class="bi bi-plus-lg"></i> Register Lead</button>@endif
                </div>
            </div>
        </header>

        @if(session('success'))<div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 font-semibold text-emerald-800"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}</div>@endif
        @if($errors->any())<div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-800"><p class="mb-1 font-extrabold">Please correct the following:</p><ul class="mb-0 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-6">
            @foreach([['Total Leads',$stats['total'],'bi-people','text-blue-600'],['Active',$stats['active'],'bi-lightning-charge','text-cyan-600'],['Awarded',$stats['awarded'],'bi-trophy','text-emerald-600'],['Cancelled',$stats['cancelled'],'bi-x-circle','text-rose-600']] as [$label,$value,$icon,$colorClass])
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="mb-3 flex items-center justify-between"><span class="text-xs font-extrabold uppercase tracking-wide text-slate-500">{{ $label }}</span><i class="bi {{ $icon }} {{ $colorClass }}"></i></div><strong class="text-2xl font-black text-slate-900">{{ number_format($value) }}</strong></article>
            @endforeach
            <article class="col-span-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-1"><p class="mb-2 text-xs font-extrabold uppercase tracking-wide text-slate-500">Active Pipeline</p><strong class="text-lg font-black text-slate-900">₱{{ number_format($stats['pipeline'], 2) }}</strong></article>
            <article class="col-span-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-1"><p class="mb-2 text-xs font-extrabold uppercase tracking-wide text-slate-500">Weighted Value</p><strong class="text-lg font-black text-slate-900">₱{{ number_format($stats['weighted'], 2) }}</strong></article>
        </div>

        <section class="mt-5 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <form class="grid gap-3 border-b border-slate-200 bg-slate-50/70 p-4 md:grid-cols-5" method="GET">
                <div class="relative md:col-span-2"><i class="bi bi-search pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i><input class="form-control pl-11" name="search" value="{{ request('search') }}" placeholder="Search lead, client, project or location"></div>
                <select class="form-select" name="stage_id"><option value="">All stages</option>@foreach($stages as $stage)<option value="{{ $stage->id }}" @selected((string)request('stage_id')===(string)$stage->id)>{{ $stage->name }}</option>@endforeach</select>
                <select class="form-select" name="status"><option value="">All statuses</option>@foreach(['ACTIVE','AWARDED','CANCELLED'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst(strtolower($status)) }}</option>@endforeach</select>
                <div class="flex gap-2"><button class="btn btn-primary flex-1" type="submit">Filter</button><a class="btn btn-outline-secondary" href="{{ route('yatira.sales.index') }}" aria-label="Clear filters"><i class="bi bi-arrow-counterclockwise"></i></a></div>
            </form>
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[1050px] text-left text-sm"><thead class="bg-slate-900 text-xs uppercase tracking-wide text-white"><tr><th class="px-5 py-4">Lead</th><th class="px-5 py-4">Client / Project</th><th class="px-5 py-4">Agent</th><th class="px-5 py-4">Value</th><th class="px-5 py-4">Stage</th><th class="px-5 py-4">Probability</th><th class="px-5 py-4 text-right">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
                    @forelse($leads as $lead)<tr class="hover:bg-blue-50/40"><td class="px-5 py-4"><a class="font-black text-blue-800 no-underline hover:underline" href="{{ route('yatira.sales.show',$lead) }}">{{ $lead->lead_code }}</a><small class="mt-1 block text-slate-500">{{ $lead->date_registered->format('M d, Y') }}</small></td><td class="px-5 py-4"><strong class="block text-slate-900">{{ $lead->client_name }}</strong><span class="text-slate-500">{{ $lead->project_name }}</span></td><td class="px-5 py-4">{{ $lead->agent ? trim($lead->agent->name.' '.$lead->agent->lastname) : 'Unassigned' }}</td><td class="px-5 py-4 font-bold">₱{{ number_format((float)$lead->estimated_value,2) }}</td><td class="px-5 py-4"><span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-extrabold text-blue-800">{{ $lead->stage->name }}</span><small @class(['mt-1 block font-bold','text-rose-600'=>$lead->status==='CANCELLED','text-emerald-600'=>$lead->status==='AWARDED','text-slate-600'=>$lead->status==='ACTIVE'])>{{ $lead->status }}</small></td><td class="px-5 py-4"><div class="mb-1 flex justify-between text-xs font-bold"><span>{{ number_format((float)$lead->probability_percent,0) }}%</span></div><div class="h-2 w-28 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-blue-700" style="width:{{ min(100,(float)$lead->probability_percent) }}%"></div></div></td><td class="px-5 py-4 text-right"><a class="btn btn-outline-primary btn-sm" href="{{ route('yatira.sales.show',$lead) }}">View details</a></td></tr>
                    @empty<tr><td class="px-6 py-14 text-center text-slate-500" colspan="7"><i class="bi bi-inbox mb-2 block text-3xl"></i>No sales leads found.</td></tr>@endforelse
                </tbody></table>
            </div>
            <div class="grid gap-3 p-4 lg:hidden">@forelse($leads as $lead)<a class="rounded-2xl border border-slate-200 p-4 text-slate-800 no-underline shadow-sm" href="{{ route('yatira.sales.show',$lead) }}"><div class="flex items-start justify-between gap-3"><div><strong class="text-blue-800">{{ $lead->lead_code }}</strong><h2 class="mb-0 mt-1 text-base font-extrabold">{{ $lead->client_name }}</h2><p class="mb-0 text-sm text-slate-500">{{ $lead->project_name }}</p></div><span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-black text-blue-700">{{ number_format((float)$lead->probability_percent,0) }}%</span></div><div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs"><span class="font-bold text-slate-600">{{ $lead->stage->name }}</span><span class="font-extrabold">₱{{ number_format((float)$lead->estimated_value,2) }}</span></div></a>@empty<p class="py-10 text-center text-slate-500">No sales leads found.</p>@endforelse</div>
            @if($leads->hasPages())<div class="border-t border-slate-200 p-4">{{ $leads->links() }}</div>@endif
        </section>
    </section>
</main>

@if($canCreate)
<div class="modal" id="addLeadModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h2 class="modal-title">Register Sales Lead</h2><p class="mb-0 text-sm text-slate-500">The system assigns the lead ID and starting probability automatically.</p></div><button class="btn-close ml-auto" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><form method="POST" action="{{ route('yatira.sales.store') }}">@csrf<div class="modal-body">@include('yatira.sales._fields',['prefix'=>'create_'])</div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Save Lead</button></div></form></div></div></div>
@endif
@if($errors->any() && $canCreate)<script>document.addEventListener('DOMContentLoaded',()=>window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('addLeadModal')).show());</script>@endif
@endsection
