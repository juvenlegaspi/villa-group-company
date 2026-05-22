@extends('layouts.app')

@section('content')

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="container">

    <h3 class="mb-4">
        Voyage Logs Dashboard
    </h3>

    {{-- ========================================= --}}
    {{-- MAIN COUNTS --}}
    {{-- ========================================= --}}

    <div class="row g-4">

        <div class="col-md-4">

            <div class="card shadow-sm border-0">

                <div class="card-body text-center">

                    <h5>Total Voyages</h5>

                    <h2 class="text-primary">
                        {{ $totalVoyages }}
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm border-0">

                <div class="card-body text-center">

                    <h5>Active Voyages</h5>

                    <h2 class="text-warning">
                        {{ $activeVoyages }}
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm border-0">

                <div class="card-body text-center">

                    <h5>Completed Voyages</h5>

                    <h2 class="text-success">
                        {{ $completedVoyages }}
                    </h2>

                </div>

            </div>

        </div>

    </div>

    {{-- ========================================= --}}
    {{-- TODAY COUNTS --}}
    {{-- ========================================= --}}

    <div class="row mt-4">

        <div class="col-md-3">

            <div class="card shadow-sm border-0">

                <div class="card-body text-center">

                    <h6>Activities Today</h6>

                    <h2 class="text-primary">
                        {{ $activitiesToday }}
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm border-0">

                <div class="card-body text-center">

                    <h6>Fuel Updates Today</h6>

                    <h2 class="text-warning">
                        {{ $fuelUpdatesToday }}
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm border-0">

                <div class="card-body text-center">

                    <h6>Active Vessels</h6>

                    <h2 class="text-success">
                        {{ $activeVessels }}
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm border-0">

                <div class="card-body text-center">

                    <h6>Completed Today</h6>

                    <h2 class="text-danger">
                        {{ $completedToday }}
                    </h2>

                </div>

            </div>

        </div>

    </div>

    {{-- ========================================= --}}
    {{-- LOW FUEL WARNING --}}
    {{-- ========================================= --}}

    @if($lowFuelVoyages->count() > 0)

    <div class="alert alert-danger mt-4 shadow-sm">

        <h5>
            🚨 Low Fuel Warning
        </h5>

        <ul class="mb-0">

            @foreach($lowFuelVoyages as $voyage)

            <li>
                Voyage #{{ $voyage->voyage_no }}
                -
                {{ $voyage->fuel_rob }}
            </li>

            @endforeach

        </ul>

    </div>

    @endif

    {{-- ========================================= --}}
    {{-- CHARTS --}}
    {{-- ========================================= --}}

    <div class="row mt-4">

        {{-- Voyage Status --}}

        <div class="col-md-6">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h6>
                        Voyage Status
                    </h6>

                    <div style="width:250px; margin:auto;">

                        <canvas id="statusChart"></canvas>

                    </div>

                </div>

            </div>

        </div>

        {{-- Monthly Voyages --}}

        <div class="col-md-6">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h6>
                        Voyages Per Month
                    </h6>

                    <canvas id="voyageChart" height="100"></canvas>

                </div>

            </div>

        </div>

    </div>

    {{-- ========================================= --}}
    {{-- SECOND CHARTS --}}
    {{-- ========================================= --}}

    <div class="row mt-4">

        {{-- Vessel Chart --}}

        <div class="col-md-6">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>
                        Voyages Per Vessel
                    </h6>

                    <canvas id="vesselChart"></canvas>

                </div>

            </div>

        </div>

        {{-- Port Chart --}}

        <div class="col-md-6">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>
                        Most Used Ports
                    </h6>

                    <div style="width:300px; margin:auto;">

                        <canvas id="portChart"></canvas>

                    </div>

                </div>

            </div>

        </div>

    </div>

    {{-- ========================================= --}}
    {{-- ACTIVITY STATUS --}}
    {{-- ========================================= --}}

    <div class="row mt-4">

        <div class="col-md-6">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>
                        Activity Status Distribution
                    </h6>

                    <div style="width:250px; margin:auto;">

                        <canvas id="activityChart"></canvas>

                    </div>

                </div>

            </div>

        </div>

        {{-- TOP ACTIVITIES --}}

        <div class="col-md-6">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h5>
                        Top Activities
                    </h5>

                    <canvas id="topActivitiesChart"></canvas>

                </div>

            </div>

        </div>

    </div>

    {{-- ========================================= --}}
    {{-- FUEL SUMMARY --}}
    {{-- ========================================= --}}

    <div class="row mt-4">

        <div class="col-md-4">

            <div class="card shadow-sm border-0">

                <div class="card-body text-center">

                    <h6>Total Fuel Consumed</h6>

                    <h3 class="text-danger">

                        {{ number_format($totalFuelConsumed, 2) }}

                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm border-0">

                <div class="card-body text-center">

                    <h6>Total Fuel Received</h6>

                    <h3 class="text-success">

                        {{ number_format($totalFuelReceived, 2) }}

                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm border-0">

                <div class="card-body text-center">

                    <h6>Average Fuel Consumption</h6>

                    <h3 class="text-primary">

                        {{ number_format($averageFuel, 2) }}

                    </h3>

                </div>

            </div>

        </div>

    </div>

    {{-- ========================================= --}}
    {{-- RECENT ACTIVITIES --}}
    {{-- ========================================= --}}

    <div class="card shadow-sm mt-4">

        <div class="card-body">

            <h5 class="mb-3">

                Recent Activities

            </h5>

            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead class="table-dark">

                        <tr>

                            <th>Activity</th>
                            <th>Status</th>
                            <th>Vessel</th>
                            <th>Created</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($recentActivities as $activity)

                        <tr>

                            <td>
                                {{ $activity->activity->name ?? '-' }}
                            </td>

                            <td>

                                <span class="badge bg-primary">

                                    {{ $activity->detail->main_status ?? '-' }}

                                </span>

                            </td>

                            <td>

                                {{ $activity->vessel->vessel_name ?? '-' }}

                            </td>

                            <td>

                                {{ $activity->created_at }}

                            </td>

                        </tr>

                        @empty

                        <tr>

                            <td colspan="4" class="text-center">

                                No recent activities

                            </td>

                        </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

{{-- ========================================= --}}
{{-- CHART JS --}}
{{-- ========================================= --}}

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    // =========================================
    // STATUS CHART
    // =========================================

    new Chart(
        document.getElementById('statusChart'),
        {
            type: 'pie',

            data: {

                labels: ['Active', 'Completed'],

                datasets: [{

                    data: [
                        {{ $activeVoyages }},
                        {{ $completedVoyages }}
                    ],

                    backgroundColor: [
                        '#ffc107',
                        '#198754'
                    ]

                }]

            }

        }
    );

    // =========================================
    // MONTHLY CHART
    // =========================================

    new Chart(
        document.getElementById('voyageChart'),
        {
            type: 'bar',

            data: {

                labels: {!! json_encode($monthlyVoyages->keys()) !!},

                datasets: [{

                    label: 'Voyages',

                    data: {!! json_encode($monthlyVoyages->values()) !!},

                    backgroundColor: '#0d6efd'

                }]

            }

        }
    );

    // =========================================
    // VESSEL CHART
    // =========================================

    new Chart(
        document.getElementById('vesselChart'),
        {
            type: 'bar',

            data: {

                labels: {!! json_encode($vesselVoyages->keys()) !!},

                datasets: [{

                    label: 'Voyages',

                    data: {!! json_encode($vesselVoyages->values()) !!},

                    backgroundColor: '#198754'

                }]

            }

        }
    );

    // =========================================
    // PORT CHART
    // =========================================

    new Chart(
        document.getElementById('portChart'),
        {
            type: 'doughnut',

            data: {

                labels: {!! json_encode($portStats->keys()) !!},

                datasets: [{

                    data: {!! json_encode($portStats->values()) !!},

                    backgroundColor: [
                        '#0d6efd',
                        '#ffc107',
                        '#dc3545',
                        '#198754',
                        '#6f42c1'
                    ]

                }]

            }

        }
    );

    // =========================================
    // ACTIVITY STATUS CHART
    // =========================================

    new Chart(
        document.getElementById('activityChart'),
        {
            type: 'doughnut',

            data: {

                labels: {!! json_encode($activityStats->keys()) !!},

                datasets: [{

                    data: {!! json_encode($activityStats->values()) !!},

                    backgroundColor: [
                        '#0d6efd',
                        '#ffc107',
                        '#198754',
                        '#dc3545'
                    ]

                }]

            }

        }
    );

    // =========================================
    // TOP ACTIVITIES CHART
    // =========================================

    new Chart(
        document.getElementById('topActivitiesChart'),
        {
            type: 'bar',

            data: {

                labels: [

                    @foreach($topActivities as $activity)

                        '{{ $activity->activity->activity_name ?? "N/A" }}',

                    @endforeach

                ],

                datasets: [{

                    label: 'Total',

                    data: [

                        @foreach($topActivities as $activity)

                            {{ $activity->total }},

                        @endforeach

                    ],

                    backgroundColor: [
                        '#0d6efd',
                        '#198754',
                        '#ffc107',
                        '#dc3545',
                        '#6f42c1'
                    ]

                }]

            }

        }
    );

</script>

@endsection