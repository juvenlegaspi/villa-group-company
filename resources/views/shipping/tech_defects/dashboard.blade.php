@extends('layouts.app')
@section('content')

<div class="container">
    <h3 class="mb-4">Tech & Defect Dashboard</h3>
    <div class="row">
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Total Reports</h6>
                    <h2 class="text-primary">{{ $totalReports }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Open</h6>
                    <h2 class="text-danger">{{ $open }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Ongoing</h6>
                    <h2 class="text-warning">{{ $ongoing }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Completed</h6>
                    <h2 class="text-success">{{ $completed }}</h2>
                </div>
            </div>
        </div>
        <div class="row mt-4">
        <!-- Most Problematic Vessel -->
            <div class="col-md-6">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h5>Most Problematic Vessel</h5>
                        @if($topVessel)
                            <h3 class="text-danger mt-3">{{ $topVessel->vessel->vessel_name }}</h3>
                            <p class="text-muted">{{ $topVessel->total }} defect reports</p>
                        @endif
                    </div>
                </div>
            </div>
            <!-- Critical Defects -->
            <div class="col-md-6">
                <div class="card shadow-sm border-danger text-center">
                    <div class="card-body">
                        <h5 class="text-danger">⚠ Critical Defects</h5>
                        <h2 class="text-danger">{{ $criticalDefects }}</h2>
                        <p class="text-muted">Need Immediate Attention</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Status Distribution</h5>
                    <div class="d-flex justify-content-center">
                        <div style="width:300px;">
                            <canvas id="defectChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Defects per Vessel</h5>
                    <div class="d-flex justify-content-center">
                        <div style="width:350px;">
                            <canvas id="vesselChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h5>Latest Defect Reports</h5>
            <table class="table table-sm table-bordered mt-3">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Vessel</th>
                        <th>Status</th>
                        <th>Date Identified</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($latestReports as $r)
                        <tr>
                            <td> <a href="{{ route('tech-defects.show',$r->id) }}"> TD-{{ $r->id }} </a></td>
                            <td>{{ $r->vessel->vessel_name }}</td>
                            <td><span class="badge bg-secondary">{{ $r->status }}</span></td>
                            <td>{{ $r->date_identified }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h5>Monthly Defect Trend</h5>
                    <div style="width:400px; margin:auto;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const ctx = document.getElementById('defectChart');new Chart(ctx,{type:'doughnut',
        data:{
            labels:['Open','Ongoing','Waiting 3rd Party','Completed'],
            datasets:[{
                data:[
                    {{ $open }},
                    {{ $ongoing }},
                    {{ $waiting }},
                    {{ $completed }}
                ]
            }]
        }
    });
</script>

<script>
    const vesselCtx = document.getElementById('vesselChart');
    new Chart(vesselCtx,{
        type:'bar',
            data:{
                labels:[
                    @foreach($vesselDefects as $v)
                        "{{ $v->vessel->vessel_name }}",
                    @endforeach
                ],
                datasets:[{
                    label:'Defects',
                    data:[
                        @foreach($vesselDefects as $v)
                            {{ $v->total }},
                        @endforeach
                    ]
                }]
            }
    });
</script>
<script>
    const monthlyCtx = document.getElementById('monthlyChart');
    new Chart(monthlyCtx,{
        type:'line',
        data:{
            labels:[
                @foreach($monthlyDefects as $m)
                    "Month {{ $m->month }}",
                @endforeach
            ],
            datasets:[{
                label:'Defects',
                data:[
                    @foreach($monthlyDefects as $m)
                        {{ $m->total }},
                    @endforeach
                ],
                borderWidth:3,
                tension:0.3,
                fill:false
            }]
        }
    });
</script>

@endsection