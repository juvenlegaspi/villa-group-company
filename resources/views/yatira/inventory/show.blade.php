@extends('layouts.app')

@section('content')
@php
    $fixedAssetCategories = [
        'HEAVY EQPT/MACHINERY',
        'TRANSPORTATION EQUIPMENT',
        'TOOLS AND SMALL EQPT',
        'BUILDING AND FACILITY',
        'FURNITURE AND OFFICE EQUIPMENTS',
        'IT & SYSTEMS INFRASTRUCTURE',
        'OTHER / LEGACY',
    ];
    $statusTone = match ($fixedAsset->status) {
        'Active' => 'is-good', 'In Use' => 'is-info',
        'Under Maintenance' => 'is-warning', 'Disposed' => 'is-danger',
        default => '',
    };
    $conditionTone = match ($fixedAsset->asset_condition) {
        'Good' => 'is-good', 'Needs Repair' => 'is-warning',
        'Damaged', 'Retired' => 'is-danger', default => '',
    };
@endphp
@if(session('success'))
    <div class="alert alert-success no-print">
        {{ session('success') }}
    </div>
@endif
<style>
    .asset-shell {
        display: grid;
        gap: 24px;
    }

    .asset-card {
        border: 0;
        border-radius: 22px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        overflow: hidden;
        background: #fff;
    }

    .asset-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .asset-title h3 {
        margin: 0;
        color: #0f172a;
    }

    .asset-title p {
        margin: 6px 0 0;
        color: #64748b;
    }

    .asset-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
    }

    .asset-meta-box {
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 16px;
        background: #f8fafc;
    }

    .asset-meta-box .label {
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-bottom: 6px;
    }

    .asset-meta-box .value {
        color: #0f172a;
        font-weight: 600;
    }

    .asset-barcode {
        border: 1px dashed #cbd5e1;
        border-radius: 20px;
        padding: 24px;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        text-align: center;
    }

    .asset-barcode img,
    .sticker-qr img {
        max-width: 100%;
        height: auto;
    }

    .print-only {
        display: none;
    }

    .sticker-print {
        display: none;
    }

    .asset-modal-section-title {
        margin: 0 0 12px;
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 700;
    }

    .tag-page{max-width:1280px;margin:auto;padding:24px}.tag-hero{position:relative;overflow:hidden;margin-bottom:22px;padding:28px;border-radius:26px;color:#fff;background:linear-gradient(120deg,#0f274c,#24477f 60%,#b76816);box-shadow:0 18px 45px #0f274c33}.tag-hero:after{content:"";position:absolute;right:-70px;top:-100px;width:260px;height:260px;border-radius:50%;background:#ffffff17}.tag-hero-inner{position:relative;z-index:1;display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap}.tag-eyebrow{margin:0 0 6px;color:#fde68a;font-size:.72rem;font-weight:900;letter-spacing:.15em;text-transform:uppercase}.tag-hero h1{margin:0;font-size:clamp(1.55rem,3vw,2.35rem);font-weight:900}.tag-subtitle{margin-top:9px;color:#dbeafe;font-size:.84rem;font-weight:700}.tag-actions{display:flex;flex-wrap:wrap;gap:9px}.tag-action{display:inline-flex;min-height:43px;align-items:center;justify-content:center;gap:8px;border:1px solid #ffffff59;border-radius:12px;padding:0 16px;color:#fff;background:#ffffff1c;font-size:.84rem;font-weight:800;text-decoration:none}.tag-action:hover{color:#fff;background:#ffffff33}.tag-action.primary{border-color:#fff;color:#17345f;background:#fff}.tag-layout{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(300px,.75fr);gap:22px;align-items:start}.tag-panel,.tag-preview{overflow:hidden;border:1px solid #e2e8f0;border-radius:22px;background:#fff;box-shadow:0 12px 32px #0f172a0f}.tag-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:19px 22px;border-bottom:1px solid #eef2f7}.tag-panel-head h2{margin:0;color:#0f172a;font-size:1rem;font-weight:900}.tag-panel-head p{margin:4px 0 0;color:#64748b;font-size:.76rem}.tag-panel-body{padding:22px}.tag-badges{display:flex;flex-wrap:wrap;gap:8px}.tag-badge{display:inline-flex;align-items:center;border-radius:999px;padding:7px 11px;color:#475569;background:#f1f5f9;font-size:.67rem;font-weight:900;text-transform:uppercase;box-shadow:inset 0 0 0 1px #e2e8f0}.tag-badge.is-good{color:#047857;background:#d1fae5;box-shadow:inset 0 0 0 1px #6ee7b7}.tag-badge.is-info{color:#1d4ed8;background:#dbeafe;box-shadow:inset 0 0 0 1px #93c5fd}.tag-badge.is-warning{color:#92400e;background:#fef3c7;box-shadow:inset 0 0 0 1px #fcd34d}.tag-badge.is-danger{color:#be123c;background:#ffe4e6;box-shadow:inset 0 0 0 1px #fda4af}.tag-info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.tag-info{min-width:0;border:1px solid #e8edf4;border-radius:16px;padding:15px 16px;background:#f8fafc}.tag-info.wide{grid-column:1/-1}.tag-label{margin-bottom:7px;color:#64748b;font-size:.66rem;font-weight:900;letter-spacing:.07em;text-transform:uppercase}.tag-value{overflow-wrap:anywhere;color:#172554;font-size:.9rem;font-weight:800;line-height:1.45}.tag-value.money{color:#047857;font-size:1.03rem}.tag-preview{position:sticky;top:92px}.tag-preview-head{padding:18px 20px;color:#fff;background:linear-gradient(110deg,#17345f,#285794);text-align:center}.tag-preview-head small{display:block;margin-bottom:3px;color:#fcd34d;font-size:.67rem;font-weight:900;letter-spacing:.15em;text-transform:uppercase}.tag-preview-head h2{margin:0;font-size:1.05rem;font-weight:900}.tag-preview-body{padding:22px;text-align:center}.tag-name{margin:0 0 6px;color:#0f172a;font-size:1.08rem;font-weight:900}.tag-code{display:inline-flex;margin-bottom:18px;border-radius:9px;padding:6px 10px;color:#24477f;background:#edf4fd;font-family:monospace;font-weight:900;letter-spacing:.08em}.tag-barcode{display:grid;min-height:210px;place-items:center;border:2px dashed #b8c5d8;border-radius:18px;padding:18px;background:linear-gradient(#fff,#f8fafc)}.tag-barcode img{width:min(100%,230px);height:auto}.tag-location{margin:14px 0 0;color:#64748b;font-size:.76rem;font-weight:700}.tag-note{margin:0;padding:13px 18px;color:#475569;background:#f8fafc;font-size:.7rem;text-align:center}.tag-support{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:22px;margin-top:22px}.tag-document{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 13px;border:1px solid #e2e8f0;border-radius:13px;color:#17345f;background:#f8fafc;text-decoration:none}.tag-document:hover{color:#17345f;background:#edf4fd}.tag-document span:first-child{overflow:hidden;font-size:.8rem;font-weight:800;text-overflow:ellipsis;white-space:nowrap}.tag-document small{flex:none;color:#64748b}.tag-audit{position:relative;margin-left:5px;padding:0 0 16px 21px;border-left:2px solid #dbe4f0}.tag-audit:before{content:"";position:absolute;left:-6px;top:3px;width:10px;height:10px;border:2px solid #fff;border-radius:50%;background:#d5a438;box-shadow:0 0 0 2px #d5a438}.tag-audit strong{display:block;color:#1e293b;font-size:.78rem;text-transform:capitalize}.tag-audit span{color:#64748b;font-size:.68rem}@media(max-width:900px){.tag-layout,.tag-support{grid-template-columns:1fr}.tag-preview{position:static}.tag-page{padding:16px}}@media(max-width:560px){.tag-info-grid{grid-template-columns:1fr}.tag-info.wide{grid-column:auto}.tag-actions,.tag-action{width:100%}.tag-panel-head{align-items:flex-start;flex-direction:column}}

    @media print {
        @page {
            size: 62mm 35mm;
            margin: 2mm;
        }

        .no-print,
        .asset-shell,
        .asset-card,
        .asset-header,
        .asset-meta,
        .asset-barcode,
        .print-screen-content,
        .sidebar,
        .topbar,
        nav,
        .btn,
        .breadcrumb,
        footer {
            display: none !important;
        }

        .print-only,
        .sticker-print {
            display: block;
        }

        html,
        body {
            width: 62mm;
            height: 35mm;
            margin: 0;
            padding: 0;
            background: #fff !important;
        }

        .sticker-print {
            width: 58mm;
            height: 31mm;
            border: 1px solid #111827;
            padding: 2mm;
            box-sizing: border-box;
            overflow: hidden;
            font-family: Arial, sans-serif;
            color: #111827;
        }

        .sticker-title {
            font-size: 8px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1mm;
            letter-spacing: 0.04em;
        }

        .sticker-name {
            font-size: 8px;
            font-weight: 700;
            text-align: center;
            line-height: 1.1;
            margin-bottom: 1mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sticker-code {
            font-size: 7px;
            text-align: center;
            margin-bottom: 1mm;
        }

        .sticker-barcode {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .sticker-barcode img {
            width: 16mm;
            height: 16mm;
            object-fit: contain;
        }

        .sticker-footer {
            font-size: 6px;
            text-align: center;
            margin-top: 1mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
</style>

<div class="tag-page">
    <div class="sticker-print">
        <div class="sticker-title">YATIRA FIXED ASSET</div>
        <div class="sticker-name">{{ $fixedAsset->asset_name }}</div>
        <div class="sticker-code">{{ $fixedAsset->asset_code }}</div>
        <div class="sticker-barcode">
            <img src="{{ $barcodeSvg }}" alt="QR code for {{ $fixedAsset->asset_code }}">
        </div>
        <div class="sticker-footer">{{ $fixedAsset->location ?: ($fixedAsset->category ?: 'YATIRA') }}</div>
    </div>

    <header class="tag-hero no-print"><div class="tag-hero-inner"><div><p class="tag-eyebrow"><i class="bi bi-upc-scan me-1"></i> Asset Tagging Record</p><h1>{{ $fixedAsset->asset_name }}</h1><div class="tag-subtitle"><i class="bi bi-tag me-1"></i>{{ $fixedAsset->asset_code }} &bull; {{ $fixedAsset->category }}</div></div><div class="tag-actions"><a href="{{ route('yatira.inventory.index') }}" class="tag-action"><i class="bi bi-arrow-left"></i>Back</a>@if($canUpdateAssets && ($fixedAsset->status !== 'Disposed' || $canDisposeAssets))<button type="button" class="tag-action" data-bs-toggle="modal" data-bs-target="#editFixedAssetModal"><i class="bi bi-pencil-square"></i>Edit Asset</button>@endif<a class="tag-action primary" target="_blank" rel="noopener" href="{{ route('yatira.inventory.fixed-assets.print-tags', ['from_asset_id' => $fixedAsset->id, 'to_asset_id' => $fixedAsset->id, 'copies' => 1, 'include_disposed' => 1]) }}"><i class="bi bi-printer"></i>Print Asset Tag</a></div></div></header>

    <div class="tag-layout print-screen-content">
        <section class="tag-panel"><header class="tag-panel-head"><div><h2><i class="bi bi-info-square me-2 text-primary"></i>Asset Information</h2><p>Identity, assignment and acquisition details.</p></div><div class="tag-badges"><span class="tag-badge {{ $statusTone }}">{{ $fixedAsset->status }}</span><span class="tag-badge {{ $conditionTone }}">{{ $fixedAsset->asset_condition }}</span></div></header><div class="tag-panel-body"><div class="tag-info-grid">
            <div class="tag-info"><div class="tag-label"><i class="bi bi-upc me-1"></i>Asset Code</div><div class="tag-value">{{ $fixedAsset->asset_code }}</div></div>
            <div class="tag-info"><div class="tag-label"><i class="bi bi-hash me-1"></i>Serial Number</div><div class="tag-value">{{ $fixedAsset->serial_number ?: 'Not recorded' }}</div></div>
            <div class="tag-info"><div class="tag-label"><i class="bi bi-person-check me-1"></i>Assigned To</div><div class="tag-value">{{ $fixedAsset->assignedUser ? trim($fixedAsset->assignedUser->name.' '.$fixedAsset->assignedUser->lastname) : ($fixedAsset->assigned_to ?: 'Unassigned') }}</div></div>
            <div class="tag-info"><div class="tag-label"><i class="bi bi-diagram-3 me-1"></i>Department</div><div class="tag-value">{{ $fixedAsset->assignedDepartment?->name ?: 'Not assigned' }}</div></div>
            <div class="tag-info"><div class="tag-label"><i class="bi bi-geo-alt me-1"></i>Current Location</div><div class="tag-value">{{ $fixedAsset->location ?: 'Not recorded' }}</div></div>
            <div class="tag-info"><div class="tag-label"><i class="bi bi-calendar3 me-1"></i>Date Acquired</div><div class="tag-value">{{ optional($fixedAsset->date_acquired)->format('M d, Y') ?: 'Not recorded' }}</div></div>
            <div class="tag-info"><div class="tag-label"><i class="bi bi-cash-stack me-1"></i>Acquisition Cost</div><div class="tag-value money">{{ is_null($fixedAsset->acquisition_cost) ? 'Not recorded' : 'PHP '.number_format((float)$fixedAsset->acquisition_cost, 2) }}</div></div>
            <div class="tag-info"><div class="tag-label"><i class="bi bi-person me-1"></i>Encoded By</div><div class="tag-value">{{ $fixedAsset->user ? trim($fixedAsset->user->name.' '.$fixedAsset->user->lastname) : 'System' }}</div></div>
            <div class="tag-info wide"><div class="tag-label"><i class="bi bi-card-text me-1"></i>Remarks</div><div class="tag-value">{{ $fixedAsset->remarks ?: 'No remarks recorded.' }}</div></div>
            @if($fixedAsset->status === 'Disposed')<div class="tag-info wide"><div class="tag-label"><i class="bi bi-exclamation-triangle me-1"></i>Disposal Reason</div><div class="tag-value">{{ $fixedAsset->disposal_reason ?: 'No reason recorded.' }}</div></div>@endif
        </div></div></section>

        <aside class="tag-preview" aria-label="Printable asset tag preview"><div class="tag-preview-head"><small>Yatira Construction, Inc.</small><h2>Fixed Asset Tag</h2></div><div class="tag-preview-body"><h3 class="tag-name">{{ $fixedAsset->asset_name }}</h3><div class="tag-code">{{ $fixedAsset->asset_code }}</div><div class="tag-barcode"><img src="{{ $barcodeSvg }}" alt="QR code for {{ $fixedAsset->asset_code }}"></div><p class="tag-location"><i class="bi bi-geo-alt me-1"></i>{{ $fixedAsset->location ?: ($fixedAsset->category ?: 'YATIRA') }}</p></div><p class="tag-note"><i class="bi bi-shield-check me-1"></i>Locally generated. No asset information is sent externally.</p></aside>
    </div>

    <div class="tag-support no-print">
        <section class="tag-panel"><header class="tag-panel-head"><div><h2><i class="bi bi-paperclip me-2 text-primary"></i>Supporting Documents</h2><p>Uploaded references and document versions.</p></div><span class="tag-badge">{{ $fixedAsset->documents->count() ?: ($fixedAsset->document_path ? 1 : 0) }} files</span></header><div class="tag-panel-body d-grid gap-2">@forelse($fixedAsset->documents as $document)<a class="tag-document" href="{{ route('yatira.inventory.fixed-assets.documents.show', [$fixedAsset, $document]) }}"><span><i class="bi bi-file-earmark-text me-2"></i>{{ $document->original_name }}</span><small>{{ optional($document->created_at)->format('M d, Y') }} &bull; {{ $document->uploader?->name ?? 'System' }}</small></a>@empty @if($fixedAsset->document_path)<a class="tag-document" href="{{ route('yatira.inventory.fixed-assets.document', $fixedAsset) }}"><span><i class="bi bi-file-earmark-text me-2"></i>{{ $fixedAsset->document_original_name ?: 'Supporting document' }}</span><small>Download</small></a>@else<p class="mb-0 rounded-3 bg-light p-4 text-center text-muted"><i class="bi bi-folder2-open d-block mb-2 fs-4"></i>No supporting documents uploaded.</p>@endif @endforelse</div></section>
        <section class="tag-panel"><header class="tag-panel-head"><div><h2><i class="bi bi-clock-history me-2 text-primary"></i>Audit Trail</h2><p>Recent activity for this record.</p></div><span class="tag-badge">{{ $fixedAsset->audits->count() }} events</span></header><div class="tag-panel-body" style="max-height:360px;overflow-y:auto">@forelse($fixedAsset->audits as $audit)<div class="tag-audit"><strong>{{ str_replace('_', ' ', $audit->action) }}</strong><span>{{ $audit->user?->name ?? 'System' }} &bull; {{ optional($audit->created_at)->format('M d, Y h:i A') }}</span></div>@empty<p class="mb-0 text-center text-muted">No audit activity recorded.</p>@endforelse</div></section>
    </div>
</div>

@if($canUpdateAssets && ($fixedAsset->status !== 'Disposed' || $canDisposeAssets))
<div class="modal fade" id="editFixedAssetModal" tabindex="-1" aria-labelledby="editFixedAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFixedAssetModalLabel">Edit Fixed Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="{{ route('yatira.inventory.fixed-assets.update', $fixedAsset->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="modal-body">
                    <h6 class="asset-modal-section-title">Asset Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Asset Code</label>
                            <input type="text" class="form-control" value="{{ $fixedAsset->asset_code }}" readonly>
                            <small class="text-muted">The permanent asset code cannot be edited after registration.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Asset Name</label>
                            <input type="text" name="asset_name" class="form-control" value="{{ old('asset_name', $fixedAsset->asset_name) }}" required>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">Serial Number</label><input type="text" name="serial_number" class="form-control" value="{{ old('serial_number', $fixedAsset->serial_number) }}"></div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select category</option>
                                @foreach($fixedAssetCategories as $category)
                                    <option value="{{ $category }}" @selected(old('category', $fixedAsset->category) === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">Assigned User</label><select name="assigned_user_id" class="form-select"><option value="">None</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)old('assigned_user_id',$fixedAsset->assigned_user_id)===(string)$user->id)>{{ $user->name }} {{ $user->lastname }}</option>@endforeach</select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Assigned Department</label><select name="assigned_department_id" class="form-select"><option value="">None</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)old('assigned_department_id',$fixedAsset->assigned_department_id)===(string)$department->id)>{{ $department->name }}</option>@endforeach</select></div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" value="{{ old('location', $fixedAsset->location) }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Condition</label>
                            <select name="asset_condition" class="form-select" required>
                                @foreach(['Good', 'Needs Repair', 'Damaged', 'Retired'] as $condition)
                                    <option value="{{ $condition }}" @selected(old('asset_condition', $fixedAsset->asset_condition) === $condition)>{{ $condition }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                @foreach($canDisposeAssets ? ['Active', 'In Use', 'Under Maintenance', 'Disposed'] : ['Active', 'In Use', 'Under Maintenance'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $fixedAsset->status) === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Acquired</label>
                            <input type="date" name="date_acquired" max="{{ today()->toDateString() }}" class="form-control" value="{{ old('date_acquired', optional($fixedAsset->date_acquired)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">Acquisition Cost</label><input type="number" min="0" step="0.01" name="acquisition_cost" class="form-control" value="{{ old('acquisition_cost', $fixedAsset->acquisition_cost) }}"></div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $fixedAsset->remarks) }}</textarea>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">Disposal Reason</label><textarea name="disposal_reason" class="form-control" rows="2">{{ old('disposal_reason', $fixedAsset->disposal_reason) }}</textarea><small class="text-muted">Required when status is Disposed.</small></div>
                        <div class="col-12 mb-3"><label class="form-label">Replace Supporting Document</label><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" class="form-control"><small class="text-muted">PDF, JPG or PNG · Maximum 5 MB</small></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Fixed Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('editFixedAssetModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
});
</script>
@endif
@endsection
