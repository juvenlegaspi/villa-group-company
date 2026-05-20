@extends('layouts.app')

@section('content')
<div class="container py-4">

    <h3 class="fw-bold mb-4">🏢 Select Division</h3>

    @php
    $colors = [
        'yatira' => 'blue',
        'villa shipping lines' => 'purple',
        'jmv' => 'orange',
        'corporate' => 'green'
    ];

    $icons = [
        'yatira' => '🏗️',
        'villa shipping lines' => '🚢',
        'jmv' => '⛏️',
        'corporate' => '🏢'
    ];

    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    @endphp

    <div class="row g-4">
        @foreach($divisions as $div)

            @php
                $key = strtolower(trim($div->name));

                $color = $colors[$key] ?? 'blue';
                $icon = $icons[$key] ?? '🏢';

                // 🔥 SAME LOGIC SA SIDEBAR
                $show = $isAdmin || $user->division_id == $div->id;
            @endphp

            @if($show)
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ route('division.dashboard', $div->name) }}" class="text-decoration-none">

                    <div class="division-card-modern division-{{ $color }}">
                        <div class="division-icon">
                            {{ $icon }}
                        </div>

                        <h5 class="mt-3 text-white">
                            {{ $div->name }}
                        </h5>

                        <p class="text-light small mb-0">
                            Open Dashboard →
                        </p>
                    </div>

                </a>
            </div>
            @endif

        @endforeach
    </div>

</div>
@endsection
