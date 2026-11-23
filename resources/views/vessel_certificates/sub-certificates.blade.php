<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
    <table class="w-full border-collapse text-left text-xs" aria-label="Sub-certificates for {{ $certificate->certificate_name }}">
        <thead class="bg-slate-200 uppercase text-slate-700"><tr><th class="rounded-l-lg px-3 py-2">Sub certificate</th><th class="px-3 py-2">Issue date</th><th class="px-3 py-2">Expiry date</th><th class="px-3 py-2">Remaining</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Document</th><th class="rounded-r-lg px-3 py-2 text-right">Actions</th></tr></thead>
        <tbody class="divide-y divide-slate-100 bg-white">
        @foreach($certificate->subCertificates as $sub)
            @php
                $historical = $sub->workflow_status === 'superseded' || $certificate->workflow_status !== 'active';
                $subDays = $sub->expiry_date ? (int) today()->diffInDays($sub->expiry_date, false) : null;
                $subStatus = $historical ? 'History' : ($subDays === null ? 'No expiry' : ($subDays < 0 ? 'Expired' : ($subDays <= 30 ? 'Expiring' : 'Valid')));
                $subTone = match ($subStatus) { 'Expired' => 'bg-rose-50 text-rose-700', 'Expiring' => 'bg-amber-50 text-amber-800', 'Valid' => 'bg-emerald-50 text-emerald-700', default => 'bg-slate-100 text-slate-700' };
            @endphp
            <tr>
                <td class="px-3 py-3"><p class="mb-1 text-sm font-bold text-slate-900">{{ $sub->certificate_name }}</p><small class="text-slate-500">Updated {{ $sub->updated_at?->format('M d, Y') }}@if($sub->certificate_number) &middot; {{ $sub->certificate_number }}@endif</small>@if($sub->remarks)<p class="mb-0 mt-1 text-slate-500">{{ $sub->remarks }}</p>@endif</td>
                <td class="px-3 py-3 text-slate-600">{{ $sub->issue_date?->format('M d, Y') ?? '—' }}</td>
                <td class="px-3 py-3 font-semibold text-slate-700">{{ $sub->expiry_date?->format('M d, Y') ?? '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $subDays === null ? '—' : ($subDays < 0 ? abs($subDays).' days overdue' : $subDays.' days') }}</td>
                <td class="px-3 py-3"><span class="whitespace-nowrap rounded-full px-2 py-1 font-bold {{ $subTone }}">{{ $subStatus }}</span></td>
                <td class="px-3 py-3">@if($sub->document)<a class="font-bold text-villa-700" href="{{ route('vessel-certificates.document', $sub) }}">Download</a>@else<span class="text-slate-400">No file</span>@endif</td>
                <td class="px-3 py-3"><div class="flex justify-end gap-2">@if($canManage && ! $historical)
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 font-bold text-slate-700" data-sub-certificate-open data-mode="edit" data-parent-id="{{ $certificate->id }}" data-parent-name="{{ $certificate->certificate_name }}" data-load-url="{{ route('vessel-certificates.edit', $sub) }}" data-store-url="{{ route('vessel-certificates.update', $sub) }}">Edit</button>
                    <button type="button" class="rounded-lg bg-villa-700 px-3 py-2 font-bold text-white" data-sub-certificate-open data-mode="renew" data-parent-id="{{ $certificate->id }}" data-parent-name="{{ $certificate->certificate_name }}" data-load-url="{{ route('vessel-certificates.renew', $sub) }}" data-store-url="{{ route('vessel-certificates.renew.store', $sub) }}">Renew</button>
                @endif</div></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>