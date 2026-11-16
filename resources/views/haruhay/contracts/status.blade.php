@php
    $tone = match($contract->status) {
        'Active' => 'bg-emerald-50 text-emerald-800',
        'Expiring Soon' => 'bg-amber-50 text-amber-800',
        'Scheduled' => 'bg-blue-50 text-blue-800',
        'Terminated' => 'bg-rose-50 text-rose-800',
        'Overdue' => 'bg-rose-50 text-rose-800',
        'Renewed' => 'bg-slate-100 text-slate-600',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp
<span class="inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold {{ $tone }}">{{ $contract->status }}</span>
