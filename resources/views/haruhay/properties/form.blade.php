@extends('layouts.app')
@section('title', ($property->exists ? 'Edit Property' : 'Add Property').' | '.$propertyCompany)
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-6 sm:px-7 lg:px-10"><div class="mx-auto max-w-5xl">
    <header class="mb-6 flex items-start justify-between gap-4"><div><p class="mb-2 text-xs font-extrabold uppercase tracking-widest text-teal-700">{{ $propertyCompany }} / Properties</p><h1 class="m-0 text-3xl font-black text-slate-900">{{ $property->exists ? 'Edit Property' : 'Add Property' }}</h1><p class="mb-0 mt-2 text-sm text-slate-500">{{ $property->exists ? $property->code : 'A property code will be generated when you save.' }}</p></div><a href="{{ route($propertyRoutes.'index') }}" class="btn btn-outline-secondary">Back to Properties</a></header>
    @if($errors->any())<div role="alert" class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><strong>Please check the following:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" enctype="multipart/form-data" action="{{ $property->exists ? route($propertyRoutes.'update', $property) : route($propertyRoutes.'store') }}" class="space-y-5">
        @csrf @if($property->exists) @method('PUT') @endif
        @include('haruhay.properties.fields')
        <div class="flex items-center justify-end gap-3 pb-6"><a href="{{ $property->exists ? route($propertyRoutes.'show', $property) : route($propertyRoutes.'index') }}" class="btn btn-outline-secondary">Cancel</a><button class="inline-flex min-h-12 items-center gap-2 rounded-xl border-0 bg-teal-800 px-6 text-sm font-extrabold text-white hover:bg-teal-900">{{ $property->exists ? 'Save Changes' : 'Save Property' }}</button></div>
    </form>
</div></section>
@endsection
