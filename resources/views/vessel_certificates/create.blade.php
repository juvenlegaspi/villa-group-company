@extends('layouts.app')

@section('content')
@php($isRenewal = filled($renewal))
<main class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 lg:py-8">
    <a href="{{ route('vessel.certificates.show', $vessel) }}" class="mb-4 inline-flex text-sm font-bold text-villa-700 no-underline hover:text-villa-900">← Back to certificate register</a>
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
        <header class="border-b border-slate-100 bg-gradient-to-r from-villa-900 to-villa-700 px-6 py-6 text-white sm:px-8">
            <p class="mb-1 text-xs font-bold uppercase tracking-[.2em] text-blue-200">{{ $isRenewal ? 'Certificate renewal' : 'New compliance record' }}</p>
            <h1 class="mb-1 text-2xl font-extrabold">{{ $isRenewal ? 'Renew certificate' : 'Add vessel certificate' }}</h1>
            <p class="mb-0 text-sm text-blue-100">{{ $vessel->vessel_name }}@if($isRenewal) · Previous record #{{ $renewal->id }} will be retained @endif</p>
        </header>

        <form action="{{ $isRenewal ? route('vessel-certificates.renew.store', $renewal) : route('vessel-certificates.store') }}" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8">
            @csrf
            <input type="hidden" name="vessel_id" value="{{ $vessel->id }}">
            @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">Please review the highlighted fields.</div>@endif
            @if($isRenewal)
                @php($remainingDays = today()->diffInDays($renewal->expiry_date, false))
                <div class="mb-6 grid gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div>
                        <p class="mb-1 text-xs font-extrabold uppercase tracking-wide text-amber-800">Current certificate expiry</p>
                        <p class="mb-1 text-lg font-extrabold text-slate-950">{{ optional($renewal->expiry_date)->format('F d, Y') ?: 'No expiry date recorded' }}</p>
                        <p class="mb-0 text-xs font-semibold text-amber-800">
                            @if($remainingDays < 0) {{ abs($remainingDays) }} {{ Str::plural('day', abs($remainingDays)) }} overdue
                            @elseif($remainingDays === 0) Expires today
                            @else {{ $remainingDays }} {{ Str::plural('day', $remainingDays) }} remaining
                            @endif
                        </p>
                    </div>
                    <span class="w-fit rounded-full bg-white px-3 py-1.5 text-xs font-bold text-amber-800 ring-1 ring-amber-200">Previous record #{{ $renewal->id }}</span>
                </div>
            @endif
            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2"><label class="mb-2 block text-sm font-bold text-slate-700">Certificate name <span class="text-rose-500">*</span></label><input name="certificate_name" value="{{ old('certificate_name', $renewal?->certificate_name) }}" required class="min-h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 outline-none focus:border-villa-500 focus:bg-white focus:ring-4 focus:ring-villa-100" placeholder="e.g. Safety Management Certificate">@error('certificate_name')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-bold text-slate-700">Certificate number</label><input name="certificate_number" value="{{ old('certificate_number', $renewal?->certificate_number) }}" class="min-h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 outline-none focus:border-villa-500 focus:ring-4 focus:ring-villa-100" placeholder="Official reference number">@error('certificate_number')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-bold text-slate-700">Certificate type</label><input name="certificate_type" value="{{ old('certificate_type', $renewal?->certificate_type) }}" class="min-h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 outline-none focus:border-villa-500 focus:ring-4 focus:ring-villa-100" placeholder="Statutory, Class, Safety, etc.">@error('certificate_type')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="md:col-span-2"><label class="mb-2 block text-sm font-bold text-slate-700">Issuing authority</label><input name="issuing_authority" value="{{ old('issuing_authority', $renewal?->issuing_authority) }}" class="min-h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 outline-none focus:border-villa-500 focus:ring-4 focus:ring-villa-100" placeholder="Authority or classification society">@error('issuing_authority')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-bold text-slate-700">Issue date <span class="text-rose-500">*</span></label><input type="date" name="issue_date" value="{{ old('issue_date', $isRenewal ? $suggestedIssueDate?->format('Y-m-d') : null) }}" required class="min-h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 outline-none focus:border-villa-500 focus:bg-white focus:ring-4 focus:ring-villa-100"><p class="mb-0 mt-1 text-xs text-slate-500">@if($isRenewal) Suggested from the previous expiry; editable using the official document.@endif</p>@error('issue_date')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-bold text-slate-700">Expiry date <span class="text-rose-500">*</span></label><input type="date" name="expiry_date" value="{{ old('expiry_date', $isRenewal ? $suggestedExpiryDate?->format('Y-m-d') : null) }}" required class="min-h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 outline-none focus:border-villa-500 focus:bg-white focus:ring-4 focus:ring-villa-100"><p class="mb-0 mt-1 text-xs text-slate-500">@if($isRenewal) Suggested using the previous validity period; verify and edit as needed.@endif</p>@error('expiry_date')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="md:col-span-2"><label class="mb-2 block text-sm font-bold text-slate-700">Remarks <span class="font-normal text-slate-400">(optional)</span></label><textarea name="remarks" rows="3" maxlength="2000" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 outline-none focus:border-villa-500 focus:bg-white focus:ring-4 focus:ring-villa-100" placeholder="Issuing authority, restrictions, or compliance notes">{{ old('remarks', $isRenewal ? $renewal->remarks : '') }}</textarea>@error('remarks')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="md:col-span-2"><label class="mb-2 block text-sm font-bold text-slate-700">Certificate document <span class="text-rose-500">*</span></label><div class="rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-5"><input type="file" name="document" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-villa-700 file:px-4 file:py-2.5 file:font-bold file:text-white hover:file:bg-villa-800"><p class="mb-0 mt-2 text-xs text-slate-500">PDF, Word, Excel, JPG, or PNG · Maximum 5 MB. The original filename is preserved.</p></div>@error('document')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror</div>
            </div>
            <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">Saving activates the certificate immediately.@if($isRenewal) The previous certificate and its documents will remain available in renewal history.@endif</div>
            <footer class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end"><a href="{{ route('vessel.certificates.show', $vessel) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-700 no-underline hover:bg-slate-50">Cancel</a><button class="min-h-11 rounded-xl bg-villa-700 px-5 text-sm font-bold text-white shadow-md hover:bg-villa-800">{{ $isRenewal ? 'Renew and activate' : 'Save and activate' }}</button></footer>
        </form>
    </section>
</main>
@endsection
