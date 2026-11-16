@extends('layouts.app')
@section('title', 'HR | Villa Group')
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-6 sm:px-8"><div class="mx-auto max-w-6xl"><header class="mb-6 flex items-start justify-between gap-4"><div><p class="mb-1 text-xs font-extrabold uppercase tracking-widest text-villa-600">Villa Group</p><h1 class="m-0 text-2xl font-black text-villa-900">Human Resources</h1></div><a href="{{ route('villa-group.applications') }}" class="btn btn-outline-secondary">Applications</a></header><div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"><h2 class="text-lg font-black text-slate-800">HR workspace</h2><p class="mb-0 text-sm text-slate-500">Human resources applications will be added here.</p></div></div></section>
@endsection
