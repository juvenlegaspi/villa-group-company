@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Vessel Certificates Monitoring</h4>
            <p class="text-muted mb-0">Monitor certificate validity per vessel.</p>
        </div>
    </div>

    <div class="row">
        @forelse($vessels as $vessel)
            <div class="col-md-3 mb-4">
                <a href="{{ route('vessel.certificates.show', $vessel->id) }}" class="text-decoration-none">
                    <div class="card shadow-sm text-center h-100">
                        <div class="card-body">
                            <h5 class="mb-3">{{ $vessel->vessel_name }}</h5>

                            @if($vessel->expired_count > 0)
                                <div class="badge bg-danger mb-1">
                                    {{ $vessel->expired_count }} Expired
                                </div>
                            @endif

                            @if($vessel->expiring_count > 0)
                                <div class="badge bg-warning text-dark">
                                    {{ $vessel->expiring_count }} Expiring Soon
                                </div>
                            @endif

                            @if($vessel->expired_count == 0 && $vessel->expiring_count == 0)
                                <div class="badge bg-success">
                                    All Certificates Valid
                                </div>
                            @endif
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    No vessels available for certificate monitoring.
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
