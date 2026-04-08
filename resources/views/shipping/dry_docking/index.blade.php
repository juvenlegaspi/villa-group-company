@extends('layouts.app')
@section('content')
<div class="container">
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">🚢 Dry Docking Monitoring</h3>
        <a href="{{ url('/shipping/dry-docking/create') }}" class="btn btn-primary">
            + Add Dry Docking
        </a>
    </div>
    <!-- SUCCESS MESSAGE -->
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif
    <!-- CARD -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <!-- HEADER -->
                <thead class="table-dark">
                    <tr>
                        <th width="300">Dry Dock</th>
                        <th>Arrival</th>
                        <th>Docking</th>
                        <th>Laydays</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <!-- BODY -->
                <tbody>
                    @forelse($headers as $h)
                    <tr>
                        <!-- DRY DOCK ID + VESSEL -->
                        <td>
                            <a href="{{ url('/shipping/dry-docking/'.$h->id.'/details') }}" 
                               class="text-decoration-none d-block p-2 rounded hover-row">

                                <div class="fw-bold text-primary">
                                    DR-{{ str_pad($h->id, 3, '0', STR_PAD_LEFT) }}
                                </div>
                                <small class="text-muted">
                                    {{ $h->vessel->vessel_name ?? '-' }}
                                </small>
                            </a>
                        </td>
                        <!-- DATES -->
                        <td>{{ $h->arrival_date ?? '-' }}</td>
                        <td>{{ $h->docking_date ?? '-' }}</td>
                        <td>{{ $h->laydays ?? '-' }}</td>
                        <!-- STATUS -->
                        <td>
                            @if($h->status == 'operational')
                                <span class="badge bg-success px-3 py-2">
                                    Operational
                                </span>
                            @else
                                <span class="badge bg-danger px-3 py-2">
                                    Non-Operational
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No dry docking records found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
<!-- OPTIONAL STYLE -->
<style>
.hover-row:hover {
    background-color: #f8f9fa;
    transition: 0.2s;
}
</style>