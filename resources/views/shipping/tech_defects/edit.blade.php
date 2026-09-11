@extends('layouts.app')

@section('title', 'Edit '.$report->report_code.' | Villa Shipping Lines')

@section('content')
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-slate-50 via-white to-villa-50 px-4 py-6 sm:px-7 lg:px-12"><div class="mx-auto max-w-5xl">
    <header class="mb-6 flex items-start justify-between gap-4"><div><p class="mb-1 text-xs font-extrabold uppercase tracking-[.16em] text-villa-600">{{ $report->report_code }}</p><h1 class="m-0 text-2xl font-black text-slate-900 sm:text-3xl">Edit defect report</h1><p class="mt-1 text-sm text-slate-500">Every changed field will be recorded in the audit trail.</p></div><a href="{{ route('tech-defects.show', $report) }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600 no-underline shadow-sm hover:border-villa-500 hover:text-villa-800"><i class="bi bi-arrow-left"></i><span class="hidden sm:inline">Back</span></a></header>
    @if(session('success'))<div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><ul class="mb-0 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('tech-defects.update', $report) }}" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">@csrf @method('PUT')<input type="hidden" name="action" value="update_details">
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-7"><div><h2 class="mb-0 text-lg font-black text-slate-900">Report details</h2><p class="mb-0 mt-1 text-sm text-slate-500">Completed and third-party waiting reports are protected from editing.</p></div><span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-extrabold text-blue-700">{{ $report->status }}</span></div>
        <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">@include('shipping.tech_defects.partials.form-fields', ['report' => $report])</div>
        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-7"><a href="{{ route('tech-defects.show', $report) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-bold text-slate-600 no-underline hover:bg-slate-100">Cancel</a><button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-villa-700 px-5 text-sm font-extrabold text-white shadow-lg shadow-villa-700/20 hover:bg-villa-800"><i class="bi bi-check2-circle"></i>Update report</button></div>
    </form>
</div></section>
@endsection
