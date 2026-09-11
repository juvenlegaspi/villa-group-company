<article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="mb-1 text-lg font-black text-slate-900">Corrective action close-out</h2>
            <p class="mb-0 text-sm text-slate-500">Complete each step in order before submitting for verification.</p>
        </div>
        <span class="rounded-full bg-villa-50 px-3 py-1.5 text-sm font-black text-villa-700">{{ (int) $report->progress_percent }}% progress</span>
    </div>

    <div class="grid gap-4 p-5 lg:grid-cols-2">
        <section class="rounded-2xl border {{ (int)$report->progress_percent >= 100 ? 'border-emerald-200 bg-emerald-50/50' : 'border-blue-200 bg-blue-50/50' }} p-4">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-blue-700 text-sm font-black text-white">1</span><div><h3 class="mb-0 text-sm font-black text-slate-900">Update progress</h3><p class="mb-0 text-xs text-slate-500">Record completed work until it reaches 100%.</p></div></div>
                <span class="rounded-full px-2 py-1 text-[.68rem] font-extrabold {{ (int)$report->progress_percent >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">{{ (int)$report->progress_percent >= 100 ? 'COMPLETE' : (int)$report->progress_percent.'%' }}</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-white"><div class="h-full bg-gradient-to-r from-blue-600 to-cyan-500" style="width:{{ (int)$report->progress_percent }}%"></div></div>
            @if($report->progress_notes)<p class="mb-0 mt-3 text-xs text-slate-600"><b>Latest:</b> {{ $report->progress_notes }}</p>@endif
            @if($report->status==='Ongoing' && $canPerformAction && (int)$report->progress_percent < 100)
                <form method="POST" action="{{ route('tech-defects.update',$report) }}" class="mt-3 grid gap-2 sm:grid-cols-[110px_1fr_auto]">@csrf @method('PUT')
                    <input type="number" min="{{ max(1,(int)$report->progress_percent) }}" max="100" name="progress_percent" value="{{ max(1,(int)$report->progress_percent) }}" class="{{ $input }}" required aria-label="Progress percentage">
                    <input name="progress_notes" maxlength="3000" class="{{ $input }}" required placeholder="Work completed/current issue">
                    <button name="action" value="update_progress" class="rounded-xl bg-blue-700 px-3 text-sm font-bold text-white">Update</button>
                </form>
            @elseif($report->status==='Ongoing' && (int)$report->progress_percent < 100)
                <div class="mt-3 rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm text-blue-900"><b>Awaiting progress update from the assigned Technical PIC</b><p class="mb-0 mt-1 text-xs text-blue-700">{{ $report->assignee ? trim($report->assignee->name.' '.$report->assignee->lastname) : 'No Technical PIC is assigned' }} is responsible for continuing the corrective work.</p></div>
            @endif
            @if($report->status==='Ongoing' && (int)$report->progress_percent < 100)
                @if($hasPendingThirdPartySupport)
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><b>Third-party work in progress</b><p class="mb-0 mt-1 text-xs text-amber-700">Update the work progress while the provider completes the assigned support.</p></div>
                @elseif($canRequestSupport)
                    <button type="button" data-bs-toggle="modal" data-bs-target="#supportForm" class="mt-3 rounded-xl bg-amber-100 px-4 py-2.5 text-sm font-bold text-amber-900">Assign third party</button>
                @endif
            @endif
        </section>

        <section class="rounded-2xl border {{ $closeoutComplete ? 'border-emerald-200 bg-emerald-50/50' : 'border-slate-200 bg-slate-50' }} p-4">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-villa-700 text-sm font-black text-white">2</span><div><h3 class="mb-0 text-sm font-black text-slate-900">Repair close-out</h3><p class="mb-0 text-xs text-slate-500">Complete the root cause, corrective action and required prevention.</p></div></div>
                <span class="rounded-full px-2 py-1 text-[.68rem] font-extrabold {{ $closeoutComplete ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">{{ $closeoutComplete ? 'COMPLETE' : ((int)$report->progress_percent < 100 ? 'LOCKED' : 'PENDING') }}</span>
            </div>
            @if((int)$report->progress_percent < 100)
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"><b>Complete corrective work first</b><p class="mb-0 mt-1 text-xs text-slate-500">Repair close-out becomes available after progress reaches 100%.</p></div>
            @elseif($hasPendingThirdPartySupport)
                <div class="rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm text-amber-900"><b>Third-party work in progress</b><p class="mb-0 mt-1 text-xs text-amber-700">Complete the active support work and record its actual cost before repair close-out.</p></div>
            @else
                @if($report->status==='Ongoing' && $canCloseout)<a href="{{ route('tech-defects.repair-closeout',$report) }}" class="inline-flex rounded-xl bg-villa-700 px-4 py-2.5 text-sm font-bold text-white no-underline">{{ $closeoutComplete ? 'Review repair details' : 'Complete repair details' }}</a>@endif
            @endif
        </section>

        <section class="rounded-2xl border {{ $repairPhotos->isNotEmpty() ? 'border-emerald-200 bg-emerald-50/50' : 'border-amber-200 bg-amber-50/50' }} p-4">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-amber-600 text-sm font-black text-white">3</span><div><h3 class="mb-0 text-sm font-black text-slate-900">Upload repair evidence</h3><p class="mb-0 text-xs text-slate-500">Add a clear photo proving the reported defect is repaired.</p></div></div>
                <span class="rounded-full px-2 py-1 text-[.68rem] font-extrabold {{ $repairPhotos->isNotEmpty() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $repairPhotos->isNotEmpty() ? 'ATTACHED' : 'REQUIRED' }}</span>
            </div>
            @if($report->status==='Ongoing' && $canUpload)
                @if((int)$report->progress_percent >= 100)<button type="button" data-bs-toggle="modal" data-bs-target="#repairEvidenceModal" class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-extrabold text-white">{{ $repairPhotos->isEmpty() ? 'Upload repair photo' : 'Add evidence' }}</button>
                @else<button type="button" disabled class="cursor-not-allowed rounded-xl bg-slate-200 px-4 py-2.5 text-sm font-bold text-slate-500">Upload repair photo</button><p class="mb-0 mt-2 text-xs font-semibold text-amber-700">Complete progress to 100% first.</p>@endif
            @endif
            @if($repairPhotos->isNotEmpty() || $supportingDocuments->isNotEmpty())<div class="mt-3 flex flex-wrap gap-2">@foreach($repairPhotos as $file)<a href="{{ route('tech-defects.attachments.show',[$report,$file]) }}" class="max-w-full truncate rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-bold text-emerald-800 no-underline">Photo · {{ $file->original_name }}</a>@endforeach @foreach($supportingDocuments as $file)<a href="{{ route('tech-defects.attachments.show',[$report,$file]) }}" class="max-w-full truncate rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 no-underline">{{ $file->category }} · {{ $file->original_name }}</a>@endforeach</div>@endif
        </section>

        <section class="rounded-2xl border {{ $readyForVerification ? 'border-violet-200 bg-violet-50/50' : 'border-slate-200 bg-slate-50' }} p-4">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-violet-700 text-sm font-black text-white">4</span><div><h3 class="mb-0 text-sm font-black text-slate-900">Submit for verification</h3><p class="mb-0 text-xs text-slate-500">Send the completed repair for independent review.</p></div></div>
                <span class="rounded-full px-2 py-1 text-[.68rem] font-extrabold {{ in_array($report->status,['For Verification','Closed'],true) ? 'bg-emerald-100 text-emerald-800' : ($readyForVerification ? 'bg-violet-100 text-violet-800' : 'bg-slate-200 text-slate-700') }}">{{ in_array($report->status,['For Verification','Closed'],true) ? 'SUBMITTED' : ($readyForVerification ? 'READY' : 'LOCKED') }}</span>
            </div>
            @if($report->status==='Ongoing' && $canSubmitVerification)
                @if($readyForVerification)<form method="POST" action="{{ route('tech-defects.update',$report) }}" onsubmit="return confirm('Submit completed action for verification?')">@csrf @method('PUT')<button name="action" value="submit_completion" class="rounded-xl bg-violet-700 px-4 py-2.5 text-sm font-extrabold text-white">Submit for verification</button></form>
                @else<ul class="mb-0 list-disc space-y-1 pl-5 text-xs font-semibold text-slate-600">@if((int)$report->progress_percent < 100)<li>Update progress to 100%.</li>@endif @if(!$closeoutComplete)<li>Complete the repair close-out details.</li>@endif @if($repairPhotos->isEmpty())<li>Upload a repair completion photo.</li>@endif @if(!$thirdPartyComplete)<li>Complete all third-party support work.</li>@endif</ul>@endif
            @endif
        </section>
    </div>

    @if($report->status==='Ongoing' && $canUpload && (int)$report->progress_percent >= 100)
        <div id="repairEvidenceModal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="repairEvidenceTitle"><div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" enctype="multipart/form-data" action="{{ route('tech-defects.attachments.store',$report) }}" class="modal-content">@csrf<div class="modal-header"><div><h2 id="repairEvidenceTitle" class="modal-title">Upload repair evidence</h2><p class="mb-0 mt-1 text-sm text-slate-500">The repair completion photo is required for verification.</p></div><button type="button" data-bs-dismiss="modal" aria-label="Close" class="btn-close ml-auto"></button></div><div class="modal-body"><div class="grid gap-4 sm:grid-cols-2"><label><b class="mb-2 block text-sm">Evidence type</b><select name="category" class="{{ $input }}"><option value="After Repair">Repair completion photo</option><option value="Inspection">Inspection document</option><option value="Service Report">Service report</option><option value="Quotation">Quotation</option><option value="Other">Other supporting file</option></select></label><label><b class="mb-2 block text-sm">Select file <span class="text-rose-600">*</span></b><input type="file" name="attachment" class="block h-11 w-full rounded-xl border border-slate-300 bg-white p-2 text-sm" required></label></div><p class="mb-0 mt-3 text-xs text-slate-500">Repair photos: JPG, PNG or WEBP. Supporting documents may also be PDF, DOC, DOCX, XLS or XLSX. Maximum 10 MB.</p></div><div class="modal-footer"><button type="button" data-bs-dismiss="modal" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">Cancel</button><button class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Upload evidence</button></div></form></div></div>
    @endif
</article>
