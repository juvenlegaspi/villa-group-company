@extends('layouts.app')

@section('title', 'Add User | Villa Group')

@section('content')
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-slate-50 via-white to-villa-50 px-4 py-6 sm:px-7 lg:px-12">
    <div class="mx-auto max-w-5xl">
        <header class="mb-6 flex items-start justify-between gap-4">
            <div><p class="mb-1 text-xs font-extrabold uppercase tracking-[.16em] text-villa-600">User Management</p><h1 class="m-0 text-2xl font-black text-slate-900 sm:text-3xl">Create New User</h1><p class="mt-1 text-sm text-slate-500">Set the employee identity, assignment and system access.</p></div>
            <a href="{{ route('users.index') }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600 no-underline shadow-sm hover:border-villa-500 hover:text-villa-800"><i class="bi bi-arrow-left"></i><span class="hidden sm:inline">Back</span></a>
        </header>

        @if ($errors->any())<div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p class="mb-2 font-extrabold">Please correct the following:</p><ul class="mb-0 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ route('users.store') }}" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
            @csrf
            <div class="border-b border-slate-100 px-5 py-5 sm:px-7"><h2 class="mb-0 text-lg font-black text-slate-900">Account Information</h2><p class="mb-0 mt-1 text-sm text-slate-500">New users start with the temporary password <strong>villa@2026</strong> and must replace it on first login.</p></div>
            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7 lg:grid-cols-3">
                @include('users.partials.fields', ['user' => null])
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-7"><a href="{{ route('users.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-bold text-slate-600 no-underline hover:bg-slate-100">Cancel</a><button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-villa-700 px-5 text-sm font-extrabold text-white shadow-lg shadow-villa-700/20 hover:bg-villa-800 focus:outline-none focus:ring-4 focus:ring-villa-100"><i class="bi bi-person-check"></i>Create User</button></div>
        </form>
    </div>
</section>
@endsection

@include('users.partials.assignment-script')
