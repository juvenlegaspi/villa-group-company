@extends('layouts.app')

@section('title', $supplier->name.' | Supplier')

@section('content')
<style>
    #editSupplierProfileModal .supplier-edit-grid {
        display: grid !important;
        grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
        gap: 1rem !important;
        width: 100% !important;
    }
    #editSupplierProfileModal .supplier-edit-grid > * { min-width: 0 !important; width: 100% !important; max-width: 100% !important; }
    #editSupplierProfileModal .supplier-edit-grid > .col-12 { grid-column: span 12 / span 12 !important; }
    #editSupplierProfileModal .supplier-edit-grid > .col-md-8 { grid-column: span 8 / span 8 !important; }
    #editSupplierProfileModal .supplier-edit-grid > .col-md-6 { grid-column: span 6 / span 6 !important; }
    #editSupplierProfileModal .supplier-edit-grid > .col-md-4 { grid-column: span 4 / span 4 !important; }
    #editSupplierProfileModal input,
    #editSupplierProfileModal select,
    #editSupplierProfileModal textarea { width: 100% !important; max-width: 100% !important; }
    @media (max-width: 767px) {
        #editSupplierProfileModal .supplier-edit-grid > * { grid-column: 1 / -1 !important; }
    }
</style>
<main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:py-8">
    <a href="{{ route('suppliers.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-bold text-villa-700 no-underline hover:text-villa-900"><i class="bi bi-arrow-left"></i> Back to suppliers</a>
    <header class="mb-5 rounded-3xl bg-gradient-to-r from-villa-900 to-villa-700 px-6 py-6 text-white shadow-xl sm:px-8">
        <p class="mb-1 text-xs font-extrabold uppercase tracking-[.18em] text-amber-200">Supplier profile</p>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h1 class="mb-1 text-2xl font-black sm:text-3xl">{{ $supplier->name }}</h1><p class="mb-0 text-sm text-blue-100">SUP-{{ str_pad($supplier->id, 5, '0', STR_PAD_LEFT) }} &middot; TIN {{ $supplier->tin ?: 'Requires review' }} &middot; {{ $supplier->business_type }}</p></div><div class="flex flex-wrap items-center gap-2"><span class="w-fit rounded-full px-3 py-1.5 text-xs font-black {{ $supplier->status ? 'bg-emerald-400/20 text-emerald-100 ring-1 ring-emerald-300/40' : 'bg-slate-400/20 text-slate-200 ring-1 ring-slate-300/30' }}">{{ $supplier->status ? 'Active' : 'Inactive' }}</span>@if(auth()->user()->isSystemAdministrator() || auth()->user()->hasPermission('yatira.suppliers.manage'))<button type="button" class="rounded-xl border border-white/30 bg-white px-4 py-2 text-xs font-black text-villa-900 shadow" data-bs-toggle="modal" data-bs-target="#editSupplierProfileModal"><i class="bi bi-pencil-square me-1"></i>Edit Supplier</button>@endif</div></div>
    </header>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="mb-5 text-lg font-black text-slate-900">Supplier information</h2><dl class="grid gap-5 sm:grid-cols-2">
            @foreach([['Products', $supplier->products], ['Address', $supplier->address], ['Contact person', $supplier->contact_person], ['Email', $supplier->email], ['Telephone', $supplier->telephone], ['Mobile', $supplier->mobile], ['Tax type', $supplier->tax_type], ['Lead time', is_null($supplier->lead_time) ? null : $supplier->lead_time.' days'], ['Credit term', is_null($supplier->credit_term) ? null : $supplier->credit_term.' days'], ['Advance limit', is_null($supplier->limit_advances) ? null : number_format($supplier->limit_advances, 2)]] as [$label,$value])
                <div><dt class="text-xs font-extrabold uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="mb-0 mt-1 break-words text-sm font-bold text-slate-800">{{ filled($value) ? $value : 'Not provided' }}</dd></div>
            @endforeach
        </dl><div class="mt-6 border-t border-slate-100 pt-4 text-xs text-slate-500">Created by {{ $supplier->user?->name ?? 'System' }} on {{ optional($supplier->created_at)->format('M d, Y h:i A') }} &middot; Last updated by {{ $supplier->updater?->name ?? $supplier->user?->name ?? 'System' }}</div></section>

        <details open class="h-fit rounded-3xl border border-slate-200 bg-white shadow-sm"><summary class="cursor-pointer list-none px-5 py-4 text-sm font-black text-slate-900">Audit trail <span class="float-right rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ $supplier->audits->count() }}</span></summary><div class="max-h-[520px] space-y-4 overflow-y-auto border-t border-slate-100 p-5">@forelse($supplier->audits as $audit)<article class="border-l-2 border-amber-300 pl-3"><p class="mb-0 text-xs font-black capitalize text-slate-800">{{ str_replace('_', ' ', $audit->action) }}</p><p class="mb-0 mt-1 text-[11px] text-slate-500">{{ $audit->user?->name ?? 'System' }} &middot; {{ optional($audit->created_at)->format('M d, Y h:i A') }}</p>@if($audit->changes)<details class="mt-2"><summary class="cursor-pointer text-[11px] font-bold text-villa-700">View recorded changes</summary><pre class="mt-2 max-w-full overflow-x-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-2 text-[10px] text-slate-600">{{ json_encode($audit->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>@endif</article>@empty<p class="mb-0 text-sm text-slate-500">No audit activity recorded.</p>@endforelse</div></details>
    </div>
</main>

@if(auth()->user()->isSystemAdministrator() || auth()->user()->hasPermission('yatira.suppliers.manage'))
<div class="modal fade" id="editSupplierProfileModal" tabindex="-1" aria-labelledby="editSupplierProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title" id="editSupplierProfileModalLabel">Update Supplier</h5><small class="text-muted">SUP-{{ str_pad($supplier->id, 5, '0', STR_PAD_LEFT) }}</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
                @csrf @method('PUT')
                <input type="hidden" name="_form_context" value="edit_supplier_profile">
                <div class="modal-body">
                    @if($errors->any())<div class="alert alert-danger"><strong>Supplier was not updated.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <h6 class="border-bottom pb-2 mb-3">Supplier Information</h6>
                    <div class="supplier-edit-grid">
                        <div class="col-md-8"><label class="form-label">Supplier Name *</label><input class="form-control" name="name" value="{{ old('name', $supplier->name) }}" required maxlength="255"></div>
                        <div class="col-md-4"><label class="form-label">Status *</label><select class="form-select" name="status" required><option value="1" @selected((string)old('status', (int)$supplier->status)==='1')>Active</option><option value="0" @selected((string)old('status', (int)$supplier->status)==='0')>Inactive</option></select></div>
                        <div class="col-md-6"><label class="form-label">Business Type *</label><input class="form-control" name="business_type" value="{{ old('business_type', $supplier->business_type) }}" required maxlength="255"></div>
                        <div class="col-md-6"><label class="form-label">TIN *</label><input class="form-control" name="tin" value="{{ old('tin', $supplier->tin) }}" required maxlength="255"></div>
                        <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address" value="{{ old('address', $supplier->address) }}"></div>
                        <div class="col-12"><label class="form-label">Products or Services *</label><textarea class="form-control" name="products" rows="2" required>{{ old('products', $supplier->products) }}</textarea></div>
                        <div class="col-md-4"><label class="form-label">Tax Type</label><input class="form-control" name="tax_type" value="{{ old('tax_type', $supplier->tax_type) }}" maxlength="255"></div>
                        <div class="col-md-4"><label class="form-label">Lead Time (days)</label><input type="number" min="0" class="form-control" name="lead_time" value="{{ old('lead_time', $supplier->lead_time) }}"></div>
                        <div class="col-md-4"><label class="form-label">Credit Term (days)</label><input type="number" min="0" class="form-control" name="credit_term" value="{{ old('credit_term', $supplier->credit_term) }}"></div>
                        <div class="col-md-4"><label class="form-label">Advance Limit</label><input type="number" min="0" step="0.01" class="form-control" name="limit_advances" value="{{ old('limit_advances', $supplier->limit_advances) }}"></div>
                    </div>
                    <h6 class="border-bottom pb-2 mt-4 mb-3">Contact Information</h6>
                    <div class="supplier-edit-grid">
                        <div class="col-md-6"><label class="form-label">Contact Person *</label><input class="form-control" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" required maxlength="255"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="{{ old('email', $supplier->email) }}" maxlength="255"></div>
                        <div class="col-md-6"><label class="form-label">Telephone</label><input class="form-control" name="telephone" value="{{ old('telephone', $supplier->telephone) }}" maxlength="50"></div>
                        <div class="col-md-6"><label class="form-label">Mobile</label><input class="form-control" name="mobile" value="{{ old('mobile', $supplier->mobile) }}" maxlength="50"></div>
                    </div>
                    <p class="mb-0 mt-2 small text-muted">Provide at least one contact detail: telephone, mobile number, or email.</p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>
@if($errors->any())
<script>document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('editSupplierProfileModal')).show());</script>
@endif
@endif
@endsection
