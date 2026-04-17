@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-0">Vessel Certificate Monitoring</h3>
            <small class="text-muted">
                Vessel: <strong>{{ $vessel->vessel_name }}</strong> |
                Today: {{ $today->format('F d, Y') }}
            </small>
        </div>

        <a href="{{ route('vessel-certificates.index') }}" class="btn btn-outline-secondary">
            Back
        </a>
    </div>

    <div class="mb-3">
        <a href="{{ route('vessel-certificates.add', $vessel->id) }}" class="btn btn-success shadow">
            Add Certificate
        </a>
    </div>

    @php
        $validCount = $certificates->filter(fn ($certificate) => $certificate->expiry_date->gt(now()->copy()->addDays(30)))->count();
        $expiringCount = $certificates->filter(fn ($certificate) => $certificate->expiry_date->between(now(), now()->copy()->addDays(30)))->count();
        $expiredCount = $certificates->filter(fn ($certificate) => $certificate->expiry_date->lt(now()))->count();
    @endphp

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center p-3">
                <h6>Total</h6>
                <h4>{{ $certificates->count() }}</h4>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center p-3 bg-success text-white">
                <h6>Valid</h6>
                <h4>{{ $validCount }}</h4>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center p-3 bg-warning">
                <h6>Expiring</h6>
                <h4>{{ $expiringCount }}</h4>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center p-3 bg-danger text-white">
                <h6>Expired</h6>
                <h4>{{ $expiredCount }}</h4>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3 p-3">
        <form method="GET">
            <div class="row">
                <div class="col-md-5">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control"
                        placeholder="Search certificate..."
                    >
                </div>

                <div class="col-md-4">
                    <select name="filter" class="form-control">
                        <option value="">All Certificates</option>
                        <option value="valid" {{ request('filter') == 'valid' ? 'selected' : '' }}>Valid</option>
                        <option value="expiring" {{ request('filter') == 'expiring' ? 'selected' : '' }}>Expiring</option>
                        <option value="expired" {{ request('filter') == 'expired' ? 'selected' : '' }}>Expired</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary w-100">Search</button>
                    <a href="{{ route('vessel.certificates.show', $vessel->id) }}" class="btn btn-secondary w-100">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Certificate</th>
                        <th>Created</th>
                        <th>Issue Date</th>
                        <th>Expiry Date</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Document</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($certificates as $certificate)
                        @php
                            $days = now()->diffInDays($certificate->expiry_date, false);
                            $status = 'Valid';
                            $color = 'success';
                            $rowClass = '';

                            if ($days < 0) {
                                $status = 'Expired';
                                $color = 'danger';
                                $rowClass = 'table-danger';
                            } elseif ($days <= 30) {
                                $status = 'Expiring';
                                $color = 'warning';
                                $rowClass = 'table-warning';
                            }
                        @endphp

                        <tr class="{{ $rowClass }}">
                            <td><strong>{{ $certificate->certificate_name }}</strong></td>
                            <td>{{ optional($certificate->created_at)->format('F d, Y h:i A') }}</td>
                            <td>{{ optional($certificate->issue_date)->format('F d, Y') }}</td>
                            <td>{{ optional($certificate->expiry_date)->format('F d, Y') }}</td>
                            <td>{{ $days }}</td>
                            <td>
                                <span class="badge rounded-pill bg-{{ $color }} px-3 py-2">
                                    {{ $status }}
                                </span>
                            </td>
                            <td>
                                @if($certificate->document)
                                    <a
                                        href="{{ asset('uploads/certificates/' . $certificate->document) }}"
                                        target="_blank"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        View
                                    </a>

                                    <a
                                        href="{{ asset('uploads/certificates/' . $certificate->document) }}"
                                        download
                                        class="btn btn-sm btn-outline-success"
                                    >
                                        Download
                                    </a>
                                @else
                                    <span class="badge bg-secondary">No File</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('vessel-certificates.edit', $certificate->id) }}" class="btn btn-sm btn-primary">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">No certificates found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
