@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <!-- ================= HEADER ================= -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center flex-wrap">

                <!-- LEFT: Vessel Info -->
                <div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3"
                             style="width:50px;height:50px;">
                            🚢
                        </div>
                        <div>
                            <h4 class="mb-0 fw-bold text-primary">
                                {{ $vessel->vessel_name }}
                            </h4>
                            <small class="text-muted">Vessel Information</small>
                        </div>
                    </div>

                    <div class="row g-2">

                        <div class="col-md-3">
                            <div class="info-box">
                                <small>IMO</small>
                                <div>{{ $vessel->imo_number }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="info-box">
                                <small>Call Sign</small>
                                <div>{{ $vessel->call_sign }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="info-box">
                                <small>Type</small>
                                <div>{{ $vessel->vessel_type }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="info-box">
                                <small>DWT</small>
                                <div>{{ $vessel->dwt }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="info-box">
                                <small>Fuel</small>
                                <div>{{ $vessel->fuel_type }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="info-box">
                                <small>Speed</small>
                                <div>{{ $vessel->service_speed }} knots</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="info-box">
                                <small>Status</small>
                                <div>
                                    <span class="badge bg-info text-dark px-2 py-1">
                                        {{ $vessel->vessel_status }}
                                    </span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- RIGHT: Buttons -->
                <div class="mt-3 mt-md-0">
                    <a href="/shipping/vessels" class="btn btn-light border me-2">
                        ← Back
                    </a>

                    <a href="/shipping/vessels/{{ $vessel->id }}/logs/create"
                       class="btn btn-primary shadow-sm">
                        + Add Voyage
                    </a>
                </div>

            </div>

        </div>
    </div>

    <!-- ================= SEARCH ================= -->
    <div class="card shadow-sm mb-3 border-0">
        <div class="card-body">

            <form method="GET" class="row g-2">

                <div class="col-md-4">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="🔍 Search Voyage / Port / Cargo"
                           value="{{ request('search') }}">
                </div>

                <div class="col-md-3">
                    <select name="sort" class="form-select">
                        <option value="">Sort by</option>
                        <option value="activity" {{ request('sort')=='activity'?'selected':'' }}>
                            Activity
                        </option>
                        <option value="date" {{ request('sort')=='date'?'selected':'' }}>
                            Date
                        </option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-primary w-100">
                        Search
                    </button>
                </div>

                <div class="col-md-2">
                    <a href="" class="btn btn-secondary w-100">
                        Reset
                    </a>
                </div>

            </form>

        </div>
    </div>

    <!-- ================= TABLE ================= -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">

            <table class="table align-middle mb-0 custom-table">

                <thead>
                    <tr>
                        <th>Voyage</th>
                        <th>Date Start</th>
                        <th>Date End</th>
                        <th>Port</th>
                        <th>No.</th>
                        <th>Cargo</th>
                        <th>Crew</th>
                        <th>Activities</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                @foreach($voyages as $voyage)
                    <tr onclick="window.location='/shipping/voyage-logs/{{ $voyage->voyage_id }}'">

                        <td>
                            <span class="badge bg-primary px-3 py-2">
                                {{ $voyage->voyage_code }}
                            </span>
                        </td>

                        <td>{{ \Carbon\Carbon::parse($voyage->date_created)->format('M d, Y') }}</td>

                        <td>
                            {{ $voyage->date_completed 
                                ? \Carbon\Carbon::parse($voyage->date_completed)->format('M d, Y') 
                                : '-' }}
                        </td>

                        <td>{{ $voyage->port_location }}</td>
                        <td>{{ $voyage->voyage_no }}</td>
                        <td>{{ $voyage->cargo_type }}</td>
                        <td>{{ $voyage->crew_on_board }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">
                               {{ $voyage->details->count() }}
                            </span>
                        </td>

                        <td>
                            @if($voyage->status == 'OPEN')
                                <span class="badge bg-warning text-dark px-3 py-2">
                                    ACTIVE
                                </span>
                            @else
                                <span class="badge bg-success px-3 py-2">
                                    COMPLETED
                                </span>
                            @endif
                        </td>

                    </tr>
                @endforeach
                </tbody>

            </table>

        </div>

        <div class="p-3">
            {{ $voyages->links() }}
        </div>
    </div>

</div>

<!-- ================= STYLE ================= -->
<style>

.info-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 8px 12px;
    border: 1px solid #eee;
}

.info-box small {
    color: #6c757d;
    font-size: 12px;
}

.info-box div {
    font-weight: 600;
}

.custom-table thead {
    background: #f1f3f5;
}

.custom-table tbody tr {
    transition: 0.2s;
    cursor: pointer;
}

.custom-table tbody tr:hover {
    background: #f8f9fa;
    transform: scale(1.01);
}

</style>

@endsection