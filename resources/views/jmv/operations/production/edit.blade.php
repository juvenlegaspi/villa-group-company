@extends('layouts.app')
@section('title', 'Edit '.$log->log_no.' | JMV Daily Production')
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-6 sm:px-7"><div class="mx-auto max-w-5xl">
    <a href="{{ route('jmv.operations.production.show',$log) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-bold text-villa-700 no-underline"><i class="bi bi-arrow-left"></i>Back to log</a>
    <header class="mb-5 rounded-3xl bg-gradient-to-r from-slate-950 via-villa-900 to-amber-600 p-6 text-white shadow-xl"><p class="mb-1 text-xs font-black uppercase tracking-[.18em] text-amber-200">JMV Operations</p><h1 class="m-0 text-2xl font-black">Edit Daily Production Log</h1><p class="mb-0 mt-1 text-sm text-white/75">{{ $log->log_no }}</p></header>
    @if($errors->any())<div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" enctype="multipart/form-data" action="{{ route('jmv.operations.production.update',$log) }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">@csrf @method('PUT')
        @include('jmv.operations.production.partials.form',['log'=>$log])
        <div class="mt-6 flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end"><a href="{{ route('jmv.operations.production.show',$log) }}" class="grid min-h-11 place-items-center rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-700 no-underline">Cancel</a><button class="min-h-11 rounded-xl border-0 bg-villa-800 px-6 text-sm font-black text-white">Save Changes</button></div>
    </form>
    <datalist id="productionLocations"><option value="Mine 1"><option value="Mine 2"></datalist><datalist id="productionShifts"><option value="8:00 AM - 5:00 PM"><option value="5:00 PM - 2:00 AM"></datalist>
</div></section>
@endsection
