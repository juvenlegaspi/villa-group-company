@extends('layouts.app')

@section('content')
<style>
    .ops-shell {
        display: grid;
        gap: 24px;
    }

    .ops-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .ops-card {
        border: 0;
        border-radius: 22px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        overflow: hidden;
        background: #fff;
    }

    .ops-hero {
        border-radius: 24px;
        padding: 28px 30px;
        background: linear-gradient(135deg, #0b3c74, #0f5fa8 55%, #7cc6ff);
        color: #fff;
        box-shadow: 0 20px 45px rgba(11, 60, 116, 0.2);
    }

    .ops-stat {
        padding: 22px;
    }

    .ops-stat-label {
        color: #64748b;
        font-size: 0.92rem;
        margin-bottom: 8px;
    }

    .ops-stat-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .ops-stat-note {
        margin: 0;
        color: #475569;
        font-size: 0.9rem;
    }

    .ops-card-body {
        padding: 24px;
    }

    .ops-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }

    .ops-title h4,
    .ops-title h5 {
        margin: 0;
        color: #0f172a;
    }

    .ops-subtext {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 0.92rem;
    }

    .ops-chart-wrap {
        position: relative;
        min-height: 290px;
    }

    .ops-table thead th {
        white-space: nowrap;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .ops-table tbody td {
        vertical-align: middle;
    }

    @media (max-width: 768px) {
        .ops-hero {
            padding: 22px;
        }
    }
</style>

<div class="container-fluid px-0">
    <div class="ops-shell">
        <section class="ops-hero">
            <h2 class="fw-bold mb-2">OPERATIONS | Dashboard</h2>
            <p class="mb-0">Monthly vessel performance, fuel trend, turnaround time, ug loading/unloading duration.</p>
        </section>

        <section class="ops-grid">
            <div class="ops-card">
                <div class="ops-stat">
                    <div class="ops-stat-label">Total Voyages for the Month of {{ $currentMonthLabel }}</div>
                    <div class="ops-stat-value">{{ number_format($monthlyVoyageSummary) }}</div>
                    <p class="ops-stat-note">{{ number_format($totalVoyages) }} total voyages in the system</p>
                </div>
            </div>
            <div class="ops-card">
                <div class="ops-stat">
                    <div class="ops-stat-label">Open Voyages</div>
                    <div class="ops-stat-value">{{ number_format($activeVoyages) }}</div>
                    <p class="ops-stat-note">Voyages currently marked open</p>
                </div>
            </div>
            <div class="ops-card">
                <div class="ops-stat">
                    <div class="ops-stat-label">Completed Voyages</div>
                    <div class="ops-stat-value">{{ number_format($completedVoyages) }}</div>
                    <p class="ops-stat-note">Voyages already completed</p>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-6">
                <div class="ops-card h-100">
                    <div class="ops-card-body">
                        <div class="ops-title">
                            <div>
                                <h4>Top Vessels with Most Voyages in {{ $currentMonthLabel }}</h4>
                                <p class="ops-subtext">Monthly voyage count ug total voyage hours per vessel.</p>
                            </div>
                        </div>

                        <div class="ops-chart-wrap mb-4">
                            <canvas id="monthlyVoyageVesselChart"></canvas>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle ops-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Voyages</th>
                                        <th>Total Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($monthlyVoyagesPerVessel as $row)
                                        <tr>
                                            <td>{{ $row['vessel_name'] }}</td>
                                            <td>{{ number_format($row['total_voyages']) }}</td>
                                            <td>{{ number_format($row['total_voyage_hours'], 2) }} hrs</td>
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
                <div class="ops-card h-100">
                    <div class="ops-card-body">
                        <div class="ops-title">
                            <div>
                                <h4>Fuel Consumption Per Vessel</h4>
                                <p class="ops-subtext">Fuel consumed ug received per vessel within {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="ops-chart-wrap mb-4">
                            <canvas id="monthlyFuelVesselChart"></canvas>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle ops-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Avg. Fuel</th>
                                        <th>Total Consumed</th>
                                        <th>Total Received</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($monthlyFuelByVessel as $row)
                                        <tr>
                                            <td>{{ $row['vessel_name'] }}</td>
                                            <td>{{ number_format($row['average_consumed'], 2) }} L</td>
                                            <td>{{ number_format($row['total_consumed'], 2) }} L</td>
                                            <td>{{ number_format($row['total_received'], 2) }} L</td>
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
                <div class="ops-card h-100">
                    <div class="ops-card-body">
                        <div class="ops-title">
                            <div>
                                <h4>Monthly Voyage Trend</h4>
                                <p class="ops-subtext">Voyage trend across the months of the current year.</p>
                            </div>
                        </div>

                        <div class="ops-chart-wrap">
                            <canvas id="monthlyVoyageTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="ops-card h-100">
                    <div class="ops-card-body">
                        <div class="ops-title">
                            <div>
                                <h4>Average Fuel Consumption Breakdown</h4>
                                <p class="ops-subtext">Monthly fuel engine share from monitoring logs.</p>
                            </div>
                        </div>

                        <div class="ops-chart-wrap">
                            <canvas id="fuelEngineBreakdownChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-6">
                <div class="ops-card h-100">
                    <div class="ops-card-body">
                        <div class="ops-title">
                            <div>
                                <h4>Average Turnaround Time</h4>
                                <p class="ops-subtext">Per port call average turnaround hours for {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="ops-chart-wrap">
                            <canvas id="averageTurnaroundChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="ops-card h-100">
                    <div class="ops-card-body">
                        <div class="ops-title">
                            <div>
                                <h4>Loading & Unloading Duration</h4>
                                <p class="ops-subtext">Combined loading and unloading hours per vessel for {{ $currentMonthLabel }}.</p>
                            </div>
                        </div>

                        <div class="ops-chart-wrap">
                            <canvas id="loadingUnloadingChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-xl-6">
                <div class="ops-card h-100">
                    <div class="ops-card-body">
                        <div class="ops-title">
                            <div>
                                <h5>Turnaround Per Port Location</h5>
                                <p class="ops-subtext">Average ug total turnaround hours per port location.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle ops-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Port Location</th>
                                        <th>Voyages</th>
                                        <th>Avg Turnaround</th>
                                        <th>Total Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($turnaroundPerPort as $row)
                                        <tr>
                                            <td>{{ $row['location_name'] }}</td>
                                            <td>{{ number_format($row['total_voyages']) }}</td>
                                            <td>{{ number_format($row['average_turnaround_hours'], 2) }} hrs</td>
                                            <td>{{ number_format($row['total_turnaround_hours'], 2) }} hrs</td>
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
                <div class="ops-card h-100">
                    <div class="ops-card-body">
                        <div class="ops-title">
                            <div>
                                <h5>Loading / Unloading Summary</h5>
                                <p class="ops-subtext">Duration summary per vessel for monthly cargo handling activities.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle ops-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vessel</th>
                                        <th>Loading Hours</th>
                                        <th>Unloading Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($loadingUnloadingLabels as $vesselName)
                                        <tr>
                                            <td>{{ $vesselName }}</td>
                                            <td>{{ number_format((float) ($loadingDurationChartData[$loop->index] ?? 0), 2) }} hrs</td>
                                            <td>{{ number_format((float) ($unloadingDurationChartData[$loop->index] ?? 0), 2) }} hrs</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">No loading or unloading data found.</td>
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
    const monthlyVoyageVesselLabels = {!! json_encode($monthlyVoyageVesselLabels) !!};
    const monthlyVoyageVesselData = {!! json_encode($monthlyVoyageVesselData) !!};
    const monthlyFuelVesselLabels = {!! json_encode($monthlyFuelVesselLabels) !!};
    const monthlyFuelVesselData = {!! json_encode($monthlyFuelVesselData) !!};
    const monthlyVoyageTrendLabels = {!! json_encode($monthlyVoyageTrend->pluck('label')->values()) !!};
    const monthlyVoyageTrendData = {!! json_encode($monthlyVoyageTrend->pluck('total')->values()) !!};
    const fuelEngineLabels = {!! json_encode($fuelEngineLabels) !!};
    const fuelEngineData = {!! json_encode($fuelEngineData) !!};
    const turnaroundPortLabels = {!! json_encode($turnaroundPortLabels) !!};
    const turnaroundPortData = {!! json_encode($turnaroundPortData) !!};
    const loadingUnloadingLabels = {!! json_encode($loadingUnloadingLabels) !!};
    const loadingDurationChartData = {!! json_encode($loadingDurationChartData) !!};
    const unloadingDurationChartData = {!! json_encode($unloadingDurationChartData) !!};

    new Chart(document.getElementById('monthlyVoyageVesselChart'), {
        type: 'bar',
        data: {
            labels: monthlyVoyageVesselLabels,
            datasets: [{
                label: 'Voyages',
                data: monthlyVoyageVesselData,
                backgroundColor: ['#f7b500', '#28a5f5', '#1f78d1', '#184f9c', '#4c8fe3', '#81b5ff'],
                borderRadius: 10,
                maxBarThickness: 42
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } }
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
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.18)',
                tension: 0.35,
                fill: false,
                pointRadius: 4,
                pointBackgroundColor: '#f59e0b'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    new Chart(document.getElementById('monthlyVoyageTrendChart'), {
        type: 'line',
        data: {
            labels: monthlyVoyageTrendLabels,
            datasets: [{
                label: 'Voyages',
                data: monthlyVoyageTrendData,
                borderColor: '#ea7a00',
                backgroundColor: 'rgba(234, 122, 0, 0.12)',
                tension: 0.3,
                fill: false,
                pointRadius: 4,
                pointBackgroundColor: '#ea7a00'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    new Chart(document.getElementById('fuelEngineBreakdownChart'), {
        type: 'pie',
        data: {
            labels: fuelEngineLabels,
            datasets: [{
                data: fuelEngineData,
                backgroundColor: ['#0d6efd', '#fd7e14', '#6fbe44', '#6c757d'],
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
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true }
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
                backgroundColor: '#ff9f1c',
                borderRadius: 8,
                borderSkipped: false
            }, {
                label: 'Unloading',
                data: unloadingDurationChartData,
                backgroundColor: '#1e88e5',
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
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
