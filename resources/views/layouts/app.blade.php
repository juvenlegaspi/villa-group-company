<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Villa System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f4f6f9;
        }

        .layout {
            display: flex;
        }

        .sidebar {
            width: 250px;
            min-height: 100vh;
            background: linear-gradient(180deg, #1e3c72, #2a5298);
            padding: 15px;
            color: white;
            transition: all .3s;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 10px;
            color: #dbeafe;
            text-decoration: none;
            margin-bottom: 5px;
            transition: 0.2s;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        .sidebar a i {
            font-size: 18px;
            width: 25px;
            text-align: center;
        }

        .sidebar a.active,
        .active-menu {
            background: #3b82f6;
            color: white !important;
            font-weight: bold;
        }

        .sidebar-title {
            margin-top: 15px;
            margin-bottom: 5px;
            font-size: 13px;
            color: #cbd5f1;
        }

        .sidebar-dropdown {
            margin-left: 15px;
            display: none;
        }

        .sidebar-dropdown.show {
            display: block;
        }

        .sidebar-dropdown-header {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            cursor: pointer;
            border-radius: 8px;
        }

        .sidebar-dropdown-header:hover {
            background: rgba(255,255,255,0.15);
        }

        .sidebar.collapsed span,
        .sidebar.collapsed .sidebar-title,
        .sidebar.collapsed h4 {
            display: none;
        }

        .sidebar.collapsed a {
            justify-content: center;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .topbar {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .division-card {
                        transition: 0.3s;
                        border-radius: 12px;
                    }
                    .division-card:hover {
                        transform: translateY(-5px);
                        background: #f8fafc;
                    }
                    /* ===== DIVISION CARD MODERN ===== */
                    .division-card-modern {
                        background: linear-gradient(135deg, #1e3c72, #2a5298);
                        border-radius: 16px;
                        padding: 30px 20px;
                        text-align: center;
                        transition: all 0.3s ease;
                        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
                        position: relative;
                        overflow: hidden;
                    }
                    .division-card-modern:hover {
                        transform: translateY(-8px) scale(1.02);
                        box-shadow: 0 12px 30px rgba(0,0,0,0.2);
                    }
                    /* glowing effect */
                    .division-card-modern::before {
                        content: '';
                        position: absolute;
                        width: 150%;
                        height: 150%;
                        background: rgba(255,255,255,0.1);
                        top: -50%;
                        left: -50%;
                        transform: rotate(25deg);
                        transition: 0.5s;
                    }

                    .division-card-modern:hover::before {
                        top: -20%;
                        left: -20%;
                    }

                    /* icon */
                    .division-icon {
                        font-size: 40px;
                        background: rgba(255,255,255,0.2);
                        width: 70px;
                        height: 70px;
                        margin: auto;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }

                    /* text */
                    .division-card-modern h5 {
                        font-weight: 600;
                    }

                    .division-card-modern p {
                        opacity: 0.8;
                    }
                    /* ===== COLORS PER DIVISION ===== */
                    .division-blue {
                        background: linear-gradient(135deg, #1e3c72, #2a5298);
                    }

                    .division-purple {
                        background: linear-gradient(135deg, #6a11cb, #2575fc);
                    }

                    .division-green {
                        background: linear-gradient(135deg, #11998e, #38ef7d);
                    }

                    .division-orange {
                        background: linear-gradient(135deg, #f7971e, #ffd200);
                    }

                    .division-dark {
                        background: linear-gradient(135deg, #232526, #414345);
                    }

                    .division-pink {
                        background: linear-gradient(135deg, #ff758c, #ff7eb3);
                    }

                    .division-teal {
                        background: linear-gradient(135deg, #136a8a, #267871);
                    }
    </style>
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
