@extends('layouts.app')

@section('content')
<a href="{{ route('supplier.report') }}" class="btn btn-danger mb-3">
    📄 Export PDF
</a>
<div class="container py-4">

    <h3 class="fw-bold mb-4">📦 Supplier Dashboard</h3>

    <div class="row g-4">

        <!-- Total Suppliers -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #0d6efd, #3d8bfd); border-radius:15px;">
                <div class="card-body">
                    <h6>Total Suppliers</h6>
                    <h2 class="fw-bold">{{ $metrics['totalSuppliers'] }}</h2>
                </div>
            </div>
        </div>

        <!-- Today -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #198754, #43c47c); border-radius:15px;">
                <div class="card-body">
                    <h6>Added Today</h6>
                    <h2 class="fw-bold">{{ $metrics['todaySuppliers'] }}</h2>
                </div>
            </div>
        </div>

        <!-- This Month -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #fd7e14, #ffb067); border-radius:15px;">
                <div class="card-body">
                    <h6>This Month</h6>
                    <h2 class="fw-bold">{{ $metrics['thisMonthSuppliers'] }}</h2>
                </div>
            </div>
        </div>

    </div>

    <!-- Top Products -->   
    <div class="mt-4">
        <div class="card border-0 shadow-sm" style="border-radius:15px;">
            <div class="card-body">
                <h5 class="fw-bold mb-3">🔥 Top Products</h5>

                <ul class="list-group list-group-flush">
                    @foreach($metrics['topProducts'] as $product)
                        <li class="list-group-item">
                            {{ $product }}
                        </li>
                    @endforeach
                </ul>

            </div>
        </div>
    </div>
    <div class="mt-4">
    <div class="card shadow-sm border-0" style="border-radius:15px;">
        <div class="card-body">
            <h5 class="fw-bold mb-3">📈 Supplier Activity</h5>
            <canvas id="supplierChart" height="100"></canvas>
        </div>
    </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const ctx = document.getElementById('supplierChart').getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: {!! json_encode($metrics['chartLabels']) !!},
            datasets: [{
                label: 'Suppliers Added',
                data: {!! json_encode($metrics['chartData']) !!},
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true }
            }
        }
    });
</script>
@endsection