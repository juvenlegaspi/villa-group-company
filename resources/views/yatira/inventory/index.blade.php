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
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif
<style>
    .yatira-inventory-shell {
        display: grid;
        gap: 24px;
    }

    .yatira-inventory-card {
        border: 0;
        border-radius: 22px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }

    .yatira-tab-nav {
        gap: 12px;
        flex-wrap: wrap;
    }

    .yatira-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
    }

    .yatira-tab-nav .nav-link {
        border: 0;
        border-radius: 999px;
        padding: 10px 18px;
        color: #2f4f40;
        background: #e8f4ec;
        font-weight: 600;
    }

    .yatira-tab-nav .nav-link.active {
        color: #fff;
        background: #2f7d57;
    }

    .yatira-placeholder {
        border: 1px dashed #cbd5e1;
        border-radius: 20px;
        padding: 28px;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
    }

    .yatira-placeholder h5 {
        color: #0f172a;
        margin-bottom: 8px;
    }

    .yatira-placeholder p {
        color: #64748b;
        margin-bottom: 0;
    }

    .yatira-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }

    .yatira-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 8px 12px;
        background: #f1f5f9;
        color: #334155;
        font-size: 0.92rem;
    }

    .yatira-section-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }

    .yatira-section-head h5 {
        margin: 0;
        color: #0f172a;
    }

    .yatira-section-head p {
        margin: 4px 0 0;
        color: #64748b;
    }

    .yatira-table-wrap {
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        overflow: hidden;
        background: #fff;
    }

    .yatira-table thead th {
        background: #f8fafc;
        color: #334155;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        white-space: nowrap;
    }

    .yatira-table tbody td {
        vertical-align: middle;
    }

    .yatira-empty {
        padding: 28px 18px;
        text-align: center;
        color: #64748b;
    }

    .yatira-modal-section-title {
        margin: 0 0 12px;
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 700;
    }

    .yatira-filter-bar {
        display: grid;
        grid-template-columns: minmax(220px, 1.4fr) minmax(220px, 1fr) minmax(180px, 0.8fr) auto auto;
        gap: 12px;
        align-items: end;
        margin-bottom: 18px;
    }

    @media (max-width: 992px) {
        .yatira-filter-bar {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container-fluid px-0">
    <div class="yatira-inventory-shell">
        <section class="card yatira-inventory-card">
            <div class="card-body p-4">
                <div class="yatira-toolbar">
                    <ul class="nav nav-pills yatira-tab-nav mb-0" id="yatiraInventoryTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="fixed-assets-tab" data-bs-toggle="pill" data-bs-target="#fixed-assets-pane" type="button" role="tab" aria-controls="fixed-assets-pane" aria-selected="true">
                                Fixed Asset
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="consumables-tab" data-bs-toggle="pill" data-bs-target="#consumables-pane" type="button" role="tab" aria-controls="consumables-pane" aria-selected="false">
                                Consumables
                            </button>
                        </li>
                    </ul>

                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFixedAssetModal">
                        + Add
                    </button>
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="fixed-assets-pane" role="tabpanel" aria-labelledby="fixed-assets-tab" tabindex="0">
                        <div class="yatira-section-head">
                            <div>
                                <h5>Fixed Asset Table</h5>
                                <p>Starter layout for long-term company assets under Yatira.</p>
                            </div>
                        </div>

                        <form method="GET" action="{{ route('yatira.inventory.index') }}" class="yatira-filter-bar">
                            <div>
                                <label class="form-label">Search</label>
                                <input
                                    type="text"
                                    name="search"
                                    class="form-control"
                                    value="{{ request('search') }}"
                                    placeholder="Asset code, name, category, assigned to, or location"
                                >
                            </div>

                            <div>
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">All categories</option>
                                    @foreach($fixedAssetCategories as $category)
                                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All status</option>
                                    @foreach(['Active', 'In Use', 'Under Maintenance', 'Disposed'] as $statusOption)
                                        <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>{{ $statusOption }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <button type="submit" class="btn btn-primary w-100">Search</button>
                            </div>

                            <div>
                                <a href="{{ route('yatira.inventory.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
                            </div>
                        </form>

                        <div class="table-responsive yatira-table-wrap">
                            <table class="table table-hover align-middle yatira-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Asset Code</th>
                                        <th>Asset Name</th>
                                        <th>Category</th>
                                        <th>Assigned To</th>
                                        <th>Location</th>
                                        <th>Condition</th>
                                        <th>Status</th>
                                        <th>Date Acquired</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($fixedAssets as $asset)
                                        <tr>
                                            <td>{{ $asset->asset_code }}</td>
                                            <td>{{ $asset->asset_name }}</td>
                                            <td>{{ $asset->category }}</td>
                                            <td>{{ $asset->assigned_to ?: 'N/A' }}</td>
                                            <td>{{ $asset->location ?: 'N/A' }}</td>
                                            <td>{{ $asset->asset_condition }}</td>
                                            <td>{{ $asset->status }}</td>
                                            <td>{{ optional($asset->date_acquired)->format('M d, Y') ?: 'N/A' }}</td>
                                            <td>
                                                <a href="{{ route('yatira.inventory.fixed-assets.show', $asset->id) }}" class="btn btn-sm btn-outline-secondary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="yatira-empty">
                                                No fixed asset records yet. Use the <strong>+ Add</strong> button to start building the inventory.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $fixedAssets->links() }}
                        </div>
                    </div>

                    <div class="tab-pane fade" id="consumables-pane" role="tabpanel" aria-labelledby="consumables-tab" tabindex="0">
                        <div class="yatira-placeholder">
                            <h5>Consumables Inventory</h5>
                            <p>This tab is ready for items that are regularly used or replenished such as office supplies, packaging, or operating materials.</p>
                            <div class="yatira-chip-row">
                                <span class="yatira-chip">Item Name</span>
                                <span class="yatira-chip">Unit</span>
                                <span class="yatira-chip">Available Stock</span>
                                <span class="yatira-chip">Reorder Level</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="addFixedAssetModal" tabindex="-1" aria-labelledby="addFixedAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFixedAssetModalLabel">Add Fixed Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="{{ route('yatira.inventory.fixed-assets.store') }}">
                @csrf

                <div class="modal-body">
                    <h6 class="yatira-modal-section-title">Asset Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Asset Code</label>
                            <input type="text" class="form-control" value="Auto-generated (Example: YC-ds2134)" readonly>
                            <small class="text-muted">The system will generate a unique Yatira asset code automatically.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Asset Name</label>
                            <input type="text" name="asset_name" class="form-control" value="{{ old('asset_name') }}" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select category</option>
                                @foreach($fixedAssetCategories as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assigned To</label>
                            <input type="text" name="assigned_to" class="form-control" value="{{ old('assigned_to') }}">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" value="{{ old('location') }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Condition</label>
                            <select name="asset_condition" class="form-select" required>
                                @foreach(['Good', 'Needs Repair', 'Damaged', 'Retired'] as $condition)
                                    <option value="{{ $condition }}" @selected(old('asset_condition', 'Good') === $condition)>{{ $condition }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                @foreach(['Active', 'In Use', 'Under Maintenance', 'Disposed'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', 'Active') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Acquired</label>
                            <input type="date" name="date_acquired" class="form-control" value="{{ old('date_acquired') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="1">{{ old('remarks') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Fixed Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('addFixedAssetModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
});
</script>
@endif
@endsection
