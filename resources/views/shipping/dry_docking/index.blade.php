@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Dry Docking Monitoring</h3>
        <a href="{{ url('/shipping/dry-docking/create') }}" class="btn btn-primary">
            Add Dry Docking
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th width="300">Dry Dock</th>
                        <th>Arrival</th>
                        <th>Docking</th>
                        <th>Laydays</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($headers as $header)
                        <tr>
                            <td>
                                <a
                                    href="{{ url('/shipping/dry-docking/' . $header->id . '/details') }}"
                                    class="text-decoration-none d-block p-2 rounded hover-row"
                                >
                                    <div class="fw-bold text-primary">
                                        DR-{{ str_pad($header->id, 3, '0', STR_PAD_LEFT) }}
                                    </div>
                                    <small class="text-muted">
                                        {{ $header->vessel->vessel_name ?? '-' }}
                                    </small>
                                </a>
                            </td>
                            <td>{{ optional($header->arrival_date)->format('M d, Y') ?? '-' }}</td>
                            <td>{{ optional($header->docking_date)->format('M d, Y') ?? '-' }}</td>
                            <td>{{ $header->laydays ?? '-' }}</td>
                            <td>
                                @if($header->status === 'operational')
                                    <span class="badge bg-success px-3 py-2">Operational</span>
                                @else
                                    <span class="badge bg-danger px-3 py-2">Non-Operational</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No dry docking records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

<style>
.hover-row:hover {
    background-color: #f8f9fa;
    transition: 0.2s;
}
</style>
