@php
    $currentSubs = $certificate->subCertificates->where('workflow_status', 'active');
    $overdueSubs = $currentSubs->filter(fn ($sub) => $sub->expiry_date?->lt(today()));
    $soonSubs = $currentSubs->filter(fn ($sub) => $sub->expiry_date?->betweenIncluded(today(), today()->addDays(30)));
@endphp
<details class="rounded-xl border border-slate-200 bg-slate-50" @if((string)request('expand') === (string)$certificate->id) open @endif>
    <summary class="cursor-pointer px-4 py-3 text-xs font-bold text-villa-800">
        Sub-certificates / Annual audits ({{ $certificate->subCertificates->count() }})
        @if($certificate->workflow_status === 'active')
            @if($overdueSubs->isNotEmpty())<span class="ml-3 rounded-full bg-rose-100 px-2 py-1 text-rose-700">{{ $overdueSubs->count() }} overdue</span>
            @elseif($soonSubs->isNotEmpty())<span class="ml-3 rounded-full bg-amber-100 px-2 py-1 text-amber-800">{{ $soonSubs->count() }} due within 30 days</span>@endif
        @endif
    </summary>
    <div class="border-t border-slate-200 p-4">
        <div class="mb-3 flex items-center justify-between gap-3"><p class="text-xs text-slate-500">Annual requirements and document history for this main certificate.</p>
            @if($canManage && $certificate->workflow_status === 'active')<button type="button" class="shrink-0 rounded-lg bg-villa-700 px-3 py-2 text-xs font-bold text-white" data-sub-certificate-open data-parent-id="{{ $certificate->id }}" data-parent-name="{{ $certificate->certificate_name }}" data-store-url="{{ route('vessel-certificates.sub.store', $certificate) }}">+ Add sub-certificate</button>@endif
        </div>
        @forelse($certificate->subCertificates as $sub)
            @php
                $historical = $sub->workflow_status === 'superseded' || $certificate->workflow_status !== 'active';
                $subStatus = $historical ? 'History' : ($sub->expiry_date->lt(today()) ? 'Overdue' : ($sub->expiry_date->lte(today()->addDays(30)) ? 'Expiring' : 'Valid'));
            @endphp
            <article class="mb-2 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3">
                <div><p class="text-sm font-bold text-slate-900">{{ $sub->certificate_name }}</p>
                    <p class="mt-1 text-xs text-slate-500">Issued {{ $sub->issue_date?->format('M d, Y') }} · Expiry / Next due {{ $sub->expiry_date?->format('M d, Y') }} @if($sub->certificate_number) · {{ $sub->certificate_number }}@endif</p>
                    @if($sub->remarks)<p class="mt-1 text-xs text-slate-500">{{ $sub->remarks }}</p>@endif
                </div>
                <div class="flex items-center gap-3 text-xs font-bold">
                    <span class="rounded-full px-2 py-1 {{ $subStatus === 'Overdue' ? 'bg-rose-100 text-rose-700' : ($subStatus === 'Expiring' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">{{ $subStatus }}</span>
                    @if($sub->document)<a class="text-villa-700" href="{{ route('vessel-certificates.document', $sub) }}">Download</a>@endif
                    @if($canManage && ! $historical)
                        <button type="button" class="text-villa-700" data-sub-certificate-open data-mode="edit" data-parent-id="{{ $certificate->id }}" data-parent-name="{{ $certificate->certificate_name }}" data-load-url="{{ route('vessel-certificates.edit', $sub) }}" data-store-url="{{ route('vessel-certificates.update', $sub) }}">Edit</button>
                        <button type="button" class="text-villa-700" data-sub-certificate-open data-mode="renew" data-parent-id="{{ $certificate->id }}" data-parent-name="{{ $certificate->certificate_name }}" data-load-url="{{ route('vessel-certificates.renew', $sub) }}" data-store-url="{{ route('vessel-certificates.renew.store', $sub) }}">Renew</button>
                    @endif
                </div>
            </article>
        @empty<p class="text-xs text-slate-500">No sub-certificates added. Add only when required for this main certificate.</p>@endforelse
    </div>
</details>
