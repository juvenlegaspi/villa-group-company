@extends('layouts.app')

@section('content')
<div class="container">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-0">🚢 Vessel Certificate Monitoring</h3>
            <small class="text-muted">
                Vessel: <strong>{{ $vessel->vessel_name }}</strong> |
                Today: {{ \Carbon\Carbon::now()->format('F d, Y') }}
            </small>
        </div>

        <a href="{{ route('vessel-certificates.index') }}" class="btn btn-outline-secondary">
            ← Back
        </a>
    </div>

    {{-- ADD BUTTON --}}
    <div class="mb-3">
        <a href="{{ route('vessel-certificates.add',$vessel->id) }}" 
           class="btn btn-success shadow">
            ➕ Add Certificate
        </a>
    </div>

    {{-- STATS --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center p-3">
                <h6>Total</h6>
                <h4>{{ count($certificates) }}</h4>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center p-3 bg-success text-white">
                <h6>Valid</h6>
                <h4>{{ $certificates->where('expiry_date','>',now())->count() }}</h4>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center p-3 bg-warning">
                <h6>Expiring</h6>
                <h4>{{ $certificates->whereBetween('expiry_date',[now(), now()->addDays(30)])->count() }}</h4>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center p-3 bg-danger text-white">
                <h6>Expired</h6>
                <h4>{{ $certificates->where('expiry_date','<',now())->count() }}</h4>
            </div>
        </div>
    </div>

    {{-- SEARCH --}}
    <div class="card shadow-sm border-0 mb-3 p-3">
        <form method="GET">
            <div class="row">
                <div class="col-md-5">
                    <input type="text" name="search" value="{{ request('search') }}"
                        class="form-control" placeholder="🔍 Search certificate...">
                </div>

                <div class="col-md-4">
                    <select name="filter" class="form-control">
                        <option value="">All Certificates</option>
                        <option value="valid" {{ request('filter')=='valid'?'selected':'' }}>Valid</option>
                        <option value="expiring" {{ request('filter')=='expiring'?'selected':'' }}>Expiring</option>
                        <option value="expired" {{ request('filter')=='expired'?'selected':'' }}>Expired</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary w-100">Search</button>
                    <a href="{{ route('vessel-certificates.show',$vessel->id) }}" 
                       class="btn btn-secondary w-100">Reset</a>
                </div>
            </div>
        </form>
    </div>

    {{-- TABLE --}}
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
                    @forelse($certificates as $c)
                        @php
                            $expiry = \Carbon\Carbon::parse($c->expiry_date);
                            $days = now()->diffInDays($expiry, false);

                            $status = 'VALID';
                            $color = 'success';

                            if($days < 0){
                                $status = 'EXPIRED';
                                $color = 'danger';
                            } elseif($days <= 30){
                                $status = 'EXPIRING';
                                $color = 'warning';
                            }
                        @endphp

                        <tr class="
                            @if($days < 0) table-danger
                            @elseif($days <= 30) table-warning
                            @endif
                        ">
                            <td><strong>{{ $c->certificate_name }}</strong></td>
                            <td>{{ $c->created_at }}</td>
                            <td>{{ $c->issue_date }}</td>
                            <td>{{ $c->expiry_date }}</td>
                            <td>{{ intval($days) }}</td>

                            <td>
                                <span class="badge rounded-pill bg-{{ $color }} px-3 py-2">
                                    {{ $status }}
                                </span>
                            </td>

                            <td>
                                @if($c->document)
                                    <a href="{{ asset('uploads/certificates/'.$c->document) }}" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-primary">
                                       View
                                    </a>

                                    <a href="{{ asset('uploads/certificates/'.$c->document) }}" 
                                       download 
                                       class="btn btn-sm btn-outline-success">
                                       Download
                                    </a>
                                @else
                                    <span class="badge bg-secondary">No File</span>
                                @endif
                            </td>

                            <td>
                                <a href="{{ route('vessel-certificates.edit', $c->id) }}" 
                                   class="btn btn-sm btn-primary">
                                    ✏ Edit
                                </a>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                No certificates found
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>

</div>
@endsection