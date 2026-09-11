@extends('layouts.app')

@section('title', 'New Technical Defect | Villa Shipping Lines')

@section('content')
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-slate-50 via-white to-villa-50 px-4 py-6 sm:px-7 lg:px-12"><div class="mx-auto max-w-5xl">
    <header class="mb-6 flex items-start justify-between gap-4"><div><p class="mb-1 text-xs font-extrabold uppercase tracking-[.16em] text-villa-600">Technical &amp; Defect</p><h1 class="m-0 text-2xl font-black text-slate-900 sm:text-3xl">New defect report</h1><p class="mt-1 text-sm text-slate-500">Record a vessel issue for controlled assessment and repair tracking.</p></div><a href="{{ route('tech-defects.index') }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600 no-underline shadow-sm hover:border-villa-500 hover:text-villa-800"><i class="bi bi-arrow-left"></i><span class="hidden sm:inline">Back</span></a></header>
    @if($errors->any())<div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p class="mb-2 font-extrabold">Please correct the following:</p><ul class="mb-0 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($vessels->isEmpty())<div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-900"><h2 class="mb-1 font-black">No vessel is assigned to your account</h2><p class="mb-0 text-sm">Contact a manager before creating a technical defect report.</p></div>@else
    <form method="POST" action="{{ route('tech-defects.store') }}" enctype="multipart/form-data" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">@csrf
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-7"><div><h2 class="mb-0 text-lg font-black text-slate-900">Report details</h2><p class="mb-0 mt-1 text-sm text-slate-500">The Technical Team assigns the qualified PIC after assessment.</p></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-extrabold text-slate-700">Initial status: New Report</span></div>
        <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
            @include('shipping.tech_defects.partials.form-fields', ['report' => null])
            <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-2 block text-sm font-extrabold text-slate-700">Completed checklist / Initial evidence <b class="text-rose-600">*</b></span>
                    <span class="flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-sky-200 bg-sky-50/70 px-5 py-6 text-center transition hover:border-sky-400 hover:bg-sky-50">
                        <i class="bi bi-file-earmark-check mb-2 text-3xl text-sky-700"></i>
                        <strong class="text-sm text-slate-800">Choose checklist document</strong>
                        <span id="checklistFileName" class="mt-1 text-xs text-slate-500">PDF, Word, Excel, or image — maximum 10 MB</span>
                        <input id="checklistDocument" type="file" name="checklist_document" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx" class="sr-only" required>
                    </span>
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-extrabold text-slate-700">Affected defect photo <b class="text-rose-600">*</b></span>
                    <span class="flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-amber-200 bg-amber-50/70 px-5 py-6 text-center transition hover:border-amber-400 hover:bg-amber-50">
                        <i class="bi bi-camera mb-2 text-3xl text-amber-700"></i>
                        <strong class="text-sm text-slate-800">Choose defect photo</strong>
                        <span id="defectPhotoFileName" class="mt-1 text-xs text-slate-500">JPG, PNG, or WEBP — maximum 10 MB</span>
                        <input id="defectPhoto" type="file" name="defect_photo" accept="image/jpeg,image/png,image/webp" class="sr-only" required>
                    </span>
                </label>
            </div>
        </div>
        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-7"><a href="{{ route('tech-defects.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-bold text-slate-600 no-underline hover:bg-slate-100">Cancel</a><button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-villa-700 px-5 text-sm font-extrabold text-white shadow-lg shadow-villa-700/20 hover:bg-villa-800 focus:outline-none focus:ring-4 focus:ring-villa-100"><i class="bi bi-shield-check"></i>Save report</button></div>
    </form>@endif
</div></section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    [
        ['checklistDocument', 'checklistFileName', 'PDF, Word, Excel, or image — maximum 10 MB'],
        ['defectPhoto', 'defectPhotoFileName', 'JPG, PNG, or WEBP — maximum 10 MB'],
    ].forEach(([inputId, nameId, fallback]) => {
        const input = document.getElementById(inputId);
        const name = document.getElementById(nameId);
        input?.addEventListener('change', () => { name.textContent = input.files?.[0]?.name || fallback; });
    });
});
</script>
@endpush
