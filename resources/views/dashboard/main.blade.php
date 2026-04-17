@extends('layouts.app')

@section('content')
<div class="container py-4">

    <h3 class="fw-bold mb-4">🏢 Select Division</h3>

    @php
    $colors = [
        'villa shipping' => 'blue',
        'yatira construction' => 'purple',
        'mining' => 'orange',
        'it' => 'dark',
        'hr' => 'pink',
        'r&d' => 'teal'
    ];

    $icons = [
        'villa shipping' => '🚢',
        'yatira construction' => '🏗️',
        'mining' => '⛏️',
        'it' => '💻',
        'hr' => '👥',
        'r&d' => '🧪'
    ];

    $user = auth()->user();
    $isAdmin = $user->is_admin == 1 || $user->role == 'owner';
    @endphp

    <div class="row g-4">
        @foreach($departments as $dept)

            @php
                $key = strtolower(trim($dept->name));

                $color = $colors[$key] ?? 'blue';
                $icon = $icons[$key] ?? '🏢';

                // 🔥 SAME LOGIC SA SIDEBAR
                $show = $isAdmin || $user->department_id == $dept->id;
            @endphp

            @if($show)
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ route('division.dashboard', $dept->name) }}" class="text-decoration-none">

                    <div class="division-card-modern division-{{ $color }}">
                        <div class="division-icon">
                            {{ $icon }}
                        </div>

                        <h5 class="mt-3 text-white">
                            {{ $dept->name }}
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