@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('content')
<style>
    .vsli-shell {
        display: grid;
        gap: 24px;
    }

    .vsli-hero {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        padding: 32px;
        background:
            radial-gradient(circle at top right, rgba(255,255,255,0.18), transparent 28%),
            linear-gradient(135deg, #0b3954, #087e8b 55%, #bfd7ea);
        color: #fff;
        box-shadow: 0 20px 45px rgba(11, 57, 84, 0.18);
    }

    .vsli-hero h2,
    .vsli-hero p {
        position: relative;
        z-index: 1;
    }

    .vsli-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 18px;
        position: relative;
        z-index: 1;
    }

    .vsli-actions .btn {
        border-radius: 999px;
        padding-inline: 16px;
    }

    .vsli-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .vsli-filter-card {
        border: 1px solid #dbe7f3;
        border-radius: 20px;
        padding: 20px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.07);
    }

    .vsli-filter-form {
        display: grid;
        grid-template-columns: minmax(190px, 1.2fr) repeat(2, minmax(150px, 1fr)) auto;
        gap: 12px;
        align-items: end;
    }

    .vsli-filter-form label {
        color: #334155;
        font-size: 0.8rem;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .vsli-filter-form .form-select,
    .vsli-filter-form .form-control {
        min-height: 44px;
        border-color: #cbd5e1;
        border-radius: 12px;
    }

    .vsli-filter-actions {
        display: flex;
        gap: 8px;
    }

    .vsli-filter-actions .btn {
        min-height: 44px;
        border-radius: 12px;
        white-space: nowrap;
    }

    .vsli-period-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border-radius: 999px;
        background: #e8f4ff;
        color: #075985;
        padding: 7px 12px;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .vsli-card {
        border: 0;
        border-radius: 22px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        overflow: hidden;
        height: 100%;
    }

    .vsli-stat {
        padding: 22px;
        background: #fff;
    }

    .vsli-stat-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 18px;
    }

    .vsli-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .bg-soft-blue { background: rgba(13, 110, 253, 0.12); color: #0d6efd; }
    .bg-soft-green { background: rgba(25, 135, 84, 0.12); color: #198754; }
    .bg-soft-orange { background: rgba(255, 193, 7, 0.18); color: #9a6700; }
    .bg-soft-red { background: rgba(220, 53, 69, 0.12); color: #dc3545; }
    .bg-soft-cyan { background: rgba(13, 202, 240, 0.14); color: #0c8599; }
    .bg-soft-dark { background: rgba(33, 37, 41, 0.08); color: #212529; }

    .vsli-stat-label {
        font-size: 0.9rem;
        color: #64748b;
        margin-bottom: 6px;
    }

    .vsli-stat-value {
        font-size: 2rem;
        line-height: 1;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .vsli-stat-note {
        font-size: 0.9rem;
        color: #475569;
        margin: 0;
    }

    .vsli-section-card .card-body {
        padding: 24px;
    }

    .vsli-section-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }

    .vsli-section-title h4,
    .vsli-section-title h5 {
        margin: 0;
        color: #0f172a;
    }

    .vsli-subtext {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 0.92rem;
    }

    .vsli-chart-wrap {
        position: relative;
        min-height: 290px;
    }

    .vsli-mini-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
    }

    .vsli-mini-card {
        border-radius: 18px;
        padding: 18px;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        border: 1px solid #e2e8f0;
    }

    .vsli-mini-card .label {
        color: #64748b;
        font-size: 0.86rem;
        margin-bottom: 6px;
    }

    .vsli-mini-card .value {
        font-size: 1.45rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .vsli-table thead th {
        white-space: nowrap;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .vsli-table tbody td {
        vertical-align: middle;
    }

    .vsli-scroll-table {
        height: 320px;
        overflow-y: scroll;
        overflow-x: auto;
    }

    .vsli-scroll-table::-webkit-scrollbar {
        width: 8px;
    }

    .vsli-scroll-table::-webkit-scrollbar-thumb {
        background: rgba(100, 116, 139, 0.5);
        border-radius: 999px;
    }

    .vsli-scroll-table::-webkit-scrollbar-track {
        background: #f8fafc;
    }

    .vsli-alert-list {
        display: grid;
        gap: 12px;
    }

    .vsli-alert-item {
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: #fff;
    }

    .vsli-alert-item strong {
        color: #0f172a;
    }

    .vsli-eyebrow {
        color: #0f4c81;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .vsli-live-heading {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 16px;
    }

    .vsli-attention-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 13px 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .vsli-attention-item:last-child { border-bottom: 0; }

    .vsli-attention-count {
        min-width: 38px;
        padding: 5px 9px;
        border-radius: 999px;
        background: #fee2e2;
        color: #b91c1c;
        text-align: center;
        font-weight: 800;
    }

    #executive-fleet-map {
        height: min(620px, 68vh);
        min-height: 460px;
        border-radius: 18px;
        background: #dbeafe;
        overflow: hidden;
    }

    .vsli-dashboard-map-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 340px;
        gap: 14px;
        align-items: stretch;
    }

    .vsli-dashboard-map-layout.is-details-hidden { grid-template-columns: minmax(0, 1fr); }
    .vsli-dashboard-map-layout.is-details-hidden .vsli-map-details { display: none; }

    .vsli-map-details {
        height: min(620px, 68vh);
        min-height: 460px;
        overflow-y: auto;
        border: 1px solid #dbe7f3;
        border-radius: 18px;
        background: #f8fafc;
        padding: 14px;
    }

    .vsli-map-detail-card {
        width: 100%;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #fff;
        padding: 13px;
        text-align: left;
        transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
    }

    .vsli-map-detail-card:hover,
    .vsli-map-detail-card.is-selected {
        border-color: #24477f;
        background: #eff6ff;
        box-shadow: 0 0 0 2px #bfdbfe;
    }

    .vsli-map-marker {
        display: grid;
        place-items: center;
        width: 28px;
        height: 28px;
        color: #087e8b;
        font-size: 26px;
        line-height: 1;
        filter: drop-shadow(-1px -1px 0 #fff) drop-shadow(1px 1px 0 #fff) drop-shadow(0 3px 4px rgba(15, 23, 42, .5));
    }

    .vsli-map-marker.is-stale { color: #d97706; }
    .vsli-map-marker.is-completed { color: #475569; }

    .vsli-map-filter {
        display: inline-flex;
        gap: 6px;
        padding: 5px;
        border: 1px solid #dbe7f3;
        border-radius: 14px;
        background: #f8fafc;
    }

    .vsli-map-filter button {
        min-height: 36px;
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: #475569;
        padding: 7px 12px;
        font-size: .78rem;
        font-weight: 800;
    }

    .vsli-map-filter button.is-active {
        background: #24477f;
        color: #fff;
        box-shadow: 0 5px 12px rgba(36, 71, 127, .2);
    }

    .vsli-monitor-clock {
        min-width: 210px;
        border: 1px solid #dbe7f3;
        border-radius: 14px;
        background: #f8fafc;
        padding: 7px 12px;
        color: #0f274c;
        line-height: 1.15;
    }

    .vsli-monitor-clock strong { display: block; font-size: 1rem; font-variant-numeric: tabular-nums; }
    .vsli-monitor-clock span,
    .vsli-monitor-clock small { display: block; margin-top: 2px; color: #64748b; font-size: .68rem; font-weight: 700; }
    .vsli-live-dot { color: #059669; animation: vsli-live-pulse 1.8s ease-in-out infinite; }
    @keyframes vsli-live-pulse { 50% { opacity: .35; } }

    #live-fleet-map-section:fullscreen,
    #live-fleet-map-section.is-monitor-fullscreen {
        width: 100vw;
        height: 100vh;
        margin: 0;
        border: 0;
        border-radius: 0;
        background: #07162d;
        color: #fff;
        padding: 0;
    }
    #live-fleet-map-section.is-monitor-fullscreen { position: fixed; inset: 0; z-index: 9999; }

    #live-fleet-map-section:fullscreen .card-body,
    #live-fleet-map-section.is-monitor-fullscreen .card-body {
        display: flex;
        height: 100%;
        flex-direction: column;
        padding: 18px;
    }

    #live-fleet-map-section:fullscreen .vsli-section-title,
    #live-fleet-map-section.is-monitor-fullscreen .vsli-section-title { flex: 0 0 auto; margin-bottom: 12px; }
    #live-fleet-map-section:fullscreen .vsli-section-title h4,
    #live-fleet-map-section.is-monitor-fullscreen .vsli-section-title h4 { color: #fff; font-size: 1.45rem; }
    #live-fleet-map-section:fullscreen .vsli-subtext,
    #live-fleet-map-section.is-monitor-fullscreen .vsli-subtext { color: #b8cbe6; }
    #live-fleet-map-section:fullscreen .vsli-dashboard-map-layout,
    #live-fleet-map-section.is-monitor-fullscreen .vsli-dashboard-map-layout { flex: 1 1 auto; min-height: 0; grid-template-columns: minmax(0, 1fr) 370px; }
    #live-fleet-map-section:fullscreen #executive-fleet-map,
    #live-fleet-map-section:fullscreen .vsli-map-details,
    #live-fleet-map-section.is-monitor-fullscreen #executive-fleet-map,
    #live-fleet-map-section.is-monitor-fullscreen .vsli-map-details { height: 100%; min-height: 0; max-height: none; }
    #live-fleet-map-section:fullscreen .vsli-monitor-clock,
    #live-fleet-map-section.is-monitor-fullscreen .vsli-monitor-clock { border-color: #284463; background: #102744; color: #fff; }
    #live-fleet-map-section:fullscreen .vsli-monitor-clock span,
    #live-fleet-map-section:fullscreen .vsli-monitor-clock small,
    #live-fleet-map-section.is-monitor-fullscreen .vsli-monitor-clock span,
    #live-fleet-map-section.is-monitor-fullscreen .vsli-monitor-clock small { color: #b8cbe6; }

    .vsli-details {
        border: 1px solid #dbe7f3;
        border-radius: 22px;
        background: #f8fafc;
        overflow: hidden;
    }

    .vsli-details > summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 24px;
        cursor: pointer;
        list-style: none;
        color: #0f274c;
        font-weight: 800;
        flex-wrap: wrap;
    }

    .vsli-details > summary::-webkit-details-marker { display: none; }
    .vsli-details > summary::after { content: 'Show reports'; color: #64748b; font-size: .82rem; }
    .vsli-details[open] > summary::after { content: 'Hide reports'; }
    .vsli-details-content { display: grid; gap: 24px; padding: 0 18px 20px; }

    @media (max-width: 1100px) {
        .vsli-filter-form {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .vsli-hero {
            padding: 24px;
        }

        .vsli-stat-value {
            font-size: 1.7rem;
        }

        .vsli-section-title {
            align-items: flex-start;
            flex-direction: column;
        }

        .vsli-filter-form {
            grid-template-columns: 1fr;
        }

        .vsli-filter-actions .btn {
            flex: 1;
        }

        .vsli-live-heading { align-items: flex-start; flex-direction: column; }
        #executive-fleet-map { min-height: 360px; }
        .vsli-dashboard-map-layout { grid-template-columns: minmax(0, 1fr); }
        .vsli-map-details { height: auto; min-height: 0; max-height: 460px; }
    }
</style>

<div class="container-fluid px-0">
    <div class="vsli-shell">
        <section class="vsli-hero">
            @if(auth()->user()->isExecutiveViewer())
                <span class="mb-3 d-inline-flex align-items-center gap-2 rounded-pill bg-white bg-opacity-10 px-3 py-2 small fw-bold"><i class="bi bi-eye"></i> Read-only executive dashboard</span>
            @endif
            <div class="small fw-bold text-uppercase text-white-50 mb-2" style="letter-spacing:.12em;">Executive Dashboard</div>
            <h2 class="fw-bold mb-2">Villa Shipping Lines Command Center</h2>
            <p class="mb-0" style="max-width: 760px;">
                A clear view of where the fleet is, what needs attention, and how operations are performing.
            </p>
            <p class="mt-2 mb-0 small text-white-50">
                Last data update: {{ $lastDataUpdatedAt?->format('M d, Y h:i A') ?? 'No operational updates yet' }}
            </p>

            <div class="vsli-actions">
                <a href="{{ route('vessels.index') }}" class="btn btn-light btn-sm">
                    <i class="bi bi-ship me-1"></i> Vessels
                </a>
                <a href="{{ route('voyage-logs.dashboard') }}" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-map me-1"></i> Voyage Dashboard
                </a>
                <a href="#period-performance" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-bar-chart-line me-1"></i> Period Vessel Insights
                </a>
                <a href="{{ route('tech-defects.dashboard') }}" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-tools me-1"></i> Defects Dashboard
                </a>
                <a href="{{ route('vessel-certificates.dashboard') }}" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-file-earmark-check me-1"></i> Certificates
                </a>
                <a href="#fuel-monitoring" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-fuel-pump me-1"></i> Fuel Monitoring
                </a>
            </div>
        </section>

        <section class="card vsli-card vsli-section-card" id="live-fleet-map-section">
            <div class="card-body">
                <div class="vsli-section-title">
                    <div>
                        <div class="vsli-eyebrow">Live vessel tracking</div>
                        <h4>Voyage Tracking Map</h4>
                        <p class="vsli-subtext">Switch between active routes and the permanent tracking history of completed voyages.</p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                        <div class="vsli-monitor-clock" aria-live="polite">
                            <strong id="dashboard-live-time">--:--:--</strong>
                            <span id="dashboard-live-date">Loading Philippine time...</span>
                            <small><i class="bi bi-circle-fill vsli-live-dot me-1"></i><span class="d-inline" id="dashboard-live-sync">Connecting live data...</span></small>
                        </div>
                        <div class="vsli-map-filter" role="group" aria-label="Select voyage map history">
                            <button type="button" class="is-active" data-dashboard-map-filter="active" aria-pressed="true">Active Voyages</button>
                            <button type="button" data-dashboard-map-filter="previous" aria-pressed="false">Previous Voyages</button>
                        </div>
                        <span class="badge rounded-pill bg-light text-secondary px-3 py-2" id="dashboard-map-count">0 tracks</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="dashboard-map-details-toggle" aria-expanded="true"><i class="bi bi-layout-sidebar-reverse me-1"></i><span>Hide details</span></button>
                        <button type="button" class="btn btn-primary btn-sm" id="dashboard-map-fullscreen" aria-pressed="false"><i class="bi bi-arrows-fullscreen me-1"></i><span>Full screen</span></button>
                    </div>
                </div>
                <div class="vsli-dashboard-map-layout" id="dashboard-map-layout">
                    <div id="executive-fleet-map" aria-label="Current Villa Shipping vessel locations"></div>
                    <aside class="vsli-map-details" id="dashboard-map-details" aria-label="Voyage map details">
                        <div class="mb-3 d-flex align-items-center justify-content-between gap-2">
                            <div><div class="vsli-eyebrow">Voyage details</div><strong id="dashboard-map-details-title">Active Voyages</strong></div>
                            <i class="bi bi-list-ul text-muted"></i>
                        </div>
                        <div class="d-grid gap-2" id="dashboard-map-details-list"></div>
                    </aside>
                </div>
                <div class="alert alert-warning border mt-3 mb-0 py-3 d-none" id="dashboard-map-empty">
                    <i class="bi bi-geo-alt me-2"></i><span></span>
                </div>
            </div>
        </section>

        <section class="vsli-filter-card" aria-labelledby="dashboard-range-title">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 id="dashboard-range-title" class="mb-1 fw-bold text-dark">Dashboard time range</h5>
                    <p class="mb-0 small text-muted">Voyages, fuel, defects and operational activity follow this period.</p>
                </div>
                <span class="vsli-period-chip"><i class="bi bi-calendar3"></i>{{ $dashboardRange['label'] }}</span>
            </div>

            @if($errors->any())
                <div class="alert alert-danger py-2 mb-3" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="GET" action="{{ route('division.dashboard', 'Villa Shipping Lines') }}" class="vsli-filter-form" id="shipping-dashboard-filter">
                <div>
                    <label for="dashboard-range">Time range</label>
                    <select class="form-select" id="dashboard-range" name="range">
                        <option value="today" @selected($dashboardRange['key'] === 'today')>Today</option>
                        <option value="last_7_days" @selected($dashboardRange['key'] === 'last_7_days')>Last 7 days</option>
                        <option value="last_30_days" @selected($dashboardRange['key'] === 'last_30_days')>Last 30 days</option>
                        <option value="this_month" @selected($dashboardRange['key'] === 'this_month')>This month</option>
                        <option value="last_month" @selected($dashboardRange['key'] === 'last_month')>Last month</option>
                        <option value="this_year" @selected($dashboardRange['key'] === 'this_year')>This year</option>
                        <option value="all_time" @selected($dashboardRange['key'] === 'all_time')>All time</option>
                        <option value="custom" @selected($dashboardRange['key'] === 'custom')>Custom range</option>
                    </select>
                </div>
                <div class="vsli-custom-date">
                    <label for="dashboard-date-from">From</label>
                    <input class="form-control" id="dashboard-date-from" type="date" name="date_from" value="{{ old('date_from', $dashboardRange['key'] === 'custom' ? $dashboardRange['from'] : '') }}">
                </div>
                <div class="vsli-custom-date">
                    <label for="dashboard-date-to">To</label>
                    <input class="form-control" id="dashboard-date-to" type="date" name="date_to" value="{{ old('date_to', $dashboardRange['key'] === 'custom' ? $dashboardRange['to'] : '') }}">
                </div>
                <div class="vsli-filter-actions">
                    <a href="{{ route('division.dashboard', 'Villa Shipping Lines') }}" class="btn btn-outline-secondary">Reset</a>
                    <button class="btn btn-primary px-4" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
                </div>
            </form>
        </section>

        <div class="vsli-live-heading">
            <div>
                <div class="vsli-eyebrow">Live fleet snapshot</div>
                <h3 class="h4 mb-1">What is happening right now</h3>
                <p class="vsli-subtext">Current fleet, voyage, crew, and location status. These cards are not changed by the date filter.</p>
            </div>
            <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2"><i class="bi bi-broadcast me-1"></i> Live operational data</span>
        </div>

        <section class="vsli-grid">
            <div class="vsli-card">
                <div class="vsli-stat">
                    <div class="vsli-stat-top">
                        <div>
                            <div class="vsli-stat-label">Active Fleet</div>
                            <div class="vsli-stat-value">{{ number_format($totalVessels) }}</div>
                            <p class="vsli-stat-note">{{ number_format($activeVessels) }} marked active or operational</p>
                        </div>
                        <span class="vsli-icon bg-soft-blue"><i class="bi bi-ship"></i></span>
                    </div>
                    <a href="{{ route('vessels.index') }}" class="small text-decoration-none">Open vessel monitoring</a>
                </div>
            </div>

            <div class="vsli-card">
                <div class="vsli-stat">
                    <div class="vsli-stat-top">
                        <div>
                            <div class="vsli-stat-label">Ongoing Voyages</div>
                            <div class="vsli-stat-value">{{ number_format($liveOpenVoyages) }}</div>
                            <p class="vsli-stat-note">{{ number_format($liveSailingVoyages) }} sailing, {{ number_format($liveAnchoredVoyages) }} anchored</p>
                        </div>
                        <span class="vsli-icon bg-soft-green"><i class="bi bi-compass"></i></span>
                    </div>
                    <span class="small text-muted">Current non-completed voyages</span>
                </div>
            </div>

            <div class="vsli-card">
                <div class="vsli-stat">
                    <div class="vsli-stat-top">
                        <div>
                            <div class="vsli-stat-label">Crew On Board</div>
                            <div class="vsli-stat-value">{{ number_format($totalCrew) }}</div>
                            <p class="vsli-stat-note">Combined crew recorded on ongoing voyages</p>
                        </div>
                        <span class="vsli-icon bg-soft-cyan"><i class="bi bi-people"></i></span>
                    </div>
                    <span class="small text-muted">Crew snapshot from voyage entries</span>
                </div>
            </div>

            <div class="vsli-card">
                <div class="vsli-stat">
                    <div class="vsli-stat-top">
                        <div>
                            <div class="vsli-stat-label">Delayed Voyages</div>
                            <div class="vsli-stat-value {{ $delayedVoyages > 0 ? 'text-danger' : '' }}">{{ number_format($delayedVoyages) }}</div>
                            <p class="vsli-stat-note">Open voyages beyond the recorded ETA</p>
                        </div>
                        <span class="vsli-icon bg-soft-red"><i class="bi bi-clock-history"></i></span>
                    </div>
                    <span class="small text-muted">Requires operational follow-up</span>
                </div>
            </div>

            <div class="vsli-card">
                <div class="vsli-stat">
                    <div class="vsli-stat-top">
                        <div>
                            <div class="vsli-stat-label">Location Updates</div>
                            <div class="vsli-stat-value">{{ number_format($activeVoyageMapPoints->count()) }}</div>
                            <p class="vsli-stat-note">{{ number_format($staleLocationCount) }} not updated within 24 hours</p>
                        </div>
                        <span class="vsli-icon bg-soft-orange"><i class="bi bi-geo-alt"></i></span>
                    </div>
                    <a href="#executive-fleet-map" class="small text-decoration-none">View current vessel positions</a>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-5">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <div class="vsli-eyebrow">Needs attention</div>
                                <h4>Priority action list</h4>
                                <p class="vsli-subtext">Items the President and Operations team should review first.</p>
                            </div>
                        </div>
                        <div class="vsli-attention-item"><span><i class="bi bi-exclamation-octagon text-danger me-2"></i>Critical open defects</span><span class="vsli-attention-count">{{ $criticalOpenDefects }}</span></div>
                        <div class="vsli-attention-item"><span><i class="bi bi-calendar-x text-danger me-2"></i>Overdue defects</span><span class="vsli-attention-count">{{ $liveOverdueDefects }}</span></div>
                        <div class="vsli-attention-item"><span><i class="bi bi-file-earmark-x text-danger me-2"></i>Expired certificates</span><span class="vsli-attention-count">{{ $expiredCertificates }}</span></div>
                        <div class="vsli-attention-item"><span><i class="bi bi-file-earmark-clock text-warning me-2"></i>Certificates due in 30 days</span><span class="vsli-attention-count">{{ $expiringCertificates }}</span></div>
                        <div class="vsli-attention-item"><span><i class="bi bi-fuel-pump text-warning me-2"></i>Low-fuel voyages</span><span class="vsli-attention-count">{{ $lowFuelVoyages->count() }}</span></div>
                        <div class="vsli-attention-item"><span><i class="bi bi-geo-alt text-warning me-2"></i>Stale vessel locations</span><span class="vsli-attention-count">{{ $staleLocationCount }}</span></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-7" id="period-performance">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <div class="vsli-eyebrow">Selected period</div>
                                <h4>Performance summary</h4>
                                <p class="vsli-subtext">Results for {{ $dashboardRange['label'] }} only.</p>
                            </div>
                            <span class="vsli-period-chip"><i class="bi bi-calendar3"></i>{{ $dashboardRange['label'] }}</span>
                        </div>
                        <div class="vsli-mini-grid">
                            <div class="vsli-mini-card">
                                <div class="label">Voyages</div>
                                <div class="value">{{ number_format($totalVoyages) }}</div>
                                <div class="small text-muted">{{ $completedVoyages }} completed, {{ $openVoyages }} still open</div>
                            </div>
                            <div class="vsli-mini-card">
                                <div class="label">Fuel Consumed</div>
                                <div class="value">{{ number_format($totalFuelConsumed, 2) }} L</div>
                                <div class="small text-muted">Across monitoring logs in period</div>
                            </div>
                    <div class="vsli-mini-card">
                        <div class="label">Active Defects</div>
                        <div class="value">{{ number_format($activeDefects) }}</div>
                        <div class="small text-muted">Reports not yet closed</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Overdue Defects</div>
                        <div class="value text-danger">{{ number_format($overdueDefects) }}</div>
                        <div class="small text-muted">Past target completion date</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Defect Closure Rate</div>
                        <div class="value">{{ number_format($defectClosureRate, 1) }}%</div>
                        <div class="small text-muted">Closed reports in selected period</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Certificate Compliance <span class="badge bg-light text-secondary ms-1">Live</span></div>
                        <div class="value">{{ number_format($certificateComplianceRate, 1) }}%</div>
                        <div class="small text-muted">Effective certificates valid beyond 30 days</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Fuel per Completed Voyage</div>
                        <div class="value">{{ number_format($fuelPerCompletedVoyage, 2) }} L</div>
                        <div class="small text-muted">Selected-period consumption efficiency</div>
                    </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-7">
                <div class="card vsli-card vsli-section-card">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h4>Operations Overview</h4>
                                <p class="vsli-subtext">Quick overview of voyage volume and defect distribution.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-7">
                                <div class="vsli-chart-wrap">
                                    <canvas id="voyageTrendChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="vsli-chart-wrap">
                                    <canvas id="defectStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h4>Compliance Snapshot</h4>
                                <p class="vsli-subtext">Certificate health summary for fleet compliance.</p>
                            </div>
                        </div>

                        <div class="vsli-mini-grid">
                            <div class="vsli-mini-card">
                                <div class="label">Valid Certificates</div>
                                <div class="value">{{ number_format($validCertificates) }}</div>
                                <div class="small text-muted">Beyond 30 days before expiry</div>
                            </div>
                            <div class="vsli-mini-card">
                                <div class="label">Expiring Soon</div>
                                <div class="value">{{ number_format($expiringCertificates) }}</div>
                                <div class="small text-muted">Needs follow-up within 30 days</div>
                            </div>
                            <div class="vsli-mini-card">
                                <div class="label">Expired</div>
                                <div class="value">{{ number_format($expiredCertificates) }}</div>
                                <div class="small text-muted">Priority for immediate action</div>
                            </div>
                            <div class="vsli-mini-card">
                                <div class="label">Fuel Updates Today</div>
                                <div class="value">{{ number_format($fuelUpdatesToday) }}</div>
                                <div class="small text-muted">ROB or bunkering logs posted today</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="vsli-alert-list">
                            @forelse($certificateAlerts as $certificate)
                                <div class="vsli-alert-item">
                                    <strong>{{ $certificate->vessel?->vessel_name ?? 'Unknown Vessel' }}</strong><br>
                                    <span class="text-muted">{{ $certificate->certificate_name }}</span><br>
                                    <span class="small text-muted">Expiry: {{ optional($certificate->expiry_date)->format('M d, Y') ?? '-' }}</span>
                                </div>
                            @empty
                                <div class="vsli-alert-item">
                                    <strong>No immediate certificate alerts.</strong><br>
                                    <span class="small text-muted">No due or expired certificates in the current summary.</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <details class="vsli-details" id="detailed-operational-reports">
            <summary>
                <span><i class="bi bi-table me-2"></i>Detailed operational reports</span>
                <small class="text-muted fw-normal">Charts and record-level tables for deeper review</small>
            </summary>
            <div class="vsli-details-content">
        <section id="monthly-vessel-insights" class="row g-4">
            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Voyages Per Vessel</h5>
                                <p class="vsli-subtext">Voyages per vessel within {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="vsli-chart-wrap mb-4">
                            <canvas id="monthlyVoyageVesselChart"></canvas>
                        </div>

                        <div class="table-responsive vsli-scroll-table">
                            <table class="table table-hover align-middle vsli-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Voyages</th>
                                        <th>Total Voyage Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($monthlyVoyagesPerVessel as $vesselVoyage)
                                        <tr>
                                            <td>{{ $vesselVoyage['vessel_name'] }}</td>
                                            <td>{{ number_format($vesselVoyage['total_voyages']) }}</td>
                                            <td>{{ number_format($vesselVoyage['total_voyage_hours'], 2) }} hrs</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">No voyage data found for this period.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Fuel Per Vessel</h5>
                                <p class="vsli-subtext">Fuel consumed and received per vessel within {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="vsli-chart-wrap mb-4">
                            <canvas id="monthlyFuelVesselChart"></canvas>
                        </div>

                        <div class="table-responsive vsli-scroll-table">
                            <table class="table table-hover align-middle vsli-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Consumed</th>
                                        <th>Received</th>
                                        <th>Avg / Log</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($monthlyFuelByVessel as $fuelSummary)
                                        <tr>
                                            <td>{{ $fuelSummary['vessel_name'] }}</td>
                                            <td>{{ number_format($fuelSummary['total_consumed'], 2) }} L</td>
                                            <td>{{ number_format($fuelSummary['total_received'], 2) }} L</td>
                                            <td>{{ number_format($fuelSummary['average_consumed'], 2) }} L</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No fuel data found for this period.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Average Voyage Duration by Origin Port</h5>
                                <p class="vsli-subtext">Average voyage hours grouped by origin port for {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="vsli-chart-wrap">
                            <canvas id="averageTurnaroundChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Loading & Unloading Duration</h5>
                                <p class="vsli-subtext">Combined loading and unloading hours per vessel for {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="vsli-chart-wrap">
                            <canvas id="loadingUnloadingChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="fuel-monitoring" class="card vsli-card vsli-section-card">
            <div class="card-body">
                <div class="vsli-section-title">
                    <div>
                        <h4>Fuel Monitoring Dashboard</h4>
                        <p class="vsli-subtext">Consumption and bunkering follow {{ $currentMonthLabel }}; the low-fuel watchlist is live.</p>
                    </div>
                </div>

                <div class="vsli-mini-grid mb-4">
                    <div class="vsli-mini-card">
                        <div class="label">Total Fuel Consumed</div>
                        <div class="value">{{ number_format($totalFuelConsumed, 2) }}</div>
                        <div class="small text-muted">Liters consumed from monitoring logs</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Total Fuel Received</div>
                        <div class="value">{{ number_format($totalFuelReceived, 2) }}</div>
                        <div class="small text-muted">Liters added through bunkering</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Average Consumption</div>
                        <div class="value">{{ number_format($averageFuelConsumed, 2) }}</div>
                        <div class="small text-muted">Average per fuel monitoring record</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Low Fuel Voyages <span class="badge bg-light text-secondary ms-1">Live</span></div>
                        <div class="value">{{ number_format($lowFuelVoyages->count()) }}</div>
                        <div class="small text-muted">Voyages below 1,000 liters remaining</div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-xl-4">
                        <div class="vsli-chart-wrap">
                            <canvas id="fuelEngineChart"></canvas>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="vsli-chart-wrap">
                            <canvas id="topFuelVesselChart"></canvas>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="h-100 p-3 rounded-4" style="background: linear-gradient(180deg, #fff7ed, #ffffff); border: 1px solid #fed7aa;">
                            <h5 class="mb-3">Low Fuel Watchlist</h5>
                            <div class="vsli-alert-list">
                                @forelse($lowFuelVoyages as $voyage)
                                    <div class="vsli-alert-item" style="background:#fffaf5; border-color:#fdba74;">
                                        <strong>{{ $voyage->vessel?->vessel_name ?? 'Unknown Vessel' }}</strong><br>
                                        <span class="small text-muted">Voyage {{ $voyage->voyage_no ?? ('VL-' . $voyage->voyage_id) }}</span><br>
                                        <span class="small text-danger">{{ number_format((float) $voyage->fuel_balance, 2) }} Liters remaining</span>
                                    </div>
                                @empty
                                    <div class="vsli-alert-item" style="background:#fffaf5; border-color:#fed7aa;">
                                        <strong>No low fuel alerts.</strong><br>
                                        <span class="small text-muted">All monitored voyages are above the current threshold.</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="vsli-section-title mb-3">
                    <div>
                        <h5>Selected Period Snapshot</h5>
                        <p class="vsli-subtext">Overview of voyages, fuel, voyage duration, and loading activities for the chosen period.</p>
                    </div>
                </div>

                <div class="vsli-mini-grid">
                    <div class="vsli-mini-card">
                        <div class="label">Period Covered</div>
                        <div class="value">{{ $currentMonthLabel }}</div>
                        <div class="small text-muted">Current dashboard filter</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Voyages in Period</div>
                        <div class="value">{{ number_format($monthlyVoyageSummary) }}</div>
                        <div class="small text-muted">All voyage logs created within {{ $currentMonthLabel }}</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Vessels With Fuel Logs</div>
                        <div class="value">{{ number_format($monthlyFuelByVessel->count()) }}</div>
                        <div class="small text-muted">Vessels with fuel activity in this period</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Origin Ports Tracked</div>
                        <div class="value">{{ number_format($turnaroundPerPort->count()) }}</div>
                        <div class="small text-muted">Origin ports with recorded voyage hours</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Voyage Duration by Origin Port</h5>
                                <p class="vsli-subtext">Average and total voyage hours grouped by origin port for {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="vsli-chart-wrap mb-4">
                            <canvas id="turnaroundPortChart"></canvas>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle vsli-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Port Location</th>
                                        <th>Voyages</th>
                                        <th>Avg Voyage Duration</th>
                                        <th>Total Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($turnaroundPerPort as $turnaround)
                                        <tr>
                                            <td>{{ $turnaround['location_name'] }}</td>
                                            <td>{{ number_format($turnaround['total_voyages']) }}</td>
                                            <td>{{ number_format($turnaround['average_turnaround_hours'], 2) }} hrs</td>
                                            <td>{{ number_format($turnaround['total_turnaround_hours'], 2) }} hrs</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No voyage-duration data found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Loading Duration Per Vessel</h5>
                                <p class="vsli-subtext">Loading activities duration per vessel for {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle vsli-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Loading Activities</th>
                                        <th>Total Duration</th>
                                        <th>Avg Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($loadingDurationByVessel as $loading)
                                        <tr>
                                            <td>{{ $loading['vessel_name'] }}</td>
                                            <td>{{ number_format($loading['total_activities']) }}</td>
                                            <td>{{ number_format($loading['total_duration_hours'], 2) }} hrs</td>
                                            <td>{{ number_format($loading['average_duration_hours'], 2) }} hrs</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No loading activity data found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Unloading Duration Per Vessel</h5>
                                <p class="vsli-subtext">Unloading activities duration per vessel for {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle vsli-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Unloading Activities</th>
                                        <th>Total Duration</th>
                                        <th>Avg Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($unloadingDurationByVessel as $unloading)
                                        <tr>
                                            <td>{{ $unloading['vessel_name'] }}</td>
                                            <td>{{ number_format($unloading['total_activities']) }}</td>
                                            <td>{{ number_format($unloading['total_duration_hours'], 2) }} hrs</td>
                                            <td>{{ number_format($unloading['average_duration_hours'], 2) }} hrs</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No unloading activity data found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Voyage Logs in Period</h5>
                                <p class="vsli-subtext">Latest voyage headers within {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="table-responsive vsli-scroll-table">
                            <table class="table table-hover align-middle vsli-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Voyage</th>
                                        <th>Vessel</th>
                                        <th>Status</th>
                                        <th>Fuel ROB</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentVoyages as $voyage)
                                        <tr>
                                            <td>{{ $voyage->voyage_no ?? ('VL-' . str_pad($voyage->voyage_id, 5, '0', STR_PAD_LEFT)) }}</td>
                                            <td>{{ $voyage->vessel?->vessel_name ?? '-' }}</td>
                                            <td>
                                                <span class="badge {{ $voyage->status === 'COMPLETED' ? 'bg-success' : 'bg-warning text-dark' }}">
                                                    {{ $voyage->status ?? '-' }}
                                                </span>
                                            </td>
                                            <td>{{ $voyage->fuel_rob ?? '-' }}</td>
                                            <td>{{ optional($voyage->date_created)->format('M d, Y') ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No voyage records found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Cargo Voyages in Period</h5>
                                <p class="vsli-subtext">Cargo movements recorded within {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle vsli-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Cargo</th>
                                        <th>Quantity</th>
                                        <th>From</th>
                                        <th>To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentCargoVoyages as $voyage)
                                        <tr>
                                            <td>{{ $voyage->vessel?->vessel_name ?? '-' }}</td>
                                            <td>{{ $voyage->cargo_type ?? '-' }}</td>
                                            <td>{{ $voyage->cargo_volume ?? '-' }}</td>
                                            <td>{{ $voyage->port_location ?? '-' }}</td>
                                            <td>{{ $voyage->port_destination ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No cargo voyage records found for {{ $currentMonthLabel }}.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Fuel Monitoring in Period</h5>
                                <p class="vsli-subtext">Latest ROB and bunkering entries within {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle vsli-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Voyage</th>
                                        <th>Consumed</th>
                                        <th>Received</th>
                                        <th>Remaining</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentFuelMonitorings as $fuel)
                                        <tr>
                                            <td>{{ $fuel->vessel?->vessel_name ?? '-' }}</td>
                                            <td>{{ $fuel->voyage?->voyage_no ?? ('VL-' . str_pad((int) $fuel->voyage_id, 5, '0', STR_PAD_LEFT)) }}</td>
                                            <td>{{ number_format((float) $fuel->total_consumed, 2) }}</td>
                                            <td>{{ number_format((float) $fuel->received_fuel, 2) }}</td>
                                            <td>{{ number_format((float) $fuel->remaining_fuel, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No fuel monitoring records found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Activities in Period</h5>
                                <p class="vsli-subtext">Latest voyage activities within {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle vsli-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Activity</th>
                                        <th>Vessel</th>
                                        <th>Status</th>
                                        <th>Started</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentActivities as $activity)
                                        <tr>
                                            <td>{{ $activity->activity?->name ?? '-' }}</td>
                                            <td>{{ $activity->vessel?->vessel_name ?? '-' }}</td>
                                            <td>
                                                <span class="badge {{ strtoupper((string) $activity->main_status) === 'COMPLETED' ? 'bg-success' : 'bg-primary' }}">
                                                    {{ $activity->main_status ?? 'ONGOING' }}
                                                </span>
                                            </td>
                                            <td>{{ optional($activity->start_date_time)->format('M d, Y h:i A') ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No recent activities found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card vsli-card vsli-section-card h-100">
                    <div class="card-body">
                        <div class="vsli-section-title">
                            <div>
                                <h5>Tech Defects in Period</h5>
                                <p class="vsli-subtext">Latest defect reports within {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle vsli-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Status</th>
                                        <th>Severity</th>
                                        <th>Identified</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentDefects as $defect)
                                        <tr>
                                            <td>{{ $defect->vessel?->vessel_name ?? '-' }}</td>
                                            <td>{{ $defect->status ?? '-' }}</td>
                                            <td>
                                                <span class="badge {{ strtolower((string) $defect->severity_level) === 'critical' ? 'bg-danger' : 'bg-secondary' }}">
                                                    {{ $defect->severity_level ?? '-' }}
                                                </span>
                                            </td>
                                            <td>{{ optional($defect->date_identified)->format('M d, Y') ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No defect reports found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
            </div>
        </details>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rangeSelect = document.getElementById('dashboard-range');
    const customDateFields = document.querySelectorAll('.vsli-custom-date');
    const customDateInputs = document.querySelectorAll('.vsli-custom-date input');
    const syncCustomDateFields = () => {
        const customSelected = rangeSelect?.value === 'custom';

        customDateFields.forEach((field) => field.classList.toggle('d-none', !customSelected));
        customDateInputs.forEach((input) => {
            input.disabled = !customSelected;
            input.required = customSelected;
        });
    };

    rangeSelect?.addEventListener('change', syncCustomDateFields);
    syncCustomDateFields();

    const monthlyVoyageLabels = {{ Illuminate\Support\Js::from($monthlyVoyages->pluck('label')->values()) }};
    const monthlyVoyageData = {{ Illuminate\Support\Js::from($monthlyVoyages->pluck('total')->values()) }};
    const defectStatusLabels = {{ Illuminate\Support\Js::from($defectStatusLabels) }};
    const defectStatusData = {{ Illuminate\Support\Js::from($defectStatusData) }};
    const fuelEngineLabels = {{ Illuminate\Support\Js::from($fuelEngineLabels) }};
    const fuelEngineData = {{ Illuminate\Support\Js::from($fuelEngineData) }};
    const topFuelVesselLabels = {{ Illuminate\Support\Js::from($topFuelVesselLabels) }};
    const topFuelVesselData = {{ Illuminate\Support\Js::from($topFuelVesselData) }};
    const monthlyVoyageVesselLabels = {{ Illuminate\Support\Js::from($monthlyVoyageVesselLabels) }};
    const monthlyVoyageVesselData = {{ Illuminate\Support\Js::from($monthlyVoyageVesselData) }};
    const monthlyFuelVesselLabels = {{ Illuminate\Support\Js::from($monthlyFuelVesselLabels) }};
    const monthlyFuelVesselData = {{ Illuminate\Support\Js::from($monthlyFuelVesselData) }};
    const turnaroundPortLabels = {{ Illuminate\Support\Js::from($turnaroundPortLabels) }};
    const turnaroundPortData = {{ Illuminate\Support\Js::from($turnaroundPortData) }};
    const loadingUnloadingLabels = {{ Illuminate\Support\Js::from($loadingUnloadingLabels) }};
    const loadingDurationChartData = {{ Illuminate\Support\Js::from($loadingDurationChartData) }};
    const unloadingDurationChartData = {{ Illuminate\Support\Js::from($unloadingDurationChartData) }};

    new Chart(document.getElementById('voyageTrendChart'), {
        type: 'bar',
        data: {
            labels: monthlyVoyageLabels,
            datasets: [{
                label: 'Voyages',
                data: monthlyVoyageData,
                backgroundColor: '#0d6efd',
                borderRadius: 10,
                maxBarThickness: 42
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    new Chart(document.getElementById('defectStatusChart'), {
        type: 'doughnut',
        data: {
            labels: defectStatusLabels,
            datasets: [{
                data: defectStatusData,
                backgroundColor: ['#2563eb', '#7c3aed', '#0891b2', '#f59e0b', '#ea580c', '#dc2626', '#16a34a'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    new Chart(document.getElementById('fuelEngineChart'), {
        type: 'doughnut',
        data: {
            labels: fuelEngineLabels,
            datasets: [{
                data: fuelEngineData,
                backgroundColor: ['#0d6efd', '#20c997', '#ffc107', '#6f42c1'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    new Chart(document.getElementById('topFuelVesselChart'), {
        type: 'bar',
        data: {
            labels: topFuelVesselLabels,
            datasets: [{
                label: 'Fuel Consumed',
                data: topFuelVesselData,
                backgroundColor: '#087e8b',
                borderRadius: 10,
                maxBarThickness: 38
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });

    new Chart(document.getElementById('monthlyVoyageVesselChart'), {
        type: 'bar',
        data: {
            labels: monthlyVoyageVesselLabels,
            datasets: [{
                label: 'Voyages',
                data: monthlyVoyageVesselData,
                backgroundColor: '#f59e0b',
                borderRadius: 10,
                maxBarThickness: 42
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    new Chart(document.getElementById('monthlyFuelVesselChart'), {
        type: 'line',
        data: {
            labels: monthlyFuelVesselLabels,
            datasets: [{
                label: 'Fuel Consumed',
                data: monthlyFuelVesselData,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.12)',
                tension: 0.35,
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    new Chart(document.getElementById('turnaroundPortChart'), {
        type: 'bar',
        data: {
            labels: turnaroundPortLabels,
            datasets: [{
                label: 'Avg Voyage Hours',
                data: turnaroundPortData,
                backgroundColor: '#14b8a6',
                borderRadius: 10,
                maxBarThickness: 40
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });

    new Chart(document.getElementById('averageTurnaroundChart'), {
        type: 'bar',
        data: {
            labels: turnaroundPortLabels,
            datasets: [{
                label: 'Average Voyage Hours',
                data: turnaroundPortData,
                backgroundColor: ['#0f4c81', '#155e9c', '#1d70b8', '#2d87d3', '#4a9ce0', '#72b4ea', '#99caf3', '#bedef9'],
                borderRadius: 10,
                maxBarThickness: 46
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    new Chart(document.getElementById('loadingUnloadingChart'), {
        type: 'bar',
        data: {
            labels: loadingUnloadingLabels,
            datasets: [{
                label: 'Loading',
                data: loadingDurationChartData,
                backgroundColor: '#f97316',
                borderRadius: 8,
                borderSkipped: false
            }, {
                label: 'Unloading',
                data: unloadingDurationChartData,
                backgroundColor: '#0d6efd',
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    stacked: true
                },
                y: {
                    stacked: true
                }
            }
        }
    });
});
</script>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const details = document.getElementById('detailed-operational-reports');
    details?.addEventListener('toggle', () => {
        if (!details.open || typeof Chart === 'undefined') return;
        window.setTimeout(() => {
            details.querySelectorAll('canvas').forEach((canvas) => Chart.getChart(canvas)?.resize());
        }, 80);
    });
    document.querySelectorAll('a[href="#fuel-monitoring"], a[href="#monthly-vessel-insights"]').forEach((link) => {
        link.addEventListener('click', () => {
            if (details) details.open = true;
        });
    });

    const mapElement = document.getElementById('executive-fleet-map');
    let tracks = {{ Illuminate\Support\Js::from($dashboardVoyageTracks) }};
    const liveFleetUrl = {{ Illuminate\Support\Js::from(route('division.dashboard.live-fleet', 'Villa Shipping Lines')) }};
    const mapSection = document.getElementById('live-fleet-map-section');
    const fullscreenButton = document.getElementById('dashboard-map-fullscreen');
    const liveTime = document.getElementById('dashboard-live-time');
    const liveDate = document.getElementById('dashboard-live-date');
    const liveSync = document.getElementById('dashboard-live-sync');

    if (!mapElement) return;
    if (typeof L === 'undefined') {
        mapElement.classList.add('d-flex', 'align-items-center', 'justify-content-center', 'text-muted');
        mapElement.textContent = 'The live map could not be loaded. Check the internet connection and refresh the page.';
        return;
    }

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[character]);
    const map = L.map(mapElement, { scrollWheelZoom: false }).setView([12.4, 122.2], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const layers = {
        active: L.layerGroup(),
        previous: L.layerGroup(),
    };
    const groupBounds = { active: [], previous: [] };
    const voyageFeatures = new Map();
    const mapLayout = document.getElementById('dashboard-map-layout');
    const detailsToggle = document.getElementById('dashboard-map-details-toggle');
    const detailsTitle = document.getElementById('dashboard-map-details-title');
    const detailsList = document.getElementById('dashboard-map-details-list');
    const colors = ['#24477f', '#0f766e', '#7c3aed', '#be123c', '#b45309', '#0369a1'];
    const coordinates = (point) => {
        const latitude = Number(point?.lat);
        const longitude = Number(point?.lng);
        return Number.isFinite(latitude) && Number.isFinite(longitude) ? [latitude, longitude] : null;
    };
    const addUnique = (collection, point) => {
        if (point && (!collection.length || collection.at(-1)[0] !== point[0] || collection.at(-1)[1] !== point[1])) {
            collection.push(point);
        }
    };
    const shipIcon = (completed) => L.divIcon({
        className: '',
        html: `<span class="vsli-map-marker ${completed ? 'is-completed' : ''}" title="Current vessel location"><svg viewBox="0 0 36 32" width="28" height="28" aria-hidden="true"><path fill="currentColor" stroke="#fff" stroke-width="1.2" stroke-linejoin="round" d="M3 17.5h27.5l-4.8 8H8.2L3 17.5Z"/><path fill="currentColor" stroke="#fff" stroke-width="1.1" stroke-linejoin="round" d="M8 13.5h17l5.5 4H3l5-4Zm2-7h11v7H10v-7Zm11 3h5v4h-5v-4Z"/><path fill="#fff" d="M12 8.5h2.6v2.2H12zm4.2 0h2.6v2.2h-2.6zm6.2 2.5h2v1.5h-2z"/><path fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M7 28.5c2 1.1 4 1.1 6 0s4-1.1 6 0 4 1.1 6 0"/></svg></span>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -14],
    });

    const rebuildMapLayers = (nextTracks) => {
        tracks = Array.isArray(nextTracks) ? nextTracks : [];
        Object.values(layers).forEach((layer) => layer.clearLayers());
        groupBounds.active.length = 0;
        groupBounds.previous.length = 0;
        voyageFeatures.clear();

        tracks.forEach((voyage, index) => {
        const mode = voyage.completed ? 'previous' : 'active';
        const layer = layers[mode];
        const color = colors[index % colors.length];
        const origin = coordinates(voyage.origin);
        const destination = coordinates(voyage.destination);
        const savedPositions = (voyage.positions || []).map(coordinates).filter(Boolean);
        const current = coordinates(voyage.current) || savedPositions.at(-1) || origin;
        const actualTrack = [];
        addUnique(actualTrack, origin);
        savedPositions.forEach((point) => addUnique(actualTrack, point));
        addUnique(actualTrack, current);

        let trackLine = null;
        if (actualTrack.length > 1) {
            trackLine = L.polyline(actualTrack, {
                color: voyage.completed ? color : '#087e8b',
                weight: 5,
                opacity: .88,
            }).addTo(layer);
        }

        if (!voyage.completed && current && destination) {
            L.polyline([current, destination], {
                color: '#0891b2',
                weight: 3,
                opacity: .72,
                dashArray: '9 9',
            }).addTo(layer);
        }

        if (origin) {
            L.circleMarker(origin, { radius: 7, color: '#fff', weight: 3, fillColor: '#059669', fillOpacity: 1 })
                .addTo(layer)
                .bindPopup(`<strong>Origin</strong><br>${escapeHtml(voyage.origin?.name || 'Not named')}<br>${escapeHtml(voyage.vessel)}`);
        }
        if (destination) {
            L.circleMarker(destination, { radius: 7, color: '#fff', weight: 3, fillColor: '#dc2626', fillOpacity: 1 })
                .addTo(layer)
                .bindPopup(`<strong>Destination</strong><br>${escapeHtml(voyage.destination?.name || 'Not named')}<br>${escapeHtml(voyage.vessel)}`);
        }
        let vesselMarker = null;
        if (current) {
            vesselMarker = L.marker(current, { icon: shipIcon(voyage.completed), zIndexOffset: 1000 })
                .addTo(layer)
                .bindPopup(`
                    <div style="min-width:220px">
                        <strong>${escapeHtml(voyage.vessel)}</strong><br>
                        <span>${escapeHtml(voyage.voyage)} &middot; ${escapeHtml(voyage.status)}</span><hr class="my-2">
                        <strong>${voyage.completed ? 'Final' : 'Current'}:</strong> ${escapeHtml(voyage.current?.name || voyage.positions?.at(-1)?.name || 'Position recorded')}<br>
                        <strong>Destination:</strong> ${escapeHtml(voyage.destination?.name || 'Not set')}<br>
                        <small class="text-muted">Last update: ${escapeHtml(voyage.last_update || 'Unknown')}</small>
                    </div>
                `);
        }

        const voyageBounds = [...actualTrack, destination].filter(Boolean);
        voyageBounds.forEach((point) => groupBounds[mode].push(point));
            voyageFeatures.set(String(voyage.id), { voyage, mode, bounds: voyageBounds, trackLine, vesselMarker });
        });

        voyageFeatures.forEach((feature, voyageId) => {
            feature.trackLine?.on('click', () => focusVoyage(voyageId, false));
            feature.vesselMarker?.on('click', () => focusVoyage(voyageId, false));
        });
    };

    const filterButtons = document.querySelectorAll('[data-dashboard-map-filter]');
    const countBadge = document.getElementById('dashboard-map-count');
    const emptyMessage = document.getElementById('dashboard-map-empty');
    const detailValue = (value, fallback = 'Not recorded') => {
        const normalized = String(value ?? '').trim();
        return normalized ? escapeHtml(normalized) : fallback;
    };
    const fuelValue = (value, fallback = 'Not recorded') => {
        const amount = Number(value);
        return value !== null && value !== '' && Number.isFinite(amount)
            ? `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} L`
            : fallback;
    };
    const focusVoyage = (voyageId, openPopup = true) => {
        const feature = voyageFeatures.get(String(voyageId));
        if (!feature) return;

        detailsList?.querySelectorAll('[data-dashboard-voyage]').forEach((card) => {
            card.classList.toggle('is-selected', card.dataset.dashboardVoyage === String(voyageId));
        });
        const selectedCard = detailsList?.querySelector(`[data-dashboard-voyage="${CSS.escape(String(voyageId))}"]`);
        selectedCard?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        if (feature.bounds.length === 1) map.setView(feature.bounds[0], 10);
        else if (feature.bounds.length > 1) map.fitBounds(feature.bounds, { padding: [50, 50], maxZoom: 10 });
        if (openPopup) feature.vesselMarker?.openPopup();
    };
    const renderVoyageDetails = (mode) => {
        if (!detailsList || !detailsTitle) return;
        const visibleVoyages = tracks.filter((voyage) => (voyage.completed ? 'previous' : 'active') === mode);
        detailsTitle.textContent = mode === 'active' ? 'Active Voyages' : 'Previous Voyages';

        if (!visibleVoyages.length) {
            detailsList.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-map d-block fs-3 mb-2"></i>No voyage details available.</div>';
            return;
        }

        detailsList.innerHTML = visibleVoyages.map((voyage) => {
            const locationLabel = voyage.completed ? 'Final location' : 'Current location';
            const dateLabel = voyage.completed ? 'Completed' : 'ETA';
            const dateValue = voyage.completed ? voyage.completed_at : voyage.eta;
            return `
                <button type="button" class="vsli-map-detail-card" data-dashboard-voyage="${escapeHtml(voyage.id)}">
                    <span class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <span><strong class="d-block text-dark">${detailValue(voyage.vessel, 'Unknown vessel')}</strong><small class="text-muted">${detailValue(voyage.voyage, 'Voyage not set')}</small></span>
                        <span class="badge rounded-pill ${voyage.completed ? 'bg-secondary' : 'bg-success'}">${detailValue(voyage.status, voyage.completed ? 'Completed' : 'Active')}</span>
                    </span>
                    <span class="d-grid gap-1 small text-secondary">
                        <span><strong class="text-dark">Cargo:</strong> ${detailValue(voyage.cargo)}</span>
                        <span><strong class="text-dark">Fuel departure:</strong> ${fuelValue(voyage.fuel_at_departure)}</span>
                        <span><strong class="text-dark">Fuel consumed:</strong> ${fuelValue(voyage.fuel_consumed)}</span>
                        <span><strong class="text-dark">${locationLabel}:</strong> ${detailValue(voyage.current?.name || voyage.positions?.at(-1)?.name)}</span>
                        <span><strong class="text-dark">Destination:</strong> ${detailValue(voyage.destination?.name)}</span>
                        <span><strong class="text-dark">${dateLabel}:</strong> ${detailValue(dateValue)}</span>
                        <span><strong class="text-dark">Last update:</strong> ${detailValue(voyage.last_update)}</span>
                    </span>
                    <span class="d-block mt-2 small fw-semibold text-primary"><i class="bi bi-crosshair me-1"></i>Focus this voyage</span>
                </button>
            `;
        }).join('');

        detailsList.querySelectorAll('[data-dashboard-voyage]').forEach((card) => {
            card.addEventListener('click', () => focusVoyage(card.dataset.dashboardVoyage));
        });
    };

    let currentMapMode = 'active';
    const showMapMode = (mode, fitMap = true) => {
        currentMapMode = mode;
        Object.values(layers).forEach((layer) => map.removeLayer(layer));
        layers[mode].addTo(map);
        filterButtons.forEach((button) => {
            const selected = button.dataset.dashboardMapFilter === mode;
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        const count = tracks.filter((voyage) => (voyage.completed ? 'previous' : 'active') === mode).length;
        countBadge.textContent = `${count} ${count === 1 ? 'track' : 'tracks'}`;
        renderVoyageDetails(mode);
        emptyMessage.classList.toggle('d-none', count > 0);
        emptyMessage.querySelector('span').textContent = mode === 'active'
            ? 'No active voyage has saved coordinates yet. Update its current location to start the dashboard track.'
            : 'No completed voyage tracking history is available yet.';

        const visibleBounds = groupBounds[mode];
        if (fitMap) {
            if (visibleBounds.length === 1) map.setView(visibleBounds[0], 9);
            else if (visibleBounds.length > 1) map.fitBounds(visibleBounds, { padding: [36, 36], maxZoom: 9 });
            else map.setView([12.4, 122.2], 5);
        }
        window.setTimeout(() => map.invalidateSize(), 50);
    };

    filterButtons.forEach((button) => button.addEventListener('click', () => showMapMode(button.dataset.dashboardMapFilter)));
    detailsToggle?.addEventListener('click', () => {
        const hidden = mapLayout?.classList.toggle('is-details-hidden') ?? false;
        detailsToggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
        const label = detailsToggle.querySelector('span');
        if (label) label.textContent = hidden ? 'Show details' : 'Hide details';
        window.setTimeout(() => map.invalidateSize(), 50);
    });

    const updateClock = () => {
        const now = new Date();
        const timeOptions = { timeZone: 'Asia/Manila', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        const dateOptions = { timeZone: 'Asia/Manila', weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
        if (liveTime) liveTime.textContent = new Intl.DateTimeFormat('en-PH', timeOptions).format(now);
        if (liveDate) liveDate.textContent = new Intl.DateTimeFormat('en-PH', dateOptions).format(now);
    };
    updateClock();
    window.setInterval(updateClock, 1000);

    let liveRefreshRunning = false;
    const refreshLiveFleet = async () => {
        if (liveRefreshRunning || document.hidden) return;
        liveRefreshRunning = true;
        if (liveSync) liveSync.textContent = 'Checking vessel updates...';
        try {
            const response = await fetch(liveFleetUrl, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error('Live data request failed');
            const payload = await response.json();
            rebuildMapLayers(payload.tracks);
            showMapMode(currentMapMode, false);
            if (liveSync) liveSync.textContent = `Live · synced ${new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', second: '2-digit' }).format(new Date())}`;
        } catch (error) {
            if (liveSync) liveSync.textContent = 'Connection interrupted · retrying';
        } finally {
            liveRefreshRunning = false;
        }
    };

    const syncFullscreenState = () => {
        const active = document.fullscreenElement === mapSection || mapSection?.classList.contains('is-monitor-fullscreen');
        fullscreenButton?.setAttribute('aria-pressed', active ? 'true' : 'false');
        const label = fullscreenButton?.querySelector('span');
        const icon = fullscreenButton?.querySelector('i');
        if (label) label.textContent = active ? 'Exit full screen' : 'Full screen';
        if (icon) icon.className = `bi ${active ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'} me-1`;
        active ? map.scrollWheelZoom.enable() : map.scrollWheelZoom.disable();
        window.setTimeout(() => map.invalidateSize(), 120);
    };

    fullscreenButton?.addEventListener('click', async () => {
        if (document.fullscreenElement) {
            await document.exitFullscreen();
        } else if (mapSection?.requestFullscreen) {
            await mapSection.requestFullscreen();
        } else {
            mapSection?.classList.toggle('is-monitor-fullscreen');
            syncFullscreenState();
        }
    });
    document.addEventListener('fullscreenchange', syncFullscreenState);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshLiveFleet(); });

    rebuildMapLayers(tracks);
    showMapMode('active');
    if (liveSync) liveSync.textContent = 'Live · refreshes every 15 seconds';
    window.setInterval(refreshLiveFleet, 15000);
});
</script>
@endpush
