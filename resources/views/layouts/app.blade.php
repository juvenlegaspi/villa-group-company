<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Villa System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
</head>
<body>
<div class="layout">
    <div id="sidebar" class="sidebar">
        @php
            $isAdmin = auth()->user()->is_admin == 1;
            $isOwner = auth()->user()->role == 'owner';
        @endphp

        <div class="sidebar-header">
            <h4>Villa Group of Companies</h4>
            <button id="toggleSidebar" class="toggle-btn">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        @if(!$isOwner)
            @if($isAdmin)
                <a href="{{ url('/users') }}" class="{{ request()->is('users*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
            @endif

            <div class="sidebar-title">Divisions</div>
            <div class="ms-1">
                @foreach($allDepartments as $dept)
                    @php
                        $user = auth()->user();
                        $isAllowedDept = $isAdmin || $user->department_id == $dept->id;
                        $isShipping = str_contains(strtolower($dept->name), 'shipping');
                    @endphp

                    @if($isAllowedDept)
                        @if($isShipping)
                            <div class="sidebar-dropdown-header" onclick="toggleMenu('menu{{ $dept->id }}')">
                                <span>{{ $dept->name }}</span>
                                <i class="bi bi-chevron-down"></i>
                            </div>

                            <div id="menu{{ $dept->id }}" class="sidebar-dropdown {{ request()->is('shipping/*') ? 'show' : '' }}">
                                <a href="{{ route('vessels.index') }}" class="{{ request()->is('shipping/vessels*') ? 'active-menu' : '' }}">
                                    <i class="bi bi-ship"></i>
                                    <span>Vessels</span>
                                </a>
                                <a href="{{ route('tech-defects.index') }}" class="{{ request()->is('shipping/tech-defects*') ? 'active-menu' : '' }}">
                                    <i class="bi bi-tools"></i>
                                    <span>Tech & Defects</span>
                                </a>
                                <a href="{{ route('vessel-certificates.index') }}" class="{{ request()->is('vessel-certificates*') ? 'active-menu' : '' }}">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <span>Certificates</span>
                                </a>
                                @if($isAdmin)
                                    <a href="{{ url('/shipping/dry-docking') }}" class="{{ request()->is('shipping/dry-docking*') ? 'active-menu' : '' }}">
                                        <i class="bi bi-tools"></i>
                                        <span>Dry Docking</span>
                                    </a>
                                @endif
                            </div>
                        @else
                            <a href="{{ url('/departments/' . $dept->id) }}">
                                <i class="bi bi-building"></i>
                                <span>{{ $dept->name }}</span>
                            </a>
                        @endif
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    <div class="main-content">
        <div class="topbar d-flex justify-content-between align-items-center">
            <h5>Welcome, {{ Auth::user()->name }}</h5>
            <div class="d-flex gap-2">
                <a href="{{ url('/profile') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-person"></i> Profile
                </a>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button class="btn btn-danger btn-sm">Logout</button>
                </form>
            </div>
        </div>

        @yield('content')
    </div>
</div>

<script>
    document.getElementById('toggleSidebar').onclick = function () {
        document.getElementById('sidebar').classList.toggle('collapsed');
    };

    function toggleMenu(id) {
        const menu = document.getElementById(id);
        menu.classList.toggle('show');
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
