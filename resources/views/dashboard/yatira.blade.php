@extends('layouts.app')

@section('title', 'Yatira Dashboard | Villa Group')

@section('content')
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-slate-50 via-white to-amber-50/40 px-4 py-6 sm:px-7 lg:px-12">
    <div class="mx-auto max-w-7xl">
        <header class="mb-6 overflow-hidden rounded-3xl bg-gradient-to-r from-villa-900 via-villa-800 to-amber-700 p-6 text-white shadow-xl sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="mb-1 text-xs font-extrabold uppercase tracking-[.18em] text-amber-200">Yatira Construction, Inc.</p><h1 class="m-0 text-2xl font-black sm:text-3xl">Operations Command Center</h1><p class="mt-1 text-sm text-blue-100">Supplier, fixed-asset and consumable inventory visibility in one workspace.</p></div><a href="{{ route('supplier.report') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-4 text-sm font-extrabold text-villa-900 no-underline shadow-lg hover:bg-amber-50"><i class="bi bi-file-earmark-pdf"></i>Export PDF</a></div>
        </header>

        <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([['Total Suppliers', $metrics['totalSuppliers'], 'bi-buildings', 'bg-blue-50 text-blue-700'], ['Active', $metrics['activeSuppliers'], 'bi-check2-circle', 'bg-emerald-50 text-emerald-700'], ['Inactive', $metrics['inactiveSuppliers'], 'bi-shield-lock', 'bg-slate-100 text-slate-600'], ['Added Today', $metrics['todaySuppliers'], 'bi-calendar-check', 'bg-emerald-50 text-emerald-700'], ['This Month', $metrics['thisMonthSuppliers'], 'bi-graph-up-arrow', 'bg-amber-50 text-amber-700']] as [$label, $value, $icon, $tone])
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between"><div><p class="mb-1 text-xs font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</p><p class="mb-0 text-3xl font-black text-slate-900">{{ number_format($value) }}</p></div><span class="grid h-12 w-12 place-items-center rounded-2xl text-xl {{ $tone }}"><i class="bi {{ $icon }}"></i></span></div></article>
            @endforeach
        </div>

        <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([['Fixed Assets', $metrics['totalAssets'], 'bi-clipboard-data', 'bg-blue-50 text-blue-700'], ['Under Maintenance', $metrics['maintenanceAssets'], 'bi-tools', 'bg-amber-50 text-amber-700'], ['Disposed Assets', $metrics['disposedAssets'], 'bi-archive', 'bg-slate-100 text-slate-600'], ['Consumable Items', $metrics['totalConsumableItems'], 'bi-box-seam', 'bg-emerald-50 text-emerald-700'], ['Low Stock', $metrics['lowStockItems'], 'bi-exclamation-triangle', 'bg-rose-50 text-rose-700']] as [$label, $value, $icon, $tone])
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between"><div><p class="mb-1 text-xs font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</p><p class="mb-0 text-3xl font-black text-slate-900">{{ number_format($value) }}</p></div><span class="grid h-12 w-12 place-items-center rounded-2xl text-xl {{ $tone }}"><i class="bi {{ $icon }}"></i></span></div></article>
            @endforeach
        </div>

        <div class="grid gap-5 lg:grid-cols-[1.4fr_.8fr]">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="mb-5"><h2 class="mb-1 text-lg font-black text-slate-900">Supplier activity</h2><p class="mb-0 text-sm text-slate-500">Daily additions during the last 30 days.</p></div><div class="h-72"><canvas id="supplierChart" aria-label="Supplier activity chart"></canvas></div></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="mb-1 text-lg font-black text-slate-900">Top products</h2><p class="mb-5 text-sm text-slate-500">Most frequently registered supplier products.</p><div class="space-y-2">@forelse($metrics['topProducts'] as $index => $product)<div class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3"><span class="grid h-8 w-8 place-items-center rounded-lg bg-amber-100 text-xs font-black text-amber-800">{{ $index + 1 }}</span><span class="text-sm font-bold text-slate-700">{{ $product ?: 'Unspecified product' }}</span></div>@empty<p class="rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">No supplier products recorded yet.</p>@endforelse</div></article>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('supplierChart');
    if (!canvas || typeof Chart === 'undefined') return;
    new Chart(canvas, { type: 'line', data: { labels: @json($metrics['chartLabels']), datasets: [{ label: 'Suppliers added', data: @json($metrics['chartData']), borderColor: '#285794', backgroundColor: 'rgba(53,105,173,.12)', borderWidth: 3, pointBackgroundColor: '#d5b35d', pointRadius: 3, fill: true, tension: .35 }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } } } });
});
</script>
@endpush
