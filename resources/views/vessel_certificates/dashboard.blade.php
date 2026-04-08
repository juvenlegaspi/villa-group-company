@extends('layouts.app')
@section('content')
<div class="container">
    <h4 class="mb-4">Vessel Certificates Dashboard</h4>
    <div class="row g-4">
        <div class="col-md-3">
            <div class="card shadow-sm text-center p-3">
                <h5>Total Certificates</h5>
                <h2 class="text-primary">{{ $totalCertificates }}</h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center p-3">
                <h5>Expired</h5>
                <h2 class="text-danger">{{ $expiredCertificates }}</h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center p-3">
                <h5>Expiring Soon</h5>
                <h2 class="text-warning">{{ $expiringCertificates }}</h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center p-3">
                <h5>Valid</h5>
                <h2 class="text-success">{{ $validCertificates }}</h2>
            </div>
        </div>
    </div>
    <br>
    <a href="{{ route('vessel-certificates.index') }}" class="btn btn-primary"> View Certificates </a>
</div>
<div class="row mt-4">
    <div class="col-md-6">
        <h5 class="text-danger">Expired Certificates</h5>
        <table class="table table-sm table-bordered">
            <thead class="table-danger">
                <tr>
                    <th>Certificate</th>
                    <th>Expiry Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expiredList as $c)
                    <tr>
                        <td>{{ $c->certificate_name }}</td>
                        <td>{{ $c->expiry_date }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2">No expired certificates</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="col-md-6">
        <h5 class="text-warning">Expiring Soon</h5>
        <table class="table table-sm table-bordered">
            <thead class="table-warning">
                <tr>
                    <th>Certificate</th>
                    <th>Expiry Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expiringList as $c)
                    <tr>
                        <td>{{ $c->certificate_name }}</td>
                        <td>{{ $c->expiry_date }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2">No expiring certificates</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection