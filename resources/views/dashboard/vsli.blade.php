@extends('layouts.app')

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
    }
</style>

<div class="container-fluid px-0">
    <div class="vsli-shell">
        <section class="vsli-hero">
            @if(auth()->user()->isExecutiveViewer())
                <span class="mb-3 d-inline-flex align-items-center gap-2 rounded-pill bg-white bg-opacity-10 px-3 py-2 small fw-bold"><i class="bi bi-eye"></i> Read-only executive dashboard</span>
            @endif
            <h2 class="fw-bold mb-2">Villa Shipping Lines Command Center</h2>
            <p class="mb-0" style="max-width: 760px;">
                Central view of vessels, voyages, defects, certificates, and fuel monitoring.
            </p>

            <div class="vsli-actions">
                @unless(auth()->user()->isExecutiveViewer())
                <a href="{{ route('vessels.index') }}" class="btn btn-light btn-sm">
                    <i class="bi bi-ship me-1"></i> Vessels
                </a>
                @endunless
                <a href="{{ route('voyage-logs.dashboard') }}" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-map me-1"></i> Voyage Dashboard
                </a>
                <a href="#monthly-vessel-insights" class="btn btn-outline-light btn-sm">
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

        <section class="vsli-grid">
            <div class="vsli-card">
                <div class="vsli-stat">
                    <div class="vsli-stat-top">
                        <div>
                            <div class="vsli-stat-label">Fleet <span class="badge bg-light text-secondary ms-1">Live</span></div>
                            <div class="vsli-stat-value">{{ number_format($totalVessels) }}</div>
                            <p class="vsli-stat-note">{{ number_format($activeVessels) }} vessels marked active or operational</p>
                        </div>
                        <span class="vsli-icon bg-soft-blue"><i class="bi bi-ship"></i></span>
                    </div>
                    @unless(auth()->user()->isExecutiveViewer())<a href="{{ route('vessels.index') }}" class="small text-decoration-none">Open vessel monitoring</a>@endunless
                </div>
            </div>

            <div class="vsli-card">
                <div class="vsli-stat">
                    <div class="vsli-stat-top">
                        <div>
                            <div class="vsli-stat-label">Voyages</div>
                            <div class="vsli-stat-value">{{ number_format($totalVoyages) }}</div>
                            <p class="vsli-stat-note">{{ number_format($openVoyages) }} open and {{ number_format($completedVoyages) }} completed</p>
                        </div>
                        <span class="vsli-icon bg-soft-green"><i class="bi bi-compass"></i></span>
                    </div>
                    <a href="{{ route('voyage-logs.dashboard') }}" class="small text-decoration-none">Review voyage dashboard</a>
                </div>
            </div>

            <div class="vsli-card">
                <div class="vsli-stat">
                    <div class="vsli-stat-top">
                        <div>
                            <div class="vsli-stat-label">Crew Logged <span class="badge bg-light text-secondary ms-1">Live</span></div>
                            <div class="vsli-stat-value">{{ number_format($totalCrew) }}</div>
                            <p class="vsli-stat-note">Combined crew counts recorded across voyage headers</p>
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
                            <div class="vsli-stat-label">Defects</div>
                            <div class="vsli-stat-value">{{ number_format($totalDefects) }}</div>
                            <p class="vsli-stat-note">{{ number_format($criticalDefects) }} marked critical across the fleet</p>
                        </div>
                        <span class="vsli-icon bg-soft-red"><i class="bi bi-cone-striped"></i></span>
                    </div>
                    <a href="{{ route('tech-defects.dashboard') }}" class="small text-decoration-none">Inspect defect dashboard</a>
                </div>
            </div>

            <div class="vsli-card">
                <div class="vsli-stat">
                    <div class="vsli-stat-top">
                        <div>
                            <div class="vsli-stat-label">Certificate Risk <span class="badge bg-light text-secondary ms-1">Live</span></div>
                            <div class="vsli-stat-value">{{ number_format($expiredCertificates + $expiringCertificates) }}</div>
                            <p class="vsli-stat-note">{{ number_format($expiredCertificates) }} expired, {{ number_format($expiringCertificates) }} due within 30 days</p>
                        </div>
                        <span class="vsli-icon bg-soft-orange"><i class="bi bi-file-earmark-text"></i></span>
                    </div>
                    <a href="{{ route('vessel-certificates.dashboard') }}" class="small text-decoration-none">Open certificate dashboard</a>
                </div>
            </div>
        </section>

        <section class="card vsli-card vsli-section-card">
            <div class="card-body">
                <div class="vsli-section-title">
                    <div>
                        <h4>Selected-period health</h4>
                        <p class="vsli-subtext">Decision-ready indicators for {{ $dashboardRange['label'] }}. Certificate compliance is a live fleet snapshot.</p>
                    </div>
                </div>
                <div class="vsli-mini-grid">
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
