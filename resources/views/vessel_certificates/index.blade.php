@extends('layouts.app')
@section('content')
<div class="container">
    <h4 class="mb-4">Vessel Certificates Monitoring</h4>
    <div class="row">
        @foreach($vessels as $v)
            <div class="col-md-3 mb-4">
                <a href="{{ route('vessel.certificates.show',$v->id) }}" style="text-decoration:none">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h5>🚢 {{ $v->vessel_name }}</h5>
                            @if($v->expired_count > 0)
                                <div class="badge bg-danger mb-1">
                                    {{ $v->expired_count }} Expired
                                </div>
                            @endif
                            @if($v->expiring_count > 0)
                                <div class="badge bg-warning text-dark">
                                    {{ $v->expiring_count }} Expiring Soon
                                </div>
                            @endif
                            @if($v->expired_count == 0 && $v->expiring_count == 0)
                                <div class="badge bg-success">
                                    All Certificates Valid
                                </div>
                            @endif
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endsection