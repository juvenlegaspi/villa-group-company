@extends('layouts.app')

@section('content')
<div class="container">

    <h4 class="mb-4">Select Division</h4>

    <div class="row g-4">

        @foreach($divisions as $division)
            <div class="col-md-3">
                <a href="#" class="text-decoration-none">
                    <div class="card shadow-sm border-0 text-center p-4 division-card">

                        <h5 class="mb-2">{{ $division }}</h5>

                        <p class="text-muted small">View Dashboard</p>

                    </div>
                </a>
            </div>
        @endforeach

    </div>

</div>
@endsection

