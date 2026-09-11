@extends('layouts.app')

@section('title', ($division->name ?? 'Division').' Dashboard')

@section('content')
<section class="grid min-h-[calc(100svh-74px)] place-items-center bg-gradient-to-br from-slate-50 to-villa-50 px-4 py-10"><article class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-200/60"><span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-villa-50 text-3xl text-villa-700"><i class="bi bi-bar-chart"></i></span><p class="mb-1 mt-6 text-xs font-extrabold uppercase tracking-[.16em] text-villa-600">Dashboard preparation</p><h1 class="mb-2 text-2xl font-black text-slate-900">{{ $division->name ?? 'Division' }}</h1><p class="mb-0 text-sm leading-6 text-slate-500">The division is available, but its reporting dashboard has not been configured yet. No error occurred and no operational data was changed.</p></article></section>
@endsection
