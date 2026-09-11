@extends('layouts.app')
@section('title', $application['name'].' | Villa Shipping Lines')
@section('content')
<section class="d-flex align-items-center justify-content-center p-4" style="min-height:calc(100svh - 74px);background:linear-gradient(145deg,#fbfafb,#f3f6fa)">
    <div class="card text-center p-4 p-md-5" style="max-width:500px">
        <div class="d-grid mx-auto mb-3 rounded-4" style="place-items:center;width:64px;height:64px;background:#edf4fd;color:#24477f"><i class="bi {{ $application['icon'] }} fs-2"></i></div>
        <h1 class="h3 fw-bold" style="color:#17345f">{{ $application['name'] }}</h1>
        <p class="text-secondary">{{ $application['description'] }}</p>
        <span class="badge rounded-pill text-bg-warning mx-auto mb-4">Coming soon</span>
        <a class="btn btn-primary" href="{{ route('shipping.applications') }}"><i class="bi bi-arrow-left me-1"></i> Back to applications</a>
    </div>
</section>
@endsection
