@php
    $tone = match($status) {
        'Active', 'Operational' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'Inactive' => 'bg-rose-50 text-rose-800 ring-rose-200',
        'Under Construction' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'For Sale' => 'bg-blue-50 text-blue-800 ring-blue-200',
        'Sold' => 'bg-violet-50 text-violet-800 ring-violet-200',
        default => 'bg-slate-100 text-slate-600 ring-slate-200',
    };
@endphp
<span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset {{ $tone }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $status }}</span>
