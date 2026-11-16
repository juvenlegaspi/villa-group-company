@extends('layouts.app')
@section('title', 'Inventory | '.$assetCompanyShort)
@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-5 py-6"><div class="mx-auto max-w-6xl">
<header class="mb-6 rounded-2xl bg-gradient-to-r from-villa-900 to-slate-800 p-6 text-white"><p class="mb-1 text-xs font-bold uppercase tracking-wider text-amber-200">{{ $assetCompanyLabel }}</p><h1 class="m-0 text-2xl font-black">Inventory</h1><p class="mb-0 mt-2 text-sm text-blue-100">Choose the company inventory workspace.</p></header>
<div class="grid gap-4 md:grid-cols-2">
@if($canAssets)<a href="{{ route($assetIndexRoute) }}" class="rounded-2xl border border-slate-200 bg-white p-6 text-slate-800 no-underline shadow-sm hover:border-blue-400"><i class="bi bi-clipboard-data text-3xl text-blue-700"></i><h2 class="mt-4 text-lg font-black">Fixed Assets</h2><p class="mb-0 mt-2 text-sm text-slate-500">Asset register, custody, condition, supporting documents, history and QR tag printing.</p></a>@endif
@if($canStock)<a href="{{ route('jmv.inventory.index') }}" class="rounded-2xl border border-slate-200 bg-white p-6 text-slate-800 no-underline shadow-sm hover:border-amber-400"><i class="bi bi-box-seam text-3xl text-amber-700"></i><h2 class="mt-4 text-lg font-black">Stock Inventory</h2><p class="mb-0 mt-2 text-sm text-slate-500">Existing materials, stock movements, warehouse balances and requests.</p></a>@endif
</div><a href="{{ route($assetApplicationsRoute) }}" class="mt-6 inline-block text-sm font-bold text-villa-800">Back to applications</a>
</div></section>
@endsection
