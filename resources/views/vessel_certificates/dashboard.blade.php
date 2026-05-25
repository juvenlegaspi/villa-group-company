@extends('layouts.app')

@section('content')
<style>
    .cert-shell {
        display: grid;
        gap: 24px;
    }

    .cert-hero {
        border-radius: 24px;
        padding: 30px;
        color: #fff;
        background:
            radial-gradient(circle at top right, rgba(255,255,255,0.18), transparent 30%),
            linear-gradient(135deg, #143642, #0f8b8d 55%, #b8f2e6);
        box-shadow: 0 20px 45px rgba(20, 54, 66, 0.18);
    }

    .cert-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .cert-card {
        border: 0;
        border-radius: 22px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        height: 100%;
    }

    .cert-stat {
        padding: 22px;
    }

    .cert-stat-label {
        font-size: 0.9rem;
        color: #64748b;
        margin-bottom: 8px;
    }

    .cert-stat-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .cert-stat-note {
        margin: 0;
        color: #475569;
        font-size: 0.9rem;
    }

    .cert-card-body {
        padding: 24px;
    }

    .cert-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }

    .cert-title h4,
    .cert-title h5 {
        margin: 0;
        color: #0f172a;
    }

    .cert-subtext {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 0.92rem;
    }

    .cert-chart-wrap {
        position: relative;
        min-height: 300px;
    }

    .cert-table thead th {
        white-space: nowrap;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .cert-alert-list {
        display: grid;
        gap: 12px;
    }

    .cert-alert-item {
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: #fff;
    }

    @media (max-width: 768px) {
        .cert-hero {
            padding: 22px;
        }
    }
</style>

<div class="container-fluid px-0">
    <div class="cert-shell">
        <section class="cert-hero">
            <h2 class="fw-bold mb-2">Certificate Compliance Dashboard</h2>
            <p class="mb-0">Fleet-wide visibility sa certificate status, expiry exposure, ug vessels needing immediate compliance follow-up.</p>
        </section>

        <section class="cert-grid">
            <div class="cert-card">
                <div class="cert-stat">
                    <div class="cert-stat-label">Total Certificates</div>
                    <div class="cert-stat-value">{{ number_format($totalCertificates) }}</div>
                    <p class="cert-stat-note">All certificate records across the fleet</p>
                </div>
            </div>
            <div class="cert-card">
                <div class="cert-stat">
                    <div class="cert-stat-label">Expired</div>
                    <div class="cert-stat-value text-danger">{{ number_format($expiredCertificates) }}</div>
                    <p class="cert-stat-note">Immediate compliance attention required</p>
                </div>
            </div>
            <div class="cert-card">
                <div class="cert-stat">
                    <div class="cert-stat-label">Expiring Within 30 Days</div>
                    <div class="cert-stat-value text-warning">{{ number_format($expiringCertificates) }}</div>
                    <p class="cert-stat-note">Certificates approaching due date</p>
                </div>
            </div>
            <div class="cert-card">
                <div class="cert-stat">
                    <div class="cert-stat-label">Valid</div>
                    <div class="cert-stat-value text-success">{{ number_format($validCertificates) }}</div>
                    <p class="cert-stat-note">Beyond the current 30-day risk window</p>
                </div>
            </div>
            <div class="cert-card">
                <div class="cert-stat">
                    <div class="cert-stat-label">Vessels With Certificates</div>
                    <div class="cert-stat-value">{{ number_format($vesselsWithCertificates) }}</div>
                    <p class="cert-stat-note">Fleet units with recorded certificate documents</p>
                </div>
            </div>
            <div class="cert-card">
                <div class="cert-stat">
                    <div class="cert-stat-label">Issued This Month</div>
                    <div class="cert-stat-value">{{ number_format($renewedThisMonth) }}</div>
                    <p class="cert-stat-note">Certificates issued in {{ $today->format('F Y') }}</p>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-7">
                <div class="cert-card h-100">
                    <div class="cert-card-body">
                        <div class="cert-title">
                            <div>
                                <h4>Compliance Overview</h4>
                                <p class="cert-subtext">Status mix and expiry trend for upcoming certificate exposure.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-5">
                                <div class="cert-chart-wrap">
                                    <canvas id="certificateStatusChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="cert-chart-wrap">
                                    <canvas id="expiryTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="cert-card h-100">
                    <div class="cert-card-body">
                        <div class="cert-title">
                            <div>
                                <h4>Vessel Risk Spotlight</h4>
                                <p class="cert-subtext">Vessels with the highest combined expired and expiring certificate count.</p>
                            </div>
                        </div>

                        <div class="cert-chart-wrap mb-4">
                            <canvas id="vesselRiskChart"></canvas>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle cert-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Expired</th>
                                        <th>Expiring</th>
                                        <th>Total Certs</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($vesselRiskSummary as $risk)
                                        <tr>
                                            <td>{{ $risk['vessel_name'] }}</td>
                                            <td class="text-danger fw-semibold">{{ number_format($risk['expired_count']) }}</td>
                                            <td class="text-warning fw-semibold">{{ number_format($risk['expiring_count']) }}</td>
                                            <td>{{ number_format($risk['total_certificates_count']) }}</td>
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
                <div class="cert-card h-100">
                    <div class="cert-card-body">
                        <div class="cert-title">
                            <div>
                                <h5>Expired Certificates</h5>
                                <p class="cert-subtext">Priority list for immediate compliance action.</p>
                            </div>
                        </div>

                        <div class="cert-alert-list">
                            @forelse($expiredList as $certificate)
                                <div class="cert-alert-item" style="border-color:#fecaca; background:#fff7f7;">
                                    <strong>{{ $certificate->vessel?->vessel_name ?? 'Unknown Vessel' }}</strong><br>
                                    <span class="text-muted">{{ $certificate->certificate_name }}</span><br>
                                    <span class="small text-danger">Expired: {{ optional($certificate->expiry_date)->format('M d, Y') ?? '-' }}</span>
                                </div>
                            @empty
                                <div class="cert-alert-item">
                                    <strong>No expired certificates.</strong><br>
                                    <span class="small text-muted">Current dashboard shows no overdue document.</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="cert-card h-100">
                    <div class="cert-card-body">
                        <div class="cert-title">
                            <div>
                                <h5>Expiring Soon</h5>
                                <p class="cert-subtext">Certificates due within the next 30 days.</p>
                            </div>
                        </div>

                        <div class="cert-alert-list">
                            @forelse($expiringList as $certificate)
                                <div class="cert-alert-item" style="border-color:#fde68a; background:#fffbeb;">
                                    <strong>{{ $certificate->vessel?->vessel_name ?? 'Unknown Vessel' }}</strong><br>
                                    <span class="text-muted">{{ $certificate->certificate_name }}</span><br>
                                    <span class="small text-warning">Due: {{ optional($certificate->expiry_date)->format('M d, Y') ?? '-' }}</span>
                                </div>
                            @empty
                                <div class="cert-alert-item">
                                    <strong>No expiring certificates.</strong><br>
                                    <span class="small text-muted">Nothing is due within the current 30-day window.</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-6">
                <div class="cert-card h-100">
                    <div class="cert-card-body">
                        <div class="cert-title">
                            <div>
                                <h5>Recent Certificate Activity</h5>
                                <p class="cert-subtext">Latest issued or updated certificate records.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle cert-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Certificate</th>
                                        <th>Issue Date</th>
                                        <th>Expiry Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentCertificates as $certificate)
                                        <tr>
                                            <td>{{ $certificate->vessel?->vessel_name ?? '-' }}</td>
                                            <td>{{ $certificate->certificate_name }}</td>
                                            <td>{{ optional($certificate->issue_date)->format('M d, Y') ?? '-' }}</td>
                                            <td>{{ optional($certificate->expiry_date)->format('M d, Y') ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No recent certificate activity found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="cert-card h-100">
                    <div class="cert-card-body">
                        <div class="cert-title">
                            <div>
                                <h5>Most Common Certificate Types</h5>
                                <p class="cert-subtext">Certificate names most frequently recorded in the fleet.</p>
                            </div>
                        </div>

                        <div class="cert-chart-wrap">
                            <canvas id="certificateTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div>
            <a href="{{ route('vessel-certificates.index') }}" class="btn btn-primary">
                <i class="bi bi-folder2-open me-1"></i> View Certificates
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const certificateStatusLabels = {!! json_encode($certificateStatusLabels) !!};
    const certificateStatusData = {!! json_encode($certificateStatusData) !!};
    const vesselRiskLabels = {!! json_encode($vesselRiskLabels) !!};
    const vesselRiskData = {!! json_encode($vesselRiskData) !!};
    const expiryTrendLabels = {!! json_encode($expiryTrend->pluck('label')->values()) !!};
    const expiryTrendData = {!! json_encode($expiryTrend->pluck('total')->values()) !!};
    const certificateTypeLabels = {!! json_encode($certificateTypeLabels) !!};
    const certificateTypeData = {!! json_encode($certificateTypeData) !!};

    new Chart(document.getElementById('certificateStatusChart'), {
        type: 'doughnut',
        data: {
            labels: certificateStatusLabels,
            datasets: [{
                data: certificateStatusData,
                backgroundColor: ['#dc3545', '#ffc107', '#198754'],
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

    new Chart(document.getElementById('expiryTrendChart'), {
        type: 'line',
        data: {
            labels: expiryTrendLabels,
            datasets: [{
                label: 'Certificates Expiring',
                data: expiryTrendData,
                borderColor: '#0f8b8d',
                backgroundColor: 'rgba(15, 139, 141, 0.12)',
                tension: 0.35,
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: '#0f8b8d'
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

    new Chart(document.getElementById('vesselRiskChart'), {
        type: 'bar',
        data: {
            labels: vesselRiskLabels,
            datasets: [{
                label: 'Risk Count',
                data: vesselRiskData,
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

    new Chart(document.getElementById('certificateTypeChart'), {
        type: 'bar',
        data: {
            labels: certificateTypeLabels,
            datasets: [{
                label: 'Records',
                data: certificateTypeData,
                backgroundColor: '#0d6efd',
                borderRadius: 10,
                maxBarThickness: 44
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
