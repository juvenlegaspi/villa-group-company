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

    $fixedAssetConditionDashboard = [
        'Good' => ['color' => '#4285f4', 'count' => $fixedAssetStats['condition_counts']['Good'] ?? 0],
        'Needs Repair' => ['color' => '#fbbc05', 'count' => $fixedAssetStats['condition_counts']['Needs Repair'] ?? 0],
        'Damaged' => ['color' => '#ef4335', 'count' => $fixedAssetStats['condition_counts']['Damaged'] ?? 0],
        'Retired' => ['color' => '#34a853', 'count' => $fixedAssetStats['condition_counts']['Retired'] ?? 0],
    ];

    $knownConditionTotal = collect($fixedAssetConditionDashboard)->sum('count');
    $otherConditionTotal = max(0, $fixedAssetStats['total'] - $knownConditionTotal);

    if ($otherConditionTotal > 0) {
        $fixedAssetConditionDashboard['Other'] = ['color' => '#94a3b8', 'count' => $otherConditionTotal];
    }

    $chartStops = [];
    $chartPosition = 0;

    foreach ($fixedAssetConditionDashboard as $conditionData) {
        if ($fixedAssetStats['total'] === 0 || $conditionData['count'] === 0) {
            continue;
        }

        $nextPosition = $chartPosition + (($conditionData['count'] / $fixedAssetStats['total']) * 100);
        $chartStops[] = $conditionData['color'] . ' ' . $chartPosition . '% ' . $nextPosition . '%';
        $chartPosition = $nextPosition;
    }

    $fixedAssetChartBackground = $chartStops
        ? 'conic-gradient(' . implode(', ', $chartStops) . ')'
        : 'conic-gradient(#e2e8f0 0% 100%)';
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

    .yatira-mini-dashboard {
        display: grid;
        grid-template-columns: minmax(150px, 190px) 1fr;
        align-items: center;
        gap: 24px;
        margin-bottom: 22px;
        padding: 18px 22px;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        background: linear-gradient(135deg, #ffffff, #f8fafc);
    }

    .yatira-status-chart {
        position: relative;
        width: 142px;
        height: 142px;
        margin: auto;
        border-radius: 50%;
    }

    .yatira-status-chart::after {
        position: absolute;
        inset: 31px;
        content: '';
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 0 0 1px rgba(226, 232, 240, 0.8);
    }

    .yatira-chart-center {
        position: absolute;
        inset: 0;
        z-index: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #64748b;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .yatira-chart-center strong {
        color: #0f172a;
        font-size: 1.55rem;
        line-height: 1;
        letter-spacing: 0;
    }

    .yatira-dashboard-copy h6 {
        margin-bottom: 3px;
        color: #0f172a;
        font-weight: 700;
    }

    .yatira-dashboard-copy > p {
        margin-bottom: 14px;
        color: #64748b;
        font-size: 0.9rem;
    }

    .yatira-status-legend {
        display: grid;
        grid-template-columns: repeat(2, minmax(145px, 1fr));
        gap: 9px 18px;
    }

    .yatira-status-item {
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 8px;
        min-width: 0;
        color: #475569;
        font-size: 0.88rem;
    }

    .yatira-status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }

    .yatira-status-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .yatira-status-value {
        color: #0f172a;
        font-weight: 700;
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

    @media (max-width: 576px) {
        .yatira-mini-dashboard {
            grid-template-columns: 1fr;
        }

        .yatira-status-legend {
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
                        <div class="yatira-mini-dashboard">
                            <div
                                class="yatira-status-chart"
                                style="background: {{ $fixedAssetChartBackground }};"
                                role="img"
                                aria-label="Fixed asset condition distribution"
                            >
                                <div class="yatira-chart-center">
                                    <strong>{{ $fixedAssetStats['total'] }}</strong>
                                    <span>Total Assets</span>
                                </div>
                            </div>

                            <div class="yatira-dashboard-copy">
                                <h6>Fixed Asset Condition</h6>
                                <p>Quick overview of the current physical condition of all assets.</p>

                                <div class="yatira-status-legend">
                                    @foreach($fixedAssetConditionDashboard as $conditionLabel => $conditionData)
                                        @php
                                            $conditionPercentage = $fixedAssetStats['total'] > 0
                                                ? ($conditionData['count'] / $fixedAssetStats['total']) * 100
                                                : 0;
                                        @endphp
                                        <div class="yatira-status-item">
                                            <span class="yatira-status-dot" style="background: {{ $conditionData['color'] }};"></span>
                                            <span class="yatira-status-label">{{ $conditionLabel }}</span>
                                            <span class="yatira-status-value">
                                                {{ $conditionData['count'] }} ({{ number_format($conditionPercentage, 1) }}%)
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

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
                            <label for="fixedAssetEntryType" class="form-label">Asset Entry</label>
                            <select
                                id="fixedAssetEntryType"
                                name="asset_entry_type"
                                class="form-select @error('asset_entry_type') is-invalid @enderror"
                                required
                            >
                                <option value="new" @selected(old('asset_entry_type', 'new') === 'new')>New Asset</option>
                                <option value="existing" @selected(old('asset_entry_type') === 'existing')>Existing Tagged Asset</option>
                            </select>
                            @error('asset_entry_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Choose whether this asset needs a new system code or already has a tag.</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fixedAssetCode" class="form-label">Asset Code</label>
                            <input
                                id="fixedAssetCode"
                                type="text"
                                name="asset_code"
                                class="form-control @error('asset_code') is-invalid @enderror"
                                value="{{ old('asset_entry_type', 'new') === 'existing' ? old('asset_code') : '' }}"
                                maxlength="255"
                                @readonly(old('asset_entry_type', 'new') !== 'existing')
                                @if(old('asset_entry_type') === 'existing') required @endif
                            >
                            @error('asset_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small id="fixedAssetCodeHelp" class="text-muted"></small>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const entryType = document.getElementById('fixedAssetEntryType');
    const assetCode = document.getElementById('fixedAssetCode');
    const assetCodeHelp = document.getElementById('fixedAssetCodeHelp');

    if (!entryType || !assetCode || !assetCodeHelp) {
        return;
    }

    function syncAssetCodeField() {
        const isExisting = entryType.value === 'existing';

        assetCode.readOnly = !isExisting;
        assetCode.required = isExisting;
        assetCode.placeholder = isExisting
            ? 'Enter the existing asset tag/code'
            : 'Auto-generated on save (Example: YC-ds2134)';
        assetCodeHelp.textContent = isExisting
            ? 'Enter the code already printed or attached to this asset.'
            : 'The system will generate a unique Yatira asset code automatically.';

        if (!isExisting) {
            assetCode.value = '';
        }
    }

    entryType.addEventListener('change', syncAssetCodeField);
    syncAssetCodeField();
});
</script>

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
