@extends('layouts.app')
@section('title', 'IT | Villa Group')
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-6 sm:px-8"><div class="mx-auto max-w-6xl"><header class="mb-6 flex items-start justify-between gap-4"><div><p class="mb-1 text-xs font-extrabold uppercase tracking-widest text-villa-600">Villa Group</p><h1 class="m-0 text-2xl font-black text-villa-900">IT</h1><p class="mt-1 text-sm text-slate-500">System administration and employee access.</p></div><a href="{{ route('villa-group.applications') }}" class="btn btn-outline-secondary">Applications</a></header><section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-black text-slate-900">User Management</h2><p class="text-sm text-slate-500">Manage employee accounts, company assignments, positions and access profiles.</p><a href="{{ route('users.index') }}" class="btn bg-villa-800 text-white">Open User Management</a></section></div></section>
@endsection
