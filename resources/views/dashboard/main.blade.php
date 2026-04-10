@extends('layouts.app')

@section('content')
<div class="container py-4">

    <h3 class="fw-bold mb-4">🏢 Select Division</h3>
@php
    $colors = [
        'vsli' => 'blue',
        'yatira' => 'purple',
        'jmv' => 'green',
        'mining' => 'orange',
        'it' => 'dark',
        'hr' => 'pink',
        'rd' => 'teal'
    ];

    $icons = [
        'vsli' => '🚢',
        'yatira' => '🏗️',
        'jmv' => '🚚',
        'mining' => '⛏️',
        'it' => '💻',
        'hr' => '👥',
        'rd' => '🧪'
    ];
@endphp
    <div class="row g-4">

        @foreach($divisions as $division)
        @php
        $key = strtolower($division);
        $color = $colors[$key] ?? 'blue';
        $icon = $icons[$key] ?? '🏢';
    @endphp
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ route('division.dashboard', $division) }}" class="text-decoration-none">

            <div class="division-card-modern division-{{ $color }}">

                <div class="division-icon">
                    {{ $icon }}
                </div>

                <h5 class="mt-3 text-white text-capitalize">
                    {{ $division }}
                </h5>

                <p class="text-light small mb-0">
                    Open Dashboard →
                </p>

            </div>

        </a>
            </div>
        @endforeach

    </div>

</div>
@endsection