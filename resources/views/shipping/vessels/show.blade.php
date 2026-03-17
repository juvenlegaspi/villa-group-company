@extends('layouts.app')

@section('content')


<div class="card shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-2 fw-bold text-primary">
                🚢 {{ $vessel->vessel_name }}
            </h3>
            <div class="text-muted">
                <span class="me-3">
                    <strong>IMO:</strong> {{ $vessel->imo_number }}
                </span>
                <span class="me-3">
                    <strong>Call Sign:</strong> {{ $vessel->call_sign }}
                </span>
                <span class="me-3">
                    <strong>Vessel Type:</strong> {{ $vessel->vessel_type }}
                </span>
                <span class="me-3">
                    <strong>DWT:</strong> {{ $vessel->dwt }}
                </span>
                <span class="me-3">
                    <strong>Fuel_type:</strong> {{ $vessel->fuel_type }}
                </span>
                <span class="me-3">
                    <strong>Service speed:</strong> {{ $vessel->service_speed }}
                </span>
                <span class="me3">
                    <strong>Status:</strong> {{ $vessel->vessel_status }}
                </span>
            </div>
        </div>
    </div>
</div>

<a href="/shipping/vessels" class="btn btn-outline-secondary mb-3">
    ← Back to Vessel List
</a>
<a href="/shipping/vessels/{{ $vessel->id }}/logs/create" 
   class="btn btn-primary mb-3">
   + Add Voyage Log
</a>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <form method="GET" class="row mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search Voyage / Port / Cargo" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="sort" class="form-control">
                    <option value="">Sort by ID</option>
                    <option value="activity" {{ request('sort')=='activity'?'selected':'' }}> Sort by Activity </option> 
                    <option value="date" {{ request('sort')=='date'?'selected':'' }}> Sort by Date </option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Search</button>
            </div>
            <div class="col-md-2">
                <a href="" class="btn btn-secondary w-100">Reset</a>
            </div>
        </form>
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Voyage ID</th>
                    <th>Date started</th>
                    <th>Date Completed</th>
                    <th>Port</th>
                    <th>Voyage #</th>
                    <th>Cargo</th>
                    <th>Crew</th>
                    <th>Activities</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($voyages as $voyage)
                    <tr>
                        <td>
                            <a href="/shipping/voyage-logs/{{ $voyage->voyage_id }}" 
                                class="badge bg-primary rounded-pill px-3 py-2 text-decoration-none">
                                {{ $voyage->voyage_code }}
                            </a>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($voyage->date_created)->format('m-d-Y') }}</td>
                        <td>{{ $voyage->date_completed ? \Carbon\Carbon::parse($voyage->date_completed)->format('m-d-Y') : '-' }}</td>
                        <td>{{ $voyage->port_location }}</td>
                        <td>{{ $voyage->voyage_no }}</td>
                        <td>{{ $voyage->cargo_type }}</td>
                        <td>{{ $voyage->crew_on_board }}</td>
                        <td>{{ $voyage->details_count }}</td>
                        <td> 
                            @if($voyage->status == 'OPEN')
                                <span class="badge bg-warning text-dark px-3 py-2">ACTIVE</span>
                            @else
                                <span class="badge bg-success px-3 py-2">COMPLETED</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-3">
{{ $voyages->links() }}
</div>
    </div>
    <style>
        .table-hover tbody tr:hover{
            background:#f5f7fa;
            cursor:pointer;
        }

    </style>
</div>

@endsection