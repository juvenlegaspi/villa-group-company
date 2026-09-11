@extends('layouts.app')

@section('content')
@php
    $activeTab = request('tab') === 'consumables' || ! $canViewAssets ? 'consumables' : 'assets';
    $fixedAssetCategories = [
        'HEAVY EQPT/MACHINERY',
        'TRANSPORTATION EQUIPMENT',
        'TOOLS AND SMALL EQPT',
        'BUILDING AND FACILITY',
        'FURNITURE AND OFFICE EQUIPMENTS',
        'IT & SYSTEMS INFRASTRUCTURE',
        'OTHER / LEGACY',
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

    $fixedAssetStatusDashboard = [
        'Active' => ['color' => '#4285f4', 'count' => $fixedAssetStats['status_counts']['Active'] ?? 0],
        'In Use' => ['color' => '#ef4335', 'count' => $fixedAssetStats['status_counts']['In Use'] ?? 0],
        'Under Maintenance' => ['color' => '#fbbc05', 'count' => $fixedAssetStats['status_counts']['Under Maintenance'] ?? 0],
        'Disposed' => ['color' => '#34a853', 'count' => $fixedAssetStats['status_counts']['Disposed'] ?? 0],
    ];

    $knownStatusTotal = collect($fixedAssetStatusDashboard)->sum('count');
    $otherStatusTotal = max(0, $fixedAssetStats['total'] - $knownStatusTotal);

    if ($otherStatusTotal > 0) {
        $fixedAssetStatusDashboard['Other'] = ['color' => '#94a3b8', 'count' => $otherStatusTotal];
    }

    $statusChartStops = [];
    $statusChartPosition = 0;

    foreach ($fixedAssetStatusDashboard as $statusData) {
        if ($fixedAssetStats['total'] === 0 || $statusData['count'] === 0) {
            continue;
        }

        $nextStatusPosition = $statusChartPosition + (($statusData['count'] / $fixedAssetStats['total']) * 100);
        $statusChartStops[] = $statusData['color'] . ' ' . $statusChartPosition . '% ' . $nextStatusPosition . '%';
        $statusChartPosition = $nextStatusPosition;
    }

    $fixedAssetStatusChartBackground = $statusChartStops
        ? 'conic-gradient(' . implode(', ', $statusChartStops) . ')'
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

    .yatira-dashboard-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 28px;
    }

    .yatira-mini-dashboard {
        position: relative;
        display: grid;
        grid-template-columns: minmax(112px, 130px) 1fr;
        align-items: center;
        gap: 22px;
        padding: 24px;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }

    .yatira-mini-dashboard::before {
        position: absolute;
        inset: 0 auto 0 0;
        width: 5px;
        content: '';
        background: linear-gradient(180deg, #2563eb, #38bdf8);
    }

    .yatira-mini-dashboard--status::before {
        background: linear-gradient(180deg, #d97706, #facc15);
    }

    .yatira-status-chart {
        position: relative;
        width: 124px;
        height: 124px;
        margin: auto;
        border-radius: 50%;
    }

    .yatira-status-chart::after {
        position: absolute;
        inset: 27px;
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
        margin-bottom: 4px;
        color: #0f172a;
        font-size: 1.05rem;
        font-weight: 800;
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
        padding: 8px 10px;
        border-radius: 11px;
        background: #f8fafc;
        color: #475569;
        font-size: 0.82rem;
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

        .yatira-dashboard-grid {
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

<main class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-slate-50 via-white to-amber-50/40 px-4 py-6 sm:px-7 lg:px-10">
    <div class="mx-auto max-w-7xl">
        <header class="mb-6 overflow-hidden rounded-3xl bg-gradient-to-r from-villa-900 via-villa-800 to-amber-700 p-6 text-white shadow-xl sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="mb-1 text-xs font-extrabold uppercase tracking-[.18em] text-amber-200">Yatira Construction, Inc.</p><h1 class="mb-1 text-2xl font-black sm:text-3xl">Inventory Control Center</h1><p class="mb-0 max-w-2xl text-sm text-blue-100">Monitor fixed-asset condition and status, document custody, and consumable stock movements.</p></div>
                <div class="flex flex-wrap gap-2"><a href="{{ route('yatira.applications') }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 text-sm font-bold text-white no-underline hover:bg-white/20"><i class="bi bi-grid"></i>Applications</a>@if($canCreateAssets)<button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border-0 bg-white px-4 text-sm font-black text-villa-900 shadow-lg" data-bs-toggle="modal" data-bs-target="#addFixedAssetModal"><i class="bi bi-plus-lg"></i>Add Asset</button>@endif</div>
            </div>
        </header>
    <div class="yatira-inventory-shell">
        <section class="card yatira-inventory-card">
            <div class="card-body p-4">
                <div class="yatira-toolbar">
                    <ul class="nav nav-pills yatira-tab-nav mb-0" id="yatiraInventoryTabs" role="tablist">
                        @if($canViewAssets)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $activeTab === 'assets' ? 'active' : '' }}" id="fixed-assets-tab" data-bs-toggle="pill" data-bs-target="#fixed-assets-pane" type="button" role="tab" aria-controls="fixed-assets-pane" aria-selected="{{ $activeTab === 'assets' ? 'true' : 'false' }}">
                                Fixed Asset
                            </button>
                        </li>
                        @endif
                        @if($canViewConsumables)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $activeTab === 'consumables' ? 'active' : '' }}" id="consumables-tab" data-bs-toggle="pill" data-bs-target="#consumables-pane" type="button" role="tab" aria-controls="consumables-pane" aria-selected="{{ $activeTab === 'consumables' ? 'true' : 'false' }}">
                                Consumables
                            </button>
                        </li>
                        @endif
                    </ul>

                </div>

                <div class="tab-content">
                    @if($canViewAssets)
                    <div class="tab-pane fade {{ $activeTab === 'assets' ? 'show active' : '' }}" id="fixed-assets-pane" role="tabpanel" aria-labelledby="fixed-assets-tab" tabindex="0">
                        <div class="yatira-dashboard-grid">
                            <div class="yatira-mini-dashboard yatira-mini-dashboard--condition">
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
                                    <h6>Condition</h6>
                                    <p>Current physical condition of all fixed assets.</p>

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

                            <div class="yatira-mini-dashboard yatira-mini-dashboard--status">
                                <div
                                    class="yatira-status-chart"
                                    style="background: {{ $fixedAssetStatusChartBackground }};"
                                    role="img"
                                    aria-label="Fixed asset status distribution"
                                >
                                    <div class="yatira-chart-center">
                                        <strong>{{ $fixedAssetStats['total'] }}</strong>
                                        <span>Total Assets</span>
                                    </div>
                                </div>

                                <div class="yatira-dashboard-copy">
                                    <h6>Status</h6>
                                    <p>Current operational status of all fixed assets.</p>

                                    <div class="yatira-status-legend">
                                        @foreach($fixedAssetStatusDashboard as $statusLabel => $statusData)
                                            @php
                                                $statusPercentage = $fixedAssetStats['total'] > 0
                                                    ? ($statusData['count'] / $fixedAssetStats['total']) * 100
                                                    : 0;
                                            @endphp
                                            <div class="yatira-status-item">
                                                <span class="yatira-status-dot" style="background: {{ $statusData['color'] }};"></span>
                                                <span class="yatira-status-label">{{ $statusLabel }}</span>
                                                <span class="yatira-status-value">
                                                    {{ $statusData['count'] }} ({{ number_format($statusPercentage, 1) }}%)
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
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
                    @endif

                    @if($canViewConsumables)
                    <div class="tab-pane fade {{ $activeTab === 'consumables' ? 'show active' : '' }}" id="consumables-pane" role="tabpanel" aria-labelledby="consumables-tab" tabindex="0">
                        <div class="yatira-section-head"><div><h5>Consumables Inventory</h5><p>Monitor stock balances, reorder thresholds and controlled movements.</p></div>@if($canManageConsumables)<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addConsumableModal">+ Add Item</button>@endif</div>
                        <form method="GET" action="{{ route('yatira.inventory.index') }}" class="yatira-filter-bar"><input type="hidden" name="tab" value="consumables"><div style="grid-column:span 3"><label class="form-label">Search</label><input name="consumable_search" value="{{ request('consumable_search') }}" class="form-control" placeholder="Item code or name"></div><div><button class="btn btn-primary w-100">Search</button></div><div><a href="{{ route('yatira.inventory.index', ['tab'=>'consumables']) }}" class="btn btn-outline-secondary w-100">Reset</a></div></form>
                        <div class="table-responsive yatira-table-wrap"><table class="table table-hover align-middle yatira-table mb-0"><thead><tr><th>Code</th><th>Item</th><th>Unit</th><th>Available</th><th>Reorder</th><th>Status</th><th>Actions</th></tr></thead><tbody>@forelse($consumables as $item)<tr><td class="fw-bold">{{ $item->item_code }}</td><td>{{ $item->item_name }}</td><td>{{ $item->unit }}</td><td>{{ number_format($item->stock_on_hand) }}</td><td>{{ number_format($item->reorder_level) }}</td><td><span class="badge {{ ! $item->status ? 'bg-secondary' : ($item->stock_on_hand <= $item->reorder_level ? 'bg-warning text-dark' : 'bg-success') }}">{{ ! $item->status ? 'Inactive' : ($item->stock_on_hand <= $item->reorder_level ? 'Reorder' : 'Sufficient') }}</span></td><td><div class="d-flex gap-2"><a href="{{ route('yatira.inventory.consumables.show', $item) }}" class="btn btn-sm btn-outline-secondary">History</a>@if($canManageConsumables && $item->status)<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#movementModal{{ $item->id }}">Stock In / Out</button>@endif</div></td></tr>@empty<tr><td colspan="7" class="yatira-empty">No consumable items recorded yet.</td></tr>@endforelse</tbody></table></div>
                        <div class="mt-3">{{ $consumables->links() }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
</div>
</main>

@if($canManageConsumables)
<div class="modal fade" id="addConsumableModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Consumable Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><form method="POST" action="{{ route('yatira.inventory.consumables.store') }}">@csrf<input type="hidden" name="_form_context" value="create_consumable"><div class="modal-body"><div class="mb-3"><label class="form-label">Item Name</label><input name="item_name" value="{{ old('item_name') }}" class="form-control" required maxlength="255"></div><div class="row"><div class="col-6 mb-3"><label class="form-label">Unit</label><input name="unit" value="{{ old('unit') }}" class="form-control" placeholder="pc, box, kg" required maxlength="50"></div><div class="col-6 mb-3"><label class="form-label">Opening Stock</label><input type="number" name="opening_stock" min="0" value="{{ old('opening_stock', 0) }}" class="form-control" required></div></div><div><label class="form-label">Reorder Level</label><input type="number" name="reorder_level" min="0" value="{{ old('reorder_level', 0) }}" class="form-control" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save Item</button></div></form></div></div></div>
@foreach($consumables as $item)
<div class="modal fade" id="movementModal{{ $item->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title">Record Stock Movement</h5><small class="text-muted">{{ $item->item_name }} · Available {{ $item->stock_on_hand }} {{ $item->unit }}</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" action="{{ route('yatira.inventory.consumables.movement', $item) }}">@csrf<div class="modal-body"><div class="row"><div class="col-6 mb-3"><label class="form-label">Movement</label><select name="type" class="form-select" required><option value="IN">Stock In</option><option value="OUT">Stock Out</option></select></div><div class="col-6 mb-3"><label class="form-label">Quantity</label><input type="number" name="quantity" min="1" class="form-control" required></div></div><div class="mb-3"><label class="form-label">Reference No.</label><input name="reference_no" maxlength="100" class="form-control"></div><div><label class="form-label">Remarks</label><textarea name="remarks" maxlength="2000" rows="2" class="form-control"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Record Movement</button></div></form></div></div></div>
@endforeach
@endif

@if($canCreateAssets)
<div class="modal fade" id="addFixedAssetModal" tabindex="-1" aria-labelledby="addFixedAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFixedAssetModalLabel">Add Fixed Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="{{ route('yatira.inventory.fixed-assets.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_form_context" value="create_asset">

                <div class="modal-body">
                    @if($errors->any() && old('_form_context') === 'create_asset')
                        <div class="alert alert-danger" role="alert">
                            <strong>Unable to save the fixed asset.</strong>
                            <ul class="mb-0 mt-2 ps-3" style="list-style: disc;">
                                @foreach($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
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
                            <input type="text" name="asset_name" class="form-control @error('asset_name') is-invalid @enderror" value="{{ old('asset_name') }}" required>
                            @error('asset_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3"><label class="form-label">Serial Number</label><input type="text" name="serial_number" class="form-control" value="{{ old('serial_number') }}"></div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                <option value="">Select category</option>
                                @foreach($fixedAssetCategories as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">Assigned User <span class="text-muted">(Optional)</span></label><select id="fixedAssetAssignedUser" name="assigned_user_id" class="form-select @error('assigned_user_id') is-invalid @enderror"><option value="">None</option>@foreach($users as $user)<option value="{{ $user->id }}" data-department-id="{{ $user->department_id }}" @selected((string)old('assigned_user_id')===(string)$user->id)>{{ $user->name }} {{ $user->lastname }}</option>@endforeach</select>@error('assigned_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6 mb-3"><label class="form-label">Assigned Department <span class="text-muted">(Optional)</span></label><select id="fixedAssetAssignedDepartment" name="assigned_department_id" class="form-select @error('assigned_department_id') is-invalid @enderror"><option value="">None</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)old('assigned_department_id')===(string)$department->id)>{{ $department->name }}</option>@endforeach</select><small id="fixedAssetDepartmentHelp" class="text-muted">Choose a department only when no individual user is assigned.</small>@error('assigned_department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
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
                                @foreach(['Active', 'In Use', 'Under Maintenance'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', 'Active') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Acquired</label>
                            <input type="date" name="date_acquired" max="{{ today()->toDateString() }}" class="form-control @error('date_acquired') is-invalid @enderror" value="{{ old('date_acquired') }}">
                            @error('date_acquired')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">Acquisition Cost</label><input type="number" name="acquisition_cost" min="0" step="0.01" class="form-control" value="{{ old('acquisition_cost') }}"></div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="1">{{ old('remarks') }}</textarea>
                        </div>
                    </div>

                    <div class="yatira-upload-field mb-3 rounded-3 border bg-light p-3">
                        <label class="form-label">Supporting Document / Image</label>
                        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" class="form-control @error('document') is-invalid @enderror">
                        @error('document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted d-block mt-2">Upload a PDF, JPG or PNG file. Maximum size: 5 MB.</small>
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
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const entryType = document.getElementById('fixedAssetEntryType');
    const assetCode = document.getElementById('fixedAssetCode');
    const assetCodeHelp = document.getElementById('fixedAssetCodeHelp');
    const assignedUser = document.getElementById('fixedAssetAssignedUser');
    const assignedDepartment = document.getElementById('fixedAssetAssignedDepartment');
    const departmentHelp = document.getElementById('fixedAssetDepartmentHelp');

    document.querySelectorAll('form[action*="/consumables/"][action$="/movement"]').forEach((form) => {
        const context = document.createElement('input'); context.type = 'hidden'; context.name = '_form_context'; context.value = 'movement'; form.appendChild(context);
        const match = form.action.match(/consumables\/(\d+)\/movement$/); if (match) { const item = document.createElement('input'); item.type = 'hidden'; item.name = '_consumable_id'; item.value = match[1]; form.appendChild(item); }
    });

    if (!entryType || !assetCode || !assetCodeHelp) {
        return;
    }

    function syncAssetCodeField() {
        const isExisting = entryType.value === 'existing';

        assetCode.readOnly = !isExisting;
        assetCode.required = isExisting;
        assetCode.placeholder = isExisting
            ? 'Enter the existing asset tag/code'
            : 'Auto-generated on save (Example: YC-A1B2C3)';
        assetCodeHelp.textContent = isExisting
            ? 'Enter the code already printed or attached to this asset.'
            : 'The system will generate a unique Yatira asset code automatically.';

        if (!isExisting) {
            assetCode.value = '';
        }
    }

    entryType.addEventListener('change', syncAssetCodeField);
    syncAssetCodeField();

    function syncAssignedDepartment() {
        if (!assignedUser || !assignedDepartment) return;
        const selected = assignedUser.options[assignedUser.selectedIndex];
        const hasUser = assignedUser.value !== '';

        if (hasUser) {
            assignedDepartment.value = selected.dataset.departmentId || '';
            assignedDepartment.disabled = true;
            if (departmentHelp) departmentHelp.textContent = 'Automatically set from the selected user.';
        } else {
            assignedDepartment.disabled = false;
            if (departmentHelp) departmentHelp.textContent = 'Choose a department only when no individual user is assigned.';
        }
    }

    if (assignedUser && assignedDepartment) {
        assignedUser.addEventListener('change', syncAssignedDepartment);
        syncAssignedDepartment();
    }

});
</script>

@if($errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    let modalElement = document.getElementById('addFixedAssetModal');
    if (@json(old('_form_context')) === 'create_consumable') modalElement = document.getElementById('addConsumableModal');
    if (@json(old('_form_context')) === 'movement') modalElement = document.getElementById('movementModal' + @json(old('_consumable_id')));
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
});
</script>
@endif
@endsection
