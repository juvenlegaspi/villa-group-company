@extends('layouts.app')

@section('content')
<style>
    .defect-shell {
        display: grid;
        gap: 24px;
    }

    .defect-hero {
        border-radius: 24px;
        padding: 30px;
        color: #fff;
        background:
            radial-gradient(circle at top right, rgba(255,255,255,0.16), transparent 30%),
            linear-gradient(135deg, #3d0c11, #7f1d1d 55%, #f87171);
        box-shadow: 0 20px 45px rgba(61, 12, 17, 0.2);
    }

    .defect-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
    }

    .defect-card {
        border: 0;
        border-radius: 22px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        height: 100%;
    }

    .defect-stat {
        padding: 22px;
    }

    .defect-stat-label {
        font-size: 0.9rem;
        color: #64748b;
        margin-bottom: 8px;
    }

    .defect-stat-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .defect-stat-note {
        margin: 0;
        color: #475569;
        font-size: 0.9rem;
    }

    .defect-card-body {
        padding: 24px;
    }

    .defect-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }

    .defect-title h4,
    .defect-title h5 {
        margin: 0;
        color: #0f172a;
    }

    .defect-subtext {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 0.92rem;
    }

    .defect-chart-wrap {
        position: relative;
        min-height: 300px;
    }

    .defect-table thead th {
        white-space: nowrap;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .defect-table tbody td {
        vertical-align: middle;
    }

    .defect-alert-list {
        display: grid;
        gap: 12px;
    }

    .defect-alert-item {
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: #fff;
    }

    @media (max-width: 768px) {
        .defect-hero {
            padding: 22px;
        }
    }
</style>

<div class="container-fluid px-0">
    <div class="defect-shell">
        <section class="defect-hero">
            <h2 class="fw-bold mb-2">Tech Defects Command Dashboard</h2>
            <p class="mb-0">Professional overview sa defect load, critical exposure, vessel risk, ug latest technical reports across the fleet.</p>
        </section>

        <section class="defect-grid">
            <div class="defect-card">
                <div class="defect-stat">
                    <div class="defect-stat-label">Total Reports</div>
                    <div class="defect-stat-value">{{ number_format($totalReports) }}</div>
                    <p class="defect-stat-note">All defect records logged in the system</p>
                </div>
            </div>
            <div class="defect-card">
                <div class="defect-stat">
                    <div class="defect-stat-label">Open</div>
                    <div class="defect-stat-value text-danger">{{ number_format($open) }}</div>
                    <p class="defect-stat-note">Fresh reports waiting for action</p>
                </div>
            </div>
            <div class="defect-card">
                <div class="defect-stat">
                    <div class="defect-stat-label">Ongoing</div>
                    <div class="defect-stat-value text-warning">{{ number_format($ongoing) }}</div>
                    <p class="defect-stat-note">Repairs currently in progress</p>
                </div>
            </div>
            <div class="defect-card">
                <div class="defect-stat">
                    <div class="defect-stat-label">Waiting 3rd Party</div>
                    <div class="defect-stat-value" style="color:#0d6efd;">{{ number_format($waiting) }}</div>
                    <p class="defect-stat-note">Cases requiring external support</p>
                </div>
            </div>
            <div class="defect-card">
                <div class="defect-stat">
                    <div class="defect-stat-label">Critical Defects</div>
                    <div class="defect-stat-value text-danger">{{ number_format($criticalDefects) }}</div>
                    <p class="defect-stat-note">Need immediate technical attention</p>
                </div>
            </div>
            <div class="defect-card">
                <div class="defect-stat">
                    <div class="defect-stat-label">Resolved This Month</div>
                    <div class="defect-stat-value text-success">{{ number_format($resolvedThisMonth) }}</div>
                    <p class="defect-stat-note">Reports completed during current month</p>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-7">
                <div class="defect-card h-100">
                    <div class="defect-card-body">
                        <div class="defect-title">
                            <div>
                                <h4>Defect Overview</h4>
                                <p class="defect-subtext">Status distribution ug monthly trend sa technical defects.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-5">
                                <div class="defect-chart-wrap">
                                    <canvas id="defectStatusChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="defect-chart-wrap">
                                    <canvas id="monthlyDefectChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="defect-card h-100">
                    <div class="defect-card-body">
                        <div class="defect-title">
                            <div>
                                <h4>Vessel Risk Focus</h4>
                                <p class="defect-subtext">Top vessels with the heaviest active and critical defect exposure.</p>
                            </div>
                        </div>

                        <div class="defect-chart-wrap mb-4">
                            <canvas id="vesselDefectChart"></canvas>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle defect-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Critical</th>
                                        <th>Active</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($riskVessels as $risk)
                                        <tr>
                                            <td>{{ $risk['vessel_name'] }}</td>
                                            <td class="text-danger fw-semibold">{{ number_format($risk['critical_total']) }}</td>
                                            <td class="text-warning fw-semibold">{{ number_format($risk['active_total']) }}</td>
                                            <td>{{ number_format($risk['total_reports']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No vessel risk records found.</td>
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
                <div class="defect-card h-100">
                    <div class="defect-card-body">
                        <div class="defect-title">
                            <div>
                                <h5>Severity Breakdown</h5>
                                <p class="defect-subtext">Quick spread of critical, high, medium, ug low severity reports.</p>
                            </div>
                        </div>

                        <div class="defect-chart-wrap">
                            <canvas id="severityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="defect-card h-100">
                    <div class="defect-card-body">
                        <div class="defect-title">
                            <div>
                                <h5>System & Port Hotspots</h5>
                                <p class="defect-subtext">Where the fleet sees the most recurring defect concentration.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="defect-chart-wrap">
                                    <canvas id="systemChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="defect-chart-wrap">
                                    <canvas id="portChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-6">
                <div class="defect-card h-100">
                    <div class="defect-card-body">
                        <div class="defect-title">
                            <div>
                                <h5>Priority Watchlist</h5>
                                <p class="defect-subtext">Most problematic vessel and critical defect exposure summary.</p>
                            </div>
                        </div>

                        <div class="defect-alert-list">
                            <div class="defect-alert-item" style="border-color:#fecaca; background:#fff7f7;">
                                <strong>Most Problematic Vessel</strong><br>
                                <span class="text-muted">{{ $topVessel?->vessel?->vessel_name ?? 'No vessel yet' }}</span><br>
                                <span class="small text-danger">{{ number_format($topVessel?->total ?? 0) }} defect reports logged</span>
                            </div>
                            <div class="defect-alert-item" style="border-color:#fde68a; background:#fffbeb;">
                                <strong>Critical Defect Exposure</strong><br>
                                <span class="text-muted">{{ number_format($criticalDefects) }} critical and {{ number_format($highSeverityDefects) }} critical/high severity cases</span><br>
                                <span class="small text-warning">Keep engineering and operations aligned for escalation handling.</span>
                            </div>
                            <div class="defect-alert-item" style="border-color:#bfdbfe; background:#eff6ff;">
                                <strong>Third-Party Dependency</strong><br>
                                <span class="text-muted">{{ number_format($thirdPartyCases) }} reports marked with 3rd party requirement</span><br>
                                <span class="small text-primary">Track vendor response time and spare/tool readiness.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="defect-card h-100">
                    <div class="defect-card-body">
                        <div class="defect-title">
                            <div>
                                <h5>Latest Defect Reports</h5>
                                <p class="defect-subtext">Newest technical issues recorded in the system.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle defect-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Report</th>
                                        <th>Vessel</th>
                                        <th>Status</th>
                                        <th>Severity</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($latestReports as $report)
                                        <tr>
                                            <td><a href="{{ route('tech-defects.show', $report->id) }}">TD-{{ $report->id }}</a></td>
                                            <td>{{ $report->vessel?->vessel_name ?? '-' }}</td>
                                            <td>
                                                <span class="badge {{ $report->status === 'Completed' ? 'bg-success' : ($report->status === 'Waiting 3rd Party' ? 'bg-primary' : ($report->status === 'Ongoing' ? 'bg-warning text-dark' : 'bg-danger')) }}">
                                                    {{ $report->status }}
                                                </span>
                                            </td>
                                            <td>{{ $report->severity_level ?? '-' }}</td>
                                            <td>{{ optional($report->date_identified)->format('M d, Y') ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No defect reports found.</td>
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
    const defectStatusLabels = ['Open', 'Ongoing', 'Waiting 3rd Party', 'Completed'];
    const defectStatusData = [{{ $open }}, {{ $ongoing }}, {{ $waiting }}, {{ $completed }}];
    const monthlyDefectLabels = {!! json_encode($monthlyDefects->pluck('label')->values()) !!};
    const monthlyDefectData = {!! json_encode($monthlyDefects->pluck('total')->values()) !!};
    const vesselDefectLabels = {!! json_encode($riskVessels->pluck('vessel_name')->values()) !!};
    const vesselDefectData = {!! json_encode($riskVessels->pluck('active_total')->values()) !!};
    const severityLabels = {!! json_encode(array_keys($severityBreakdown)) !!};
    const severityData = {!! json_encode(array_values($severityBreakdown)) !!};
    const systemLabels = {!! json_encode($systemBreakdown->pluck('system_name')->values()) !!};
    const systemData = {!! json_encode($systemBreakdown->pluck('total')->values()) !!};
    const portLabels = {!! json_encode($portBreakdown->pluck('port_name')->values()) !!};
    const portData = {!! json_encode($portBreakdown->pluck('total')->values()) !!};

    new Chart(document.getElementById('defectStatusChart'), {
        type: 'doughnut',
        data: {
            labels: defectStatusLabels,
            datasets: [{
                data: defectStatusData,
                backgroundColor: ['#dc3545', '#ffc107', '#0d6efd', '#198754'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    new Chart(document.getElementById('monthlyDefectChart'), {
        type: 'line',
        data: {
            labels: monthlyDefectLabels,
            datasets: [{
                label: 'Defects',
                data: monthlyDefectData,
                borderColor: '#b91c1c',
                backgroundColor: 'rgba(185, 28, 28, 0.12)',
                tension: 0.35,
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: '#b91c1c'
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

    new Chart(document.getElementById('vesselDefectChart'), {
        type: 'bar',
        data: {
            labels: vesselDefectLabels,
            datasets: [{
                label: 'Active Defects',
                data: vesselDefectData,
                backgroundColor: '#f97316',
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
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    new Chart(document.getElementById('severityChart'), {
        type: 'pie',
        data: {
            labels: severityLabels,
            datasets: [{
                data: severityData,
                backgroundColor: ['#dc2626', '#f97316', '#facc15', '#22c55e'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    new Chart(document.getElementById('systemChart'), {
        type: 'bar',
        data: {
            labels: systemLabels,
            datasets: [{
                label: 'Reports',
                data: systemData,
                backgroundColor: '#7c3aed',
                borderRadius: 10,
                maxBarThickness: 38
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

    new Chart(document.getElementById('portChart'), {
        type: 'bar',
        data: {
            labels: portLabels,
            datasets: [{
                label: 'Reports',
                data: portData,
                backgroundColor: '#0f8b8d',
                borderRadius: 10,
                maxBarThickness: 38
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
</script>
@endsection
