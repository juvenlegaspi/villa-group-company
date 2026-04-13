@extends('layouts.app')
@section('content')
<div class="container">  
    <div class="row g-4">
        <div class="col-md-3">
            <a href="{{ route('vessels.index') }}" style="text-decoration:none">
                <div class="card shadow-sm border-0 text-center p-3">
                    <h5 class="mt-2">🚢 Vessels</h5>
                    <h2 class="text-primary">{{ $totalVessels }}</h2>
                    <p class="text-muted">Total Vessels</p>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('tech-defects.dashboard') }}" style="text-decoration:none">
                <div class="card shadow-sm border-0 text-center p-3">
                    <h5 class="mt-2">🔧 Tech & Defects</h5>
                    <h2 class="text-danger">{{ $totalDefects }}</h2>
                    <p class="text-muted">Reports</p>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('voyage-logs.dashboard') }}" style="text-decoration:none">
                <div class="card shadow-sm border-0 text-center p-3">
                    <h5 class="mt-2">🧭 Voyage Logs</h5>
                    <h2 class="text-success">{{ $totalLogs }}</h2>
                    <p class="text-muted">Voyages</p>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center p-3">
                <h5 class="mt-2">👨‍✈️ Crew</h5>
                <h2 class="text-dark">{{ $totalCrew }}</h2>
                <p class="text-muted">Crew Onboard</p>
            </div>
        </div>
        <div class="col-md-3">
            <a href="{{ route('vessel-certificates.dashboard') }}" style="text-decoration:none">
                <div class="card shadow-sm border-0 text-center p-3">
                    <h5 class="mt-2">📄 Certificates</h5>
                    <h2 class="text-warning">
                        {{ $expiredCertificates + $expiringCertificates }}
                    </h2>
                    <p class="text-muted">Expiring / Expired</p>
                </div>
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const ctx = document.getElementById('statusChart');

    const anchored = {{ $anchored }};
    const sailing = {{ $sailing }};
    const total = anchored + sailing;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Anchored', 'Sailing'],
            datasets: [{
                data: [anchored, sailing],
                backgroundColor: ['#ffc107', '#198754'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            cutout: '65%', // para mas donut look
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let value = context.raw;
                            let percentage = ((value / total) * 100).toFixed(1);
                            return context.label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
</script>
@endsection