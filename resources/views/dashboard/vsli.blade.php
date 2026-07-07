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
    }
</style>

<div class="container-fluid px-0">
    <div class="vsli-shell">
        <section class="vsli-hero">
            <h2 class="fw-bold mb-2">Villa Shipping Lines Command Center</h2>
            <p class="mb-0" style="max-width: 760px;">
                Central view sa vessels, voyages, defects, certificates, ug fuel monitoring.
            </p>

            <div class="vsli-actions">
                <a href="{{ route('vessels.index') }}" class="btn btn-light btn-sm">
                    <i class="bi bi-ship me-1"></i> Vessels
                </a>
                <a href="{{ route('voyage-logs.dashboard') }}" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-map me-1"></i> Voyage Dashboard
                </a>
                <a href="#monthly-vessel-insights" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-bar-chart-line me-1"></i> Monthly Vessel Insights
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

        <section class="vsli-grid">
            <div class="vsli-card">
                <div class="vsli-stat">
                    <div class="vsli-stat-top">
                        <div>
                            <div class="vsli-stat-label">Fleet</div>
                            <div class="vsli-stat-value">{{ number_format($totalVessels) }}</div>
                            <p class="vsli-stat-note">{{ number_format($activeVessels) }} vessels currently tied to open voyages</p>
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
                            <div class="vsli-stat-label">Crew Logged</div>
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
                    <a href="{{ route('tech-defects.dashboard') }}" class="small text-decoration-none">Inspect defect status</a>
                </div>
            </div>

            <div class="vsli-card">
                <div class="vsli-stat">
                    <div class="vsli-stat-top">
                        <div>
                            <div class="vsli-stat-label">Certificate Risk</div>
                            <div class="vsli-stat-value">{{ number_format($expiredCertificates + $expiringCertificates) }}</div>
                            <p class="vsli-stat-note">{{ number_format($expiredCertificates) }} expired, {{ number_format($expiringCertificates) }} due within 30 days</p>
                        </div>
                        <span class="vsli-icon bg-soft-orange"><i class="bi bi-file-earmark-text"></i></span>
                    </div>
                    <a href="{{ route('vessel-certificates.dashboard') }}" class="small text-decoration-none">Open certificate dashboard</a>
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
                                <p class="vsli-subtext">Quick pulse sa voyage volume ug defect distribution.</p>
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
                                <p class="vsli-subtext">Certificate health summary para sa fleet compliance.</p>
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
                                    <span class="small text-muted">Walay due or expired certificate sa current summary.</span>
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
                                <h5>Monthly Voyages Per Vessel</h5>
                                <p class="vsli-subtext">Voyage per vessel within {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="vsli-chart-wrap mb-4">
                            <canvas id="monthlyVoyageVesselChart"></canvas>
                        </div>

                        <div class="table-responsive">
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
                                            <td colspan="3" class="text-center text-muted py-4">No monthly voyage data found.</td>
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
                                <h5>Monthly Fuel Per Vessel</h5>
                                <p class="vsli-subtext">Fuel consumed ug received per vessel within {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="vsli-chart-wrap mb-4">
                            <canvas id="monthlyFuelVesselChart"></canvas>
                        </div>

                        <div class="table-responsive">
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
                                            <td colspan="4" class="text-center text-muted py-4">No monthly fuel data found.</td>
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
                                <h5>Average Turnaround Time</h5>
                                <p class="vsli-subtext">Per port call average turnaround hours for {{ $currentMonthLabel }}.</p>
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
                        <p class="vsli-subtext">Central summary sa consumption, bunkering, ug low fuel exposure.</p>
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
                        <div class="label">Low Fuel Voyages</div>
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
                        <h5>Monthly Vessel Snapshot</h5>
                        <p class="vsli-subtext">Current month focus for vessels, fuel, turnaround, ug loading activities.</p>
                    </div>
                </div>

                <div class="vsli-mini-grid">
                    <div class="vsli-mini-card">
                        <div class="label">Month Covered</div>
                        <div class="value">{{ $currentMonthLabel }}</div>
                        <div class="small text-muted">Dashboard focus for the current month</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Total Voyages This Month</div>
                        <div class="value">{{ number_format($monthlyVoyageSummary) }}</div>
                        <div class="small text-muted">All voyage logs created within {{ $currentMonthLabel }}</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Vessels With Fuel Logs</div>
                        <div class="value">{{ number_format($monthlyFuelByVessel->count()) }}</div>
                        <div class="small text-muted">Vessels with fuel activity this month</div>
                    </div>
                    <div class="vsli-mini-card">
                        <div class="label">Ports With Turnaround</div>
                        <div class="value">{{ number_format($turnaroundPerPort->count()) }}</div>
                        <div class="small text-muted">Port locations with tracked turnaround hours</div>
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
                                <h5>Turnaround Per Port Location</h5>
                                <p class="vsli-subtext">Average ug total turnaround hours per port location for {{ $currentMonthLabel }}.</p>
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
                                        <th>Avg Turnaround</th>
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
                                            <td colspan="4" class="text-center text-muted py-4">No turnaround data found.</td>
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
                                <h5>Recent Voyage Logs</h5>
                                <p class="vsli-subtext">Latest voyage headers recorded in the system.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
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
                                <h5>Monthly Cargo Voyages</h5>
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
                                <h5>Recent Fuel Monitoring</h5>
                                <p class="vsli-subtext">Latest ROB and bunkering related entries.</p>
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
                                <h5>Recent Activities</h5>
                                <p class="vsli-subtext">Latest movement gikan sa voyage operations.</p>
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
                                                <span class="badge {{ $activity->detail?->main_status === 'COMPLETED' ? 'bg-success' : 'bg-primary' }}">
                                                    {{ $activity->detail?->main_status ?? 'ONGOING' }}
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
                                <h5>Recent Tech Defects</h5>
                                <p class="vsli-subtext">Fresh defect reports that may need follow-up.</p>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const monthlyVoyageLabels = {!! json_encode($monthlyVoyages->pluck('label')->values()) !!};
    const monthlyVoyageData = {!! json_encode($monthlyVoyages->pluck('total')->values()) !!};
    const defectStatusLabels = {!! json_encode($defectStatusLabels) !!};
    const defectStatusData = {!! json_encode($defectStatusData) !!};
    const fuelEngineLabels = {!! json_encode($fuelEngineLabels) !!};
    const fuelEngineData = {!! json_encode($fuelEngineData) !!};
    const topFuelVesselLabels = {!! json_encode($topFuelVesselLabels) !!};
    const topFuelVesselData = {!! json_encode($topFuelVesselData) !!};
    const monthlyVoyageVesselLabels = {!! json_encode($monthlyVoyageVesselLabels) !!};
    const monthlyVoyageVesselData = {!! json_encode($monthlyVoyageVesselData) !!};
    const monthlyFuelVesselLabels = {!! json_encode($monthlyFuelVesselLabels) !!};
    const monthlyFuelVesselData = {!! json_encode($monthlyFuelVesselData) !!};
    const turnaroundPortLabels = {!! json_encode($turnaroundPortLabels) !!};
    const turnaroundPortData = {!! json_encode($turnaroundPortData) !!};
    const loadingUnloadingLabels = {!! json_encode($loadingUnloadingLabels) !!};
    const loadingDurationChartData = {!! json_encode($loadingDurationChartData) !!};
    const unloadingDurationChartData = {!! json_encode($unloadingDurationChartData) !!};

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
                backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#198754'],
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
                label: 'Avg Turnaround Hours',
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
                label: 'Average Turnaround Hours',
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
</script>
@endsection
