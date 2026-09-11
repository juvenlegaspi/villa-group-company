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

<div class="container-fluid px-0">
    <div class="sticker-print">
        <div class="sticker-title">YATIRA FIXED ASSET</div>
        <div class="sticker-name">{{ $fixedAsset->asset_name }}</div>
        <div class="sticker-code">{{ $fixedAsset->asset_code }}</div>
        <div class="sticker-barcode">
            <img src="{{ $barcodeSvg }}" alt="Barcode for {{ $fixedAsset->asset_code }}">
        </div>
        <div class="sticker-footer">{{ $fixedAsset->location ?: ($fixedAsset->category ?: 'YATIRA') }}</div>
    </div>

    <div class="asset-shell">
        <div class="asset-header no-print">
            <div class="asset-title">
                <h3>Fixed Asset Details</h3>
                <p>Barcode-ready record for viewing, scanning, and printing.</p>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('yatira.inventory.index') }}" class="btn btn-outline-secondary">Back</a>
                @if($canUpdateAssets && ($fixedAsset->status !== 'Disposed' || $canDisposeAssets))<button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editFixedAssetModal">Edit</button>@endif
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            </div>
        </div>

        <section class="card asset-card"> 
            <div class="card-body p-4 p-lg-5 print-screen-content">
                <div class="print-only mb-3">
                    <h2 style="margin:0; color:#0f172a;">Yatira Fixed Asset</h2>
                    <p style="margin:6px 0 0; color:#64748b;">Printable item reference and barcode sheet</p>
                </div>

                <div class="asset-header">
                    <div class="asset-title">
                        <h3>{{ $fixedAsset->asset_name }}</h3>
                        <p>{{ $fixedAsset->category }} · Asset Code: {{ $fixedAsset->asset_code }}@if($fixedAsset->serial_number) · Serial: {{ $fixedAsset->serial_number }}@endif</p>
                    </div>

                    <span class="badge text-bg-light border px-3 py-2">{{ $fixedAsset->status }}</span>
                </div>

                <div class="asset-meta mb-4">
                    <div class="asset-meta-box">
                        <div class="label">Assigned To</div>
                        <div class="value">{{ $fixedAsset->assignedUser ? $fixedAsset->assignedUser->name.' '.$fixedAsset->assignedUser->lastname : ($fixedAsset->assigned_to ?: 'N/A') }}</div>
                    </div>
                    <div class="asset-meta-box"><div class="label">Department</div><div class="value">{{ $fixedAsset->assignedDepartment?->name ?: 'N/A' }}</div></div>
                    <div class="asset-meta-box">
                        <div class="label">Location</div>
                        <div class="value">{{ $fixedAsset->location ?: 'N/A' }}</div>
                    </div>
                    <div class="asset-meta-box">
                        <div class="label">Condition</div>
                        <div class="value">{{ $fixedAsset->asset_condition }}</div>
                    </div>
                    <div class="asset-meta-box">
                        <div class="label">Date Acquired</div>
                        <div class="value">{{ optional($fixedAsset->date_acquired)->format('M d, Y') ?: 'N/A' }}</div>
                    </div>
                    <div class="asset-meta-box">
                        <div class="label">Encoded By</div>
                        <div class="value">{{ $fixedAsset->user ? $fixedAsset->user->name . ' ' . $fixedAsset->user->lastname : 'N/A' }}</div>
                    </div>
                    <div class="asset-meta-box">
                        <div class="label">Remarks</div>
                        <div class="value">{{ $fixedAsset->remarks ?: 'N/A' }}</div>
                    </div>
                    <div class="asset-meta-box"><div class="label">Acquisition Cost</div><div class="value">{{ is_null($fixedAsset->acquisition_cost) ? 'N/A' : number_format((float)$fixedAsset->acquisition_cost, 2) }}</div></div>
                </div>

                @if($fixedAsset->documents->isNotEmpty())<div class="mb-4 rounded-3 border bg-light p-3"><strong>Supporting Document Versions</strong><div class="mt-2 d-grid gap-2">@foreach($fixedAsset->documents as $document)<a class="btn btn-sm btn-outline-primary d-flex justify-content-between align-items-center" href="{{ route('yatira.inventory.fixed-assets.documents.show', [$fixedAsset, $document]) }}"><span>{{ $document->original_name }}</span><small>{{ optional($document->created_at)->format('M d, Y') }} &middot; {{ $document->uploader?->name ?? 'System' }}</small></a>@endforeach</div></div>@elseif($fixedAsset->document_path)<div class="mb-4 rounded-3 border bg-light p-3"><strong>Supporting Document</strong><div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('yatira.inventory.fixed-assets.document', $fixedAsset) }}">Download {{ $fixedAsset->document_original_name ?: 'document' }}</a></div></div>@endif

                <div class="asset-barcode">
                    <div class="mb-3">
                        <h5 class="mb-1">Asset Barcode</h5>
                        <p class="text-muted mb-0">Scan the locally generated asset code to identify this record. No asset data is sent to an external service.</p>
                    </div>

                    <img src="{{ $barcodeSvg }}" alt="Barcode for {{ $fixedAsset->asset_code }}">
                </div>
            </div>
        </section>
    </div>
</div>

<details class="no-print mx-auto mb-4 rounded-3 border bg-white" style="max-width:1180px"><summary class="cursor-pointer p-3 fw-bold">Audit Trail ({{ $fixedAsset->audits->count() }})</summary><div class="border-top p-3">@forelse($fixedAsset->audits as $audit)<div class="border-start border-2 ps-3 mb-3"><strong class="text-capitalize">{{ str_replace('_', ' ', $audit->action) }}</strong><div class="small text-muted">{{ $audit->user?->name ?? 'System' }} · {{ optional($audit->created_at)->format('M d, Y h:i A') }}</div></div>@empty<p class="text-muted mb-0">No audit activity recorded.</p>@endforelse</div></details>

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
