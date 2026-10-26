@extends('layouts.app')
@section('title', $header->name.' | PMS')
@push('styles')
<style>
.pms-detail-page{background:linear-gradient(155deg,#f8fafc,#eef3f9)}
.pms-detail-banner{display:flex;align-items:center;justify-content:space-between;gap:22px;margin:22px 0;padding:28px 30px;border-radius:22px;background:linear-gradient(115deg,#142e53,#244d7d);color:#fff;box-shadow:0 12px 30px #17345f18}
.pms-detail-banner h1{margin:0;font-size:26px;font-weight:800;overflow-wrap:anywhere}.pms-detail-kicker{font-size:10px;letter-spacing:.13em;text-transform:uppercase;color:#bad2f0;font-weight:800;margin:0 0 10px}.pms-detail-description{font-size:13px;line-height:1.7;color:#d0def0;margin:10px 0 0;max-width:650px}.pms-detail-banner .btn{flex-shrink:0;background:#fff;border:1px solid #fff;color:#17345f;padding:11px 20px;border-radius:12px;font-size:13px;font-weight:800}.pms-detail-banner .btn:hover{background:#eaf2fe}
.pms-detail-register{border:1px solid #e2e8f0;border-radius:18px;background:white;overflow:hidden;box-shadow:0 6px 24px #17345f07}.pms-detail-register-title{display:flex;align-items:center;gap:12px;padding:20px 24px;border-bottom:1px solid #e8edf4}.pms-detail-register-title>i{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:#edf3fb;color:#315b90;font-size:20px}.pms-detail-register-title h2{margin:0;font-size:15px;color:#17345f;font-weight:800}.pms-detail-register-title p{margin:4px 0 0;font-size:12px;color:#7b899e}.pms-detail-count{margin-left:auto;border:1px solid #e2e8f0;background:#f1f5f9;color:#52647d;border-radius:20px;padding:5px 11px;font-size:11px;font-weight:800;white-space:nowrap}
.pms-detail-table{min-width:800px}.pms-detail-table>thead>tr>th{background:#f8fafc;border-bottom:1px solid #e5ebf3;color:#738299;font-size:10px!important;text-transform:uppercase;letter-spacing:.07em;font-weight:800;padding:15px 18px!important}.pms-detail-table>tbody>tr>td{padding:19px 18px!important;border-color:#eef2f6;font-size:13px;color:#52647d}.pms-detail-table>tbody>tr>td:first-child{color:#17345f;min-width:190px}.pms-detail-table>tbody>tr:hover>td{background:#f8fbff}.pms-detail-table td{max-width:300px;overflow-wrap:anywhere}
#addPmsDetail .modal-content{overflow:hidden}#addPmsDetail .modal-header{background:linear-gradient(115deg,#f1f5fb,#fafcfe);padding:22px 26px!important;border-bottom:1px solid #e2e8f0}#addPmsDetail .modal-header .small{font-size:11px;font-weight:700}#addPmsDetail .modal-title{font-size:19px!important}#addPmsDetail .modal-body{padding:26px!important}#addPmsDetail label{font-size:12px;color:#41546e}#addPmsDetail .form-control,#addPmsDetail .form-select{border:1px solid #dce4ef;border-radius:10px;min-height:46px;font-size:13px;background-color:#fcfdff;padding-left:13px}#addPmsDetail .form-control:focus,#addPmsDetail .form-select:focus{background-color:#fff;border-color:#6188b8;box-shadow:0 0 0 3px #315b9012}#addPmsDetail .modal-footer{padding:16px 26px;background:#fafbfd;border-top:1px solid #e8edf4}#addPmsDetail .modal-footer .btn{border-radius:10px;padding:10px 20px;font-size:13px;font-weight:700}.pms-form-note{display:flex;align-items:flex-start;gap:9px;margin-bottom:22px;padding:12px 14px;background:#f0f5fc;border-radius:10px;font-size:12px;line-height:1.6;color:#527096}
@media(max-width:575px){.pms-detail-banner{padding:22px;align-items:stretch;flex-direction:column}.pms-detail-banner h1{font-size:22px}.pms-detail-register-title{padding:17px 16px;flex-wrap:wrap}#addPmsDetail .modal-body{padding:20px!important}}
</style>
@endpush
@section('content')
<section class="pms-detail-page min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-6 sm:px-7">
    <div class="mx-auto max-w-7xl">
        <a href="{{ route('shipping.pms.vessels.show', $vessel) }}" class="text-sm font-bold text-villa-700"><i class="bi bi-arrow-left"></i> Back to equipment headers</a>
        <div class="pms-detail-banner">
            <div><p class="pms-detail-kicker">PMS / {{ $vessel->vessel_name }}</p>
            <h1>{{ $header->name }}</h1>
            @if($header->description)<p class="pms-detail-description whitespace-pre-line">{{ $header->description }}</p>@endif</div>
            <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#addPmsDetail"><i class="bi bi-plus-lg me-2"></i> Add Detail</button>
        </div>
        @include('shipping.pms.partials.feedback')
        <div class="modal fade" id="addPmsDetail" tabindex="-1" aria-labelledby="addPmsDetailTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="{{ route('shipping.pms.details.store', [$vessel, $header]) }}" class="modal-content rounded-4 border-0 shadow-lg">
            @csrf
            <div class="modal-header bg-slate-50 px-4 py-3"><div><p class="mb-1 small text-secondary">{{ $vessel->vessel_name }} / {{ $header->name }}</p><h2 id="addPmsDetailTitle" class="modal-title fs-5 fw-bold text-villa-900">Add Equipment Detail</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body p-4">
            <div class="pms-form-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Add an equipment item and its maintenance schedule. Fields marked * are required.</span></div>
            @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach(['name' => 'Equipment / Item Name', 'activity' => 'Activity', 'in_charge' => 'In Charge'] as $field => $label)
                    <label for="detail-{{ $field }}" class="block text-sm font-bold text-slate-700">{{ $label }}{{ $field === 'name' ? ' *' : '' }}
                        <input id="detail-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" maxlength="255" @required($field === 'name') class="form-control mt-2">
                    </label>
                @endforeach
                <label for="detail-frequency" class="block text-sm font-bold text-slate-700">Frequency
                    <select id="detail-frequency" name="frequency" class="form-select mt-2">
                        <option value="">Select frequency</option>
                        @foreach($frequencies as $frequency)
                            <option value="{{ $frequency }}" @selected(old('frequency') === $frequency)>{{ $frequency }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="detail-start-date" class="block text-sm font-bold text-slate-700">Start Date<input id="detail-start-date" type="date" name="start_date" value="{{ old('start_date') }}" class="form-control mt-2"></label>
                <label for="detail-status" class="block text-sm font-bold text-slate-700">Status<select id="detail-status" name="status" class="form-select mt-2"><option value="">Select status</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status') === $status)>{{ $status }}</option>@endforeach</select></label>
            </div>
            </div>
            <div class="modal-footer bg-slate-50"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save</button></div>
        </form>
        </div>
        </div>
        <div class="pms-detail-register">
            <div class="pms-detail-register-title"><i class="bi bi-list-check" aria-hidden="true"></i><div><h2>Equipment Details</h2><p>Items and maintenance schedules in this group.</p></div><span class="pms-detail-count">{{ $details->count() }} items</span></div>
            <div class="overflow-x-auto">
            <table class="pms-detail-table table mb-0 align-middle">
                <thead class="bg-emerald-50"><tr>@foreach(['Equipment / Item Name', 'Activity', 'Frequency', 'In Charge', 'Start Date', 'Status'] as $label)<th class="whitespace-nowrap px-4 py-3 text-sm">{{ $label }}</th>@endforeach</tr></thead>
                <tbody>@forelse($details as $detail)<tr><td class="px-4 py-3 font-bold">{{ $detail->name }}</td><td>{{ $detail->activity ?: '—' }}</td><td>{{ $detail->frequency ?: '—' }}</td><td>{{ $detail->in_charge ?: '—' }}</td><td class="whitespace-nowrap">{{ $detail->start_date?->format('d-M-Y') ?? '—' }}</td><td>{{ $detail->status ?: '—' }}</td></tr>@empty<tr><td colspan="6" class="p-5 text-center text-slate-500">No details yet. Add an equipment item above.</td></tr>@endforelse</tbody>
            </table>
            </div>
        </div>
    </div>
</section>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('addPmsDetail');
    modal.addEventListener('shown.bs.modal', function () { document.getElementById('detail-name').focus(); });
    @if($errors->any())
    bootstrap.Modal.getOrCreateInstance(modal).show();
    @endif
});
</script>
@endpush
