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
            <img src="{{ $qrCodeUrl }}" alt="QR code for {{ $fixedAsset->asset_code }}">
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
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editFixedAssetModal">Edit</button>
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
                        <p>{{ $fixedAsset->category }} · Asset Code: {{ $fixedAsset->asset_code }}</p>
                    </div>

                    <span class="badge text-bg-light border px-3 py-2">{{ $fixedAsset->status }}</span>
                </div>

                <div class="asset-meta mb-4">
                    <div class="asset-meta-box">
                        <div class="label">Assigned To</div>
                        <div class="value">{{ $fixedAsset->assigned_to ?: 'N/A' }}</div>
                    </div>
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
                </div>

                <div class="asset-barcode">
                    <div class="mb-3">
                        <h5 class="mb-1">QR Code</h5>
                        <p class="text-muted mb-0">Scan this code to open the full fixed asset details payload.</p>
                    </div>

                    <img src="{{ $qrCodeUrl }}" alt="QR code for {{ $fixedAsset->asset_code }}">
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="editFixedAssetModal" tabindex="-1" aria-labelledby="editFixedAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFixedAssetModalLabel">Edit Fixed Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="{{ route('yatira.inventory.fixed-assets.update', $fixedAsset->id) }}">
                @csrf
                @method('PUT')

                <div class="modal-body">
                    <h6 class="asset-modal-section-title">Asset Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Asset Code</label>
                            <input type="text" class="form-control" value="{{ $fixedAsset->asset_code }}" readonly>
                            <small class="text-muted">Asset code is system-generated and cannot be edited.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Asset Name</label>
                            <input type="text" name="asset_name" class="form-control" value="{{ old('asset_name', $fixedAsset->asset_name) }}" required>
                        </div>
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assigned To</label>
                            <input type="text" name="assigned_to" class="form-control" value="{{ old('assigned_to', $fixedAsset->assigned_to) }}">
                        </div>
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
                                @foreach(['Active', 'In Use', 'Under Maintenance', 'Disposed'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $fixedAsset->status) === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Acquired</label>
                            <input type="date" name="date_acquired" class="form-control" value="{{ old('date_acquired', optional($fixedAsset->date_acquired)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $fixedAsset->remarks) }}</textarea>
                        </div>
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
