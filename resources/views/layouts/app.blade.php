<!DOCTYPE html>
<html>
    <head>
        <title>Villa System</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        body{
            margin:0;
            background:#f4f6f9;
        }
        /* MAIN LAYOUT */
        .layout{
            display:flex;
        }
        /* SIDEBAR */
        .sidebar{
            width:250px;
            min-height:100vh;
            background:linear-gradient(180deg,#1e3c72,#2a5298);
            color:white;
            padding:20px;
            transition:all .3s;
        }
        .sidebar.collapsed{
            width:70px;
        }
        /* SIDEBAR HEADER */
        .sidebar-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:20px;
        }
        /* SIDEBAR LINKS */
        .sidebar a{
            display:flex;
            align-items:center;
            gap:10px;
            padding:10px;
            border-radius:8px;
            margin-bottom:5px;
            text-decoration:none;
            color:#cbd5e1;
        }
        .sidebar a:hover,
        .sidebar a.active{
            background:rgba(255,255,255,0.15);
            color:white;
        }
        .sidebar-title{
            margin-top:20px;
            margin-bottom:10px;
            font-weight:bold;
        }
        /* COLLAPSED MODE */
        .sidebar.collapsed span{
        display:none;
        }
        .sidebar.collapsed h4{
            display:none;
        }
        .sidebar.collapsed .sidebar-title{
            display:none;
        }
        .sidebar.collapsed a{
            justify-content:center;
        }
        .sidebar.collapsed a i{
             font-size:22px;
        }
        /* MAIN CONTENT */
        .main-content{
            flex:1;
            padding:20px;
        }
        /* TOPBAR */
        .topbar{
            background:white;
            padding:15px;
            border-radius:10px;
            margin-bottom:20px;
            box-shadow:0 2px 5px rgba(0,0,0,0.1);
        }
        /* TOGGLE BUTTON */
        .toggle-btn{
            border:none;
            background:transparent;
            color:white;
            font-size:22px;
        }
        /* TABLE STYLE */
        .table{
            background:white;
        }
        /* Sidebar dropdown design */
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            color: #dbeafe;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .sidebar-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: #ffffff;
        }
        .sidebar-dropdown {
            margin-left: 10px;
            padding-left: 10px;
            border-left: 2px solid rgba(255,255,255,0.1);
        }
        .sidebar-header {
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .sidebar-header:hover {
            background-color: rgba(255,255,255,0.1);
        }
        .sidebar-icon {
            font-size: 14px;
        }
        .active-menu {
            background-color: #3b82f6;
            color: #fff !important;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="layout">
    <!-- SIDEBAR -->
        <div id="sidebar" class="sidebar">
            @php
                $isAdmin = auth()->user()->is_admin == 1;
                //$allowedRoles = ['manager','staff','it','r&d','hr','captain'];
            @endphp
            <div class="sidebar-header">
                <h4>Villa System and Companies</h4>
                <button id="toggleSidebar" class="toggle-btn">
                    <i class="bi bi-list"></i>
                </button>
            </div>
            <a href="/dashboard" class="{{ request()->is('dashboard') ? 'active' : '' }}"> 
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            @if($isAdmin)
                <a href="/users" class="{{ request()->is('users*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
            @endif
            <div class="sidebar-title text-white">Divisions</div>
                <div class="ms-2">
                    @foreach($allDepartments as $dept)
                        @php
                            $user = auth()->user();
                            $isAllowedDept = $isAdmin || $user->department_id == $dept->id;
                        @endphp
                        @if($isAllowedDept)
                            @php
                                $isShipping = str_contains(strtolower($dept->name), 'shipping');
                            @endphp
                            @if($isShipping)
                                <div class="mt-2">
                                    {{-- HEADER --}}
                                    <div class="sidebar-header text-white fw-bold d-flex justify-content-between align-items-center" style="cursor:pointer;" onclick="toggleMenu('shippingMenu{{ $dept->id }}')">
                                        <span>{{ $dept->name }}</span>
                                        <span>▼</span>
                                    </div>
                                    {{-- DROPDOWN --}}
                                    <div id="shippingMenu{{ $dept->id }}" class="sidebar-dropdown mt-1" style="display: {{ request()->is('shipping/*') ? 'block' : 'none' }};">
                                        <a href="{{ url('/shipping/vessels') }}" class="sidebar-link {{ request()->is('shipping/vessels') ? 'active-menu' : '' }}">
                                            <i class="bi bi-ship sidebar-icon"></i>
                                            <span>Vessels</span>
                                        </a>
                                        <a href="{{ url('/shipping/tech-defects') }}" class="sidebar-link {{ request()->is('shipping/tech-defects') ? 'active-menu' : '' }}">
                                            <i class="bi bi-tools sidebar-icon"></i>
                                            <span>Tech & Defect Reports</span>
                                        </a>
                                        <a href="{{ route('vessel-certificates.index') }}" class="sidebar-link {{ request()->is('vessel-certificates*') ? 'active-menu' : '' }}">
                                            <i class="bi bi-file-earmark-text sidebar-icon"></i>
                                            <span>Vessel Certificates</span>
                                        </a>
                                        <a href="{{ url('/shipping/dry-docking') }}" class="sidebar-link {{ request()->is('shipping/dry-docking') ? 'active-menu' : '' }}">
                                            <i class="bi bi-tools sidebar-icon"></i>
                                            <span>Dry Docking Monitoring</span>
                                        </a>
                                    </div>
                                </div>
                             @else
                                <a href="{{ url('/departments/'.$dept->id) }}">
                                    <i class="bi bi-building"></i>
                                    <span>{{ $dept->name }}</span>
                                </a>
                            @endif
                        @endif
                    @endforeach
                </div>
            </div>
            <!-- MAIN CONTENT -->
            <div class="main-content">
                <div class="topbar d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Welcome, {{ Auth::user()->name }} 👋</h5>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById("toggleSidebar").onclick = function(){
            let sidebar = document.getElementById("sidebar");
            sidebar.classList.toggle("collapsed");
        }
        document.querySelectorAll('.sidebar-dropdown').forEach(menu => {
        menu.style.display = 'block';
        });
    </script>
    <script>
        function toggleMenu(id) {
            let menu = document.getElementById(id);
            if (menu.style.display === "none") {
                menu.style.display = "block";
            } else {
                menu.style.display = "none";
            }
        }
    </script>
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>