@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<div class="container">
    <h3 class="mb-4">Voyage Logs Dashboard</h3>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-3">
                <h5>Total Voyages</h5>
                <h2 class="text-primary">{{ $totalVoyages }}</h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-3">
                <h5>Active Voyages</h5>
                <h2 class="text-warning">{{ $activeVoyages }}</h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-3">
                <h5>Completed Voyages</h5>
                <h2 class="text-success">{{ $completedVoyages }}</h2>
            </div>
        </div>
    </div>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6>Voyage Status</h6>
                    <div style="width:250px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6>Voyages Per Month</h6>
                    <canvas id="voyageChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Voyages per Vessel</h6>
                    <canvas id="vesselChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Most Used Ports</h6>
                    <div style="width:300px;">
                    <canvas id="portChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Activity Status Distribution</h6>
                    <div style="width:250px;">
                    <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

const statusChart = new Chart(
document.getElementById('statusChart'),
{
type:'pie',
data:{
labels:['Active','Completed'],
datasets:[{
data:[
{{ $activeVoyages }},
{{ $completedVoyages }}
],
backgroundColor:[
'#f0ad4e',
'#28a745'
]
}]
}
});

</script>

<script>

const voyageChart = new Chart(
document.getElementById('voyageChart'),
{
type:'bar',
data:{
labels:{!! json_encode($monthlyVoyages->keys()) !!},
datasets:[{
label:'Voyages',
data:{!! json_encode($monthlyVoyages->values()) !!},
backgroundColor:'#0d6efd'
}]
},
options:{
responsive:true
}
});

const vesselChart = new Chart(
document.getElementById('vesselChart'),
{
type:'bar',
data:{
labels:{!! json_encode($vesselVoyages->keys()) !!},
datasets:[{
label:'Voyages',
data:{!! json_encode($vesselVoyages->values()) !!},
backgroundColor:'#198754'
}]
}
});

const portChart = new Chart(
document.getElementById('portChart'),
{
type:'pie',
data:{
labels:{!! json_encode($portStats->keys()) !!},
datasets:[{
data:{!! json_encode($portStats->values()) !!},
backgroundColor:[
'#0d6efd',
'#ffc107',
'#dc3545',
'#198754',
'#6f42c1'
]
}]
}
});

const activityChart = new Chart(
document.getElementById('activityChart'),
{
type:'doughnut',
data:{
labels:{!! json_encode($activityStats->keys()) !!},
datasets:[{
data:{!! json_encode($activityStats->values()) !!},
backgroundColor:[
'#0d6efd',
'#ffc107',
'#198754',
'#dc3545'
]
}]
}
});
</script>


@endsection