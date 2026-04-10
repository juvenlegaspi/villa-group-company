<!DOCTYPE html>
    <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Villa System</title>
            <!-- BOOTSTRAP -->
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        </head>
        <body>
            <div class="layout">
                <!-- SIDEBAR -->
                <div id="sidebar" class="sidebar">
                    @php
                        $isAdmin = auth()->user()->is_admin == 1;
                    @endphp
                    <div class="sidebar-header">
                        <h4>Villa Group of Companies</h4>
                        <button id="toggleSidebar" class="toggle-btn">
                            <i class="bi bi-list"></i>
                        </button>
                    </div>
                    <!-- DASHBOARD -->
                    <a href="/dashboard" class="{{ request()->is('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                    <!-- USERS -->
                    @if($isAdmin)
                        <a href="/users" class="{{ request()->is('users*') ? 'active' : '' }}">
                            <i class="bi bi-people"></i>
                            <span>Users</span>
                        </a>
                    @endif
                    <!-- DIVISIONS -->
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
                                    <!-- HEADER -->
                                    <div class="sidebar-dropdown-header" onclick="toggleMenu('menu{{ $dept->id }}')">
                                        <span>{{ $dept->name }}</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </div>
                                    <!-- DROPDOWN -->
                                    <div id="menu{{ $dept->id }}" class="sidebar-dropdown {{ request()->is('shipping/*') ? 'show' : '' }}">
                                        <a href="{{ url('/shipping/vessels') }}"
                                            class="{{ request()->is('shipping/vessels') ? 'active-menu' : '' }}">
                                            <i class="bi bi-ship"></i>
                                            <span>Vessels</span>
                                        </a>
                                        <a href="{{ url('/shipping/tech-defects') }}"
                                            class="{{ request()->is('shipping/tech-defects') ? 'active-menu' : '' }}">
                                            <i class="bi bi-tools"></i>
                                            <span>Tech & Defects</span>
                                        </a>
                                        <a href="{{ route('vessel-certificates.index') }}"
                                            class="{{ request()->is('vessel-certificates*') ? 'active-menu' : '' }}">
                                            <i class="bi bi-file-earmark-text"></i>
                                            <span>Certificates</span>
                                        </a>
                                        @if($isAdmin)
                                        <a href="{{ url('/shipping/dry-docking') }}"
                                            class="{{ request()->is('shipping/dry-docking') ? 'active-menu' : '' }}">
                                            <i class="bi bi-tools"></i>
                                            <span>Dry Docking</span>
                                        </a>
                                        @endif
                                    </div>
                                @else
                                    <!-- NORMAL -->
                                    <a href="{{ url('/departments/'.$dept->id) }}">
                                        <i class="bi bi-building"></i>
                                        <span>{{ $dept->name }}</span>
                                    </a>
                                @endif
                            @endif
                        @endforeach
                    </div>
                </div>
                <!-- MAIN -->
                <div class="main-content">
                    <div class="topbar d-flex justify-content-between align-items-center">
                        <h5>Welcome, {{ Auth::user()->name }} 👋</h5>
                        <div class="d-flex gap-2">
                            <a href="/profile" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-person"></i> Profile
                            </a>
                            <form method="POST" action="/logout">
                                @csrf
                                <button class="btn btn-danger btn-sm">Logout</button>
                            </form>
                        </div>
                    </div>
                    @yield('content')
                </div>
            </div>
            <!-- JS -->
            <script>
                document.getElementById("toggleSidebar").onclick = function(){
                    document.getElementById("sidebar").classList.toggle("collapsed");
                }
                function toggleMenu(id){
                    let menu = document.getElementById(id);
                    menu.classList.toggle("show");
                }
            </script>
        </body>
    </html>