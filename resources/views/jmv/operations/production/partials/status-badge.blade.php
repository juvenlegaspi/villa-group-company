@php
    $tones = [
        \App\Models\JmvDailyProductionLog::STATUS_DRAFT => 'bg-slate-100 text-slate-700',
        \App\Models\JmvDailyProductionLog::STATUS_PENDING_REVIEW => 'bg-amber-100 text-amber-800',
        \App\Models\JmvDailyProductionLog::STATUS_FOR_CORRECTION => 'bg-rose-100 text-rose-800',
        \App\Models\JmvDailyProductionLog::STATUS_COMPLETED => 'bg-emerald-100 text-emerald-800',
    ];
@endphp
<span class="inline-flex rounded-full px-3 py-1 text-xs font-black {{ $tones[$status] ?? 'bg-slate-100 text-slate-700' }}">
    {{ \App\Models\JmvDailyProductionLog::STATUSES[$status] ?? str($status)->headline() }}
</span>
