@extends('layouts.app')
@section('title', 'PMS | '.$vessel->vessel_name)
@push('styles')
<style>
.pms-workspace{background:linear-gradient(160deg,#f8fafc,#eef3f9)}
.pms-banner{padding:28px 30px;border-radius:22px;background:linear-gradient(115deg,#142e53,#244d7d);color:#fff;box-shadow:0 12px 30px #17345f18;border:1px solid #ffffff22}
.pms-eyebrow{font-size:10px;letter-spacing:.16em;font-weight:800;color:#bcd3ef}.pms-eyebrow span{margin:0 9px;color:#e6b55e}
.pms-banner-description{font-size:13px;color:#cbdcf0}.pms-add-button{background:#fff;color:#17345f;border:1px solid #fff;border-radius:12px;padding:11px 24px;font-size:13px;font-weight:800;box-shadow:0 4px 12px #10244030}.pms-add-button:hover{background:#eef5ff;color:#17345f}
.pms-register{overflow:hidden;box-shadow:0 6px 24px #17345f07}.pms-register-heading{display:flex;align-items:center;gap:12px;padding:21px 24px;border-bottom:1px solid #e8edf4}.pms-register-heading h2{margin:0;font-size:15px;font-weight:800;color:#17345f}.pms-register-heading p{margin:4px 0 0;font-size:12px;color:#7b899e}.pms-register-icon,.pms-empty-icon{display:grid;place-items:center;background:#edf3fb;color:#315b90;border-radius:12px;width:42px;height:42px;font-size:20px;flex-shrink:0}.pms-total{margin-left:auto;white-space:nowrap;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:20px;padding:5px 11px;font-size:11px;font-weight:800;color:#52647d}
.pms-table{min-width:720px}.pms-table>thead>tr>th{background:#f8fafc;border-bottom:1px solid #e5ebf3;color:#738299;font-size:10px;letter-spacing:.08em;text-transform:uppercase;font-weight:800;padding-top:15px!important;padding-bottom:15px!important}.pms-table>tbody>tr>td{border-color:#eef2f6;font-size:13px;padding-top:19px!important;padding-bottom:19px!important}.pms-table>tbody>tr:hover>td{background:#f8fbff}.pms-table td:nth-child(3){max-width:360px;overflow-wrap:anywhere}.pms-table td:nth-child(2){min-width:190px}.pms-table td:nth-child(4){text-align:center;color:#315b90;font-weight:800}.pms-table th:nth-child(4){text-align:center}.pms-edit-button{border:1px solid #dde6f2;background:#fff;border-radius:9px;font-size:12px;font-weight:700;padding:7px 12px;white-space:nowrap}.pms-empty-icon{margin:auto;width:56px;height:56px;font-size:25px}.pms-register-footer{display:flex;justify-content:space-between;gap:12px;background:#fbfcfe;border-top:1px solid #e8edf4;padding:15px 24px;font-size:11px;color:#7b899e}
#addPmsHeader .modal-content{overflow:hidden}#addPmsHeader .modal-header{background:#f5f8fc;border-bottom:1px solid #e5ebf3;padding:22px 24px!important}#addPmsHeader .modal-title{color:#17345f;font-size:18px!important}#addPmsHeader .modal-body{padding:26px!important}#addPmsHeader .form-label{font-size:12px;color:#41546e}#addPmsHeader .form-control{border-color:#dce4ef;border-radius:10px;padding:12px 14px;font-size:13px}#addPmsHeader .form-control:focus{border-color:#6188b8;box-shadow:0 0 0 3px #315b9012}#addPmsHeader .modal-footer{background:#fafbfd;padding:16px 24px;border-top:1px solid #e8edf4}#addPmsHeader .modal-footer .btn{border-radius:10px;padding:9px 19px;font-size:13px;font-weight:700}
@media(max-width:575px){.pms-banner{padding:22px}.pms-register-heading{padding:18px 16px;flex-wrap:wrap}.pms-register-heading p{font-size:11px}.pms-register-footer{padding:14px 16px}.pms-total{font-size:10px}.pms-add-button{width:100%}}
</style>
@endpush
@section('content')
<section class="pms-workspace min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-6 sm:px-7">
<div class="mx-auto max-w-7xl">
<a href="{{ route('shipping.pms.index') }}" class="text-sm font-bold text-villa-700"><i class="bi bi-arrow-left"></i> Back to vessels</a>
<div class="pms-banner my-5 flex flex-wrap items-end justify-between gap-4">
<div><p class="pms-eyebrow mb-2">Operations <span aria-hidden="true">/</span> Planned Maintenance</p><h1 class="mb-2 text-2xl font-extrabold sm:text-3xl"><i class="bi bi-ship me-2" aria-hidden="true"></i>{{ $vessel->vessel_name }}</h1><p class="pms-banner-description mb-0">Equipment groups and maintenance details for this vessel.</p></div>
<button type="button" class="btn pms-add-button" data-bs-toggle="modal" data-bs-target="#addPmsHeader"><i class="bi bi-plus-lg me-2"></i> Add</button>
</div>
@include('shipping.pms.partials.feedback')
<div class="pms-register rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="pms-register-heading"><span class="pms-register-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span><div><h2>Equipment / Machine List</h2><p>Click an ID to open its equipment details.</p></div><span class="pms-total">{{ number_format($headers->total()) }} total</span></div>
<div class="overflow-x-auto">
<table class="pms-table table mb-0 align-middle">
<thead class="bg-slate-100"><tr><th class="px-4 py-3">ID</th><th class="px-4 py-3">Equipment / Machine Name</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Details</th><th class="px-4 py-3">Action</th></tr></thead>
<tbody>
@forelse($headers as $header)
<tr>
<td class="px-4 py-3"><a class="inline-flex whitespace-nowrap rounded-lg bg-villa-50 px-3 py-2 text-xs font-extrabold text-villa-800 no-underline hover:bg-villa-100" href="{{ route('shipping.pms.headers.show', [$vessel, $header]) }}" aria-label="Open {{ $header->name }} details">PMS-{{ str_pad($header->id, 4, '0', STR_PAD_LEFT) }}</a></td>
<td class="px-4 py-3 font-bold">{{ $header->name }}</td><td class="px-4 py-3 text-sm text-slate-500"><span class="whitespace-pre-line">{{ $header->description ?: '—' }}</span></td><td class="px-4 py-3">{{ $header->details_count }}</td>
<td class="px-4 py-3"><button type="button" class="btn btn-sm btn-outline-primary pms-edit-button" data-bs-toggle="modal" data-bs-target="#addPmsHeader" data-edit-id="{{ $header->id }}" data-name="{{ $header->name }}" data-description="{{ $header->description }}" data-update-url="{{ route('shipping.pms.headers.update', [$vessel, $header]) }}"><i class="bi bi-pencil me-1"></i> Edit</button></td>
</tr>
@empty
<tr><td colspan="5" class="p-5 text-center text-slate-500"><span class="pms-empty-icon"><i class="bi bi-clipboard-plus" aria-hidden="true"></i></span><strong class="d-block mt-3 text-slate-800">Your equipment list starts here</strong><p class="mb-0 mt-2 small">Click Add to create the first equipment or machine group.</p></td></tr>
@endforelse
</tbody></table></div>
<div class="pms-register-footer"><span>Showing {{ $headers->firstItem() ?? 0 }}–{{ $headers->lastItem() ?? 0 }} of {{ $headers->total() }} groups</span><span>10 per page</span></div>
</div>
@if($headers->hasPages())
<div class="mt-6">{{ $headers->links() }}</div>
@endif
</div></section>
<div class="modal fade" id="addPmsHeader" tabindex="-1" aria-labelledby="addPmsHeaderTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<form method="POST" action="{{ route('shipping.pms.headers.store', $vessel) }}" class="modal-content rounded-4 border-0 shadow-lg">
@csrf
<input type="hidden" name="_method" id="header-method" value="POST">
<input type="hidden" name="editing_header" id="editing-header" value="">
<div class="modal-header px-4 py-3"><div><p class="mb-1 small text-secondary">PMS / {{ $vessel->vessel_name }}</p><h2 id="addPmsHeaderTitle" class="modal-title fs-5 fw-bold">Add Equipment / Machine</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body p-4">
<label for="header-name" class="form-label fw-bold">Equipment / Machine Name <span class="text-danger">*</span></label>
<input id="header-name" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="e.g. Life Saving Equipment" class="form-control @error('name') is-invalid @enderror">
@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
<label for="header-description" class="form-label fw-bold mt-4">Description <span class="fw-normal text-secondary">(optional)</span></label>
<textarea id="header-description" name="description" rows="4" maxlength="2000" placeholder="Briefly describe the equipment group, its purpose, or coverage." class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
<p class="mb-0 mt-2 small text-secondary">Add equipment items and maintenance details after saving this header.</p>
</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save</button></div>
</form></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('addPmsHeader');
    const form = modal.querySelector('form');
    const createUrl = form.action;
    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;
        const editing = button.dataset.editId || '';
        form.action = editing ? button.dataset.updateUrl : createUrl;
        document.getElementById('header-method').value = editing ? 'PUT' : 'POST';
        document.getElementById('editing-header').value = editing;
        document.getElementById('addPmsHeaderTitle').textContent = editing ? 'Edit Equipment / Machine' : 'Add Equipment / Machine';
        document.getElementById('header-name').value = editing ? button.dataset.name : '';
        document.getElementById('header-description').value = editing ? button.dataset.description : '';
        modal.querySelectorAll('.is-invalid').forEach(input => input.classList.remove('is-invalid'));
        modal.querySelectorAll('.invalid-feedback').forEach(message => message.hidden = true);
    });
    modal.addEventListener('shown.bs.modal', function () { document.getElementById('header-name').focus(); });
    @if($errors->any())
    const editingId = @json(old('editing_header'));
    const editButton = Array.from(document.querySelectorAll('[data-edit-id]')).find(button => button.dataset.editId === String(editingId));
    if (editButton) {
        form.action = editButton.dataset.updateUrl;
        document.getElementById('header-method').value = 'PUT';
        document.getElementById('editing-header').value = editingId;
        document.getElementById('addPmsHeaderTitle').textContent = 'Edit Equipment / Machine';
    }
    bootstrap.Modal.getOrCreateInstance(modal).show();
    @endif
});
</script>
@endpush
