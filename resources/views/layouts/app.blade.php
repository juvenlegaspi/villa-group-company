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
    </style>
</head>
<body>
    <div class="layout">
    <!-- SIDEBAR -->
        <div id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h4>Villa System and Company</h4>
                <button id="toggleSidebar" class="toggle-btn">
                    <i class="bi bi-list"></i>
                </button>
            </div>
            <a href="/dashboard" class="{{ request()->is('dashboard') ? 'active' : '' }}"> 
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            @if(in_array(auth()->user()->role,['admin','manager']))
                <a href="/users" class="{{ request()->is('users*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
            @endif
            @if(in_array(auth()->user()->role,['admin','manager']))
                <div class="sidebar-title text-white">Division</div>
                <div class="ms-2">
                @foreach($allDepartments as $dept)
                    @php
                        $isShipping = str_contains(strtolower($dept->name),'shipping');
                    @endphp
                    @if($isShipping)
                        <div class="mt-2">
                            <strong class="text-white">{{ $dept->name }}</strong>
                            <div class="ms-3 mt-1">
                                <a href="{{ url('/shipping/vessels') }}">
                                    <i class="bi bi-ship"></i>
                                    <span>Vessels</span>
                                </a>
                                <a href="{{ url('/shipping/tech-defects') }}">
                                    <i class="bi bi-tools"></i>
                                    <span>Tech & Defect Reports</span>
                                </a>
                                <a href="{{ route('vessel-certificates.index') }}">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <span>Vessel Certificates</span>
                                </a>
                            </div>
                        </div>
                    @else
                        <a href="{{ url('/departments/'.$dept->id) }}">
                            <i class="bi bi-building"></i>
                            <span>{{ $dept->name }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
            @endif
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
</script>
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</body>
</html>