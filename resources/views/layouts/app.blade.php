<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Villa Group')</title>
    <link rel="icon" href="{{ asset('logo.jpg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --villa-navy: #24477f;
            --villa-navy-dark: #17345f;
            --villa-page: #f7f5f5;
            --villa-text: #172033;
            --villa-muted: #6f7b8e;
            --sidebar-width: 278px;
            --sidebar-collapsed-width: 86px;
        }

        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            min-width: 280px;
            overflow-x: hidden;
            background: #edf1f7;
            color: var(--villa-text);
            font-family: Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        button, input, select, textarea { font: inherit; }
        .app-layout { display: flex; min-height: 100vh; min-height: 100svh; }

        .app-sidebar {
            position: fixed;
            z-index: 1040;
            inset: 0 auto 0 0;
            display: flex;
            width: var(--sidebar-width);
            flex-direction: column;
            padding: 20px 14px;
            background: linear-gradient(180deg, var(--villa-navy) 0%, #294f89 100%);
            color: #fff;
            border-right: 1px solid rgba(255,255,255,.08);
            box-shadow: 10px 0 35px rgba(14,38,75,.1);
            transition: width .25s ease, transform .25s ease;
        }

        .sidebar-heading { position: relative; min-height: 61px; }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 52px;
            padding: 0 12px 18px;
            color: #fff;
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: .04em;
            overflow: hidden;
            white-space: nowrap;
        }

        .sidebar-brand img { flex: 0 0 auto; transition: width .25s ease, height .25s ease; }

        .sidebar-brand-mark {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border: 1px solid rgba(255,255,255,.35);
            border-radius: 9px;
            background: rgba(255,255,255,.1);
            font-size: .78rem;
        }

        .app-nav { display: grid; gap: 9px; }
        .app-nav-link {
            position: relative;
            display: flex;
            min-height: 55px;
            align-items: center;
            gap: 14px;
            padding: 12px 17px;
            border-radius: 18px;
            color: rgba(255,255,255,.86);
            font-size: .96rem;
            font-weight: 700;
            text-decoration: none;
            transition: background .18s ease, color .18s ease, transform .18s ease;
        }

        .app-nav-link i, .app-nav-link svg { width: 23px; height: 23px; font-size: 1.28rem; text-align: center; flex: 0 0 23px; }
        .app-nav-link:hover { background: rgba(255,255,255,.12); color: #fff; transform: translateX(2px); }
        .app-nav-link.active { background: #fff; color: #111827; box-shadow: 0 10px 24px rgba(13,32,65,.18); }
        .app-nav-link.active::before {
            position: absolute;
            left: -14px;
            width: 4px;
            height: 25px;
            border-radius: 0 5px 5px 0;
            background: #d9b556;
            content: "";
        }
        .app-nav-divider { height: 1px; margin: 8px 5px; background: rgba(255,255,255,.25); }

        .sidebar-collapse-button {
            position: absolute;
            z-index: 2;
            top: 22px;
            right: -14px;
            display: grid;
            width: 30px;
            height: 30px;
            place-items: center;
            padding: 0;
            border: 1px solid #dce4ef;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 5px 14px rgba(11,32,64,.2);
            color: #24477f;
            cursor: pointer;
            transition: transform .25s ease, background .18s ease;
        }
        .sidebar-collapse-button:hover { background: #eef4fb; }

        .sidebar-footer { margin-top: auto; padding: 12px; color: rgba(255,255,255,.64); font-size: .75rem; }
        .app-main { width: calc(100% - var(--sidebar-width)); min-width: 0; margin-left: var(--sidebar-width); }
        .app-main { transition: width .25s ease, margin-left .25s ease; }

        .app-layout.sidebar-collapsed .app-sidebar { width: var(--sidebar-collapsed-width); padding-inline: 11px; }
        .app-layout.sidebar-collapsed .app-main { width: calc(100% - var(--sidebar-collapsed-width)); margin-left: var(--sidebar-collapsed-width); }
        .app-layout.sidebar-collapsed .sidebar-brand { justify-content: center; padding: 4px !important; }
        .app-layout.sidebar-collapsed .sidebar-brand > span:not(.sidebar-brand-mark),
        .app-layout.sidebar-collapsed .app-nav-link span,
        .app-layout.sidebar-collapsed .sidebar-footer { display: none; }
        .app-layout.sidebar-collapsed .sidebar-brand img { width: 48px !important; height: 40px !important; }
        .app-layout.sidebar-collapsed .app-nav-link { justify-content: center; gap: 0; padding-inline: 10px; }
        .app-layout.sidebar-collapsed .app-nav-link i,
        .app-layout.sidebar-collapsed .app-nav-link svg { width: 23px; }
        .app-layout.sidebar-collapsed .sidebar-collapse-button { transform: rotate(180deg); }
        .app-layout.sidebar-collapsed .app-nav-divider { margin-inline: 7px; }

        .app-topbar {
            position: sticky;
            z-index: 1020;
            top: 0;
            display: flex;
            min-height: 74px;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 12px 27px;
            border-bottom: 1px solid #edf0f4;
            background: rgba(255,255,255,.96);
            backdrop-filter: blur(10px);
        }

        .welcome-text { margin: 0; font-size: 1rem; font-weight: 750; }
        .topbar-actions { display: flex; align-items: center; gap: 8px; }
        .icon-action {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border: 0;
            border-radius: 12px;
            background: transparent;
            color: #495261;
            font-size: 1.38rem;
            text-decoration: none;
        }
        .icon-action:hover { background: #edf2f8; color: var(--villa-navy); }
        .icon-action svg { width: 22px; height: 22px; }
        .notification-action { position: relative; }
        .notification-count { position: absolute; top: 1px; right: 0; min-width: 18px; height: 18px; padding: 0 5px; border: 2px solid #fff; border-radius: 999px; background: #e11d48; color: #fff; font-size: 10px; font-weight: 900; line-height: 14px; text-align: center; }
        .profile-action { border: 1px solid #dce2ea; border-radius: 50%; background: #fff; }
        .profile-action img { width: 100%; height: 100%; border-radius: inherit; object-fit: cover; }
        .profile-menu-wrap { position: relative; }
        .profile-action[aria-expanded="true"] { border-color: #9db3d0; background: #edf4fc; color: var(--villa-navy); box-shadow: 0 0 0 4px rgba(36,71,127,.09); }
        .profile-menu {
            position: absolute;
            z-index: 1100;
            top: calc(100% + 13px);
            right: 0;
            width: min(310px, calc(100vw - 28px));
            overflow: hidden;
            border: 1px solid #dce4ef;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 22px 55px rgba(15, 34, 62, .2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-7px) scale(.98);
            transform-origin: top right;
            transition: opacity .16s ease, transform .16s ease, visibility .16s ease;
        }
        .profile-menu::before {
            position: absolute;
            top: -7px;
            right: 16px;
            width: 14px;
            height: 14px;
            border-top: 1px solid #dce4ef;
            border-left: 1px solid #dce4ef;
            background: #fff;
            content: "";
            transform: rotate(45deg);
        }
        .profile-menu.is-open { opacity: 1; visibility: visible; transform: translateY(0) scale(1); }
        .profile-menu-header { display: flex; align-items: center; gap: 12px; padding: 17px; border-bottom: 1px solid #edf1f5; background: linear-gradient(145deg,#fff,#f7f9fc); }
        .profile-avatar { display: grid; width: 43px; height: 43px; flex: 0 0 43px; place-items: center; border-radius: 14px; background: #e7eff9; color: var(--villa-navy); }
        .profile-avatar svg { width: 22px; height: 22px; }
        .profile-menu-name { overflow: hidden; margin: 0; color: #172033; font-size: .9rem; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .profile-menu-role { margin: 2px 0 0; color: #7a8798; font-size: .7rem; font-weight: 700; text-transform: capitalize; }
        .profile-menu-list { display: grid; gap: 5px; padding: 10px; }
        .profile-menu-link {
            display: flex;
            width: 100%;
            min-height: 45px;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            border: 0;
            border-radius: 12px;
            background: transparent;
            color: #334155;
            font-size: .82rem;
            font-weight: 750;
            text-align: left;
            text-decoration: none;
            transition: background .15s ease, color .15s ease, transform .15s ease;
        }
        .profile-menu-link svg { width: 19px; height: 19px; flex: 0 0 19px; color: #61738d; }
        .profile-menu-link:hover, .profile-menu-link:focus-visible { background: #edf4fc; color: var(--villa-navy); outline: none; transform: translateX(2px); }
        .profile-menu-link.danger { color: #b42336; }
        .profile-menu-link.danger svg { color: #d04456; }
        .profile-menu-link.danger:hover, .profile-menu-link.danger:focus-visible { background: #fff0f2; color: #991b2f; }
        .profile-menu-form { margin: 0; border-top: 1px solid #edf1f5; padding-top: 5px; }
        .mobile-menu-button { display: none; }

        .app-content { min-width: 0; max-width: 100%; min-height: calc(100vh - 74px); min-height: calc(100svh - 74px); overflow-x: clip; background: var(--villa-page); }
        .app-content > * { min-width: 0; max-width: 100%; }
        .sidebar-backdrop { display: none; }

        /* Final UI guard for the Executive Viewer. The server middleware is
           still the authoritative protection for every mutation request. */
        .executive-read-only .app-content form[method="POST" i],
        .executive-read-only .app-content a[href$="/create"],
        .executive-read-only .app-content a[href*="/edit"],
        .executive-read-only .app-content a[href*="/renew"],
        .executive-read-only .app-content a[href*="/repair-closeout"],
        .executive-read-only .app-content button[data-bs-target*="add" i],
        .executive-read-only .app-content button[data-bs-target*="edit" i],
        .executive-read-only .app-content button[data-bs-target*="update" i],
        .executive-read-only .app-content button[data-bs-target*="end" i],
        .executive-read-only .app-content button[data-bs-target*="complete" i],
        .executive-read-only .app-content button[data-bs-target*="repair" i],
        .executive-read-only .app-content button[data-bs-target*="support" i],
        .executive-read-only .app-content button[data-bs-target*="evidence" i],
        .executive-read-only .app-content #complete-voyage-map-button,
        .executive-read-only .app-content #update-current-location-button,
        .executive-read-only .app-content .status-completion-map-button,
        .executive-read-only .app-content .activity-map-button { display: none !important; }

        .card { border: 0; border-radius: 14px; box-shadow: 0 3px 14px rgba(28,46,77,.07); }
        .table th { font-weight: 650; }

        @media (max-width: 1100px) {
            .app-sidebar { width: min(var(--sidebar-width), 86vw); transform: translateX(-100%); box-shadow: 18px 0 45px rgba(10,28,57,.24); }
            .app-sidebar.is-open { transform: translateX(0); }
            .app-main { width: 100%; margin-left: 0; }
            .mobile-menu-button { display: grid; }
            .app-topbar { padding: 10px 15px; }
            .sidebar-collapse-button { display: none; }
            .app-layout.sidebar-collapsed .app-sidebar { width: var(--sidebar-width); padding-inline: 14px; }
            .app-layout.sidebar-collapsed .app-main { width: 100%; margin-left: 0; }
            .app-layout.sidebar-collapsed .sidebar-brand { justify-content: flex-start; padding-inline: 12px; }
            .app-layout.sidebar-collapsed .sidebar-brand > span:not(.sidebar-brand-mark),
            .app-layout.sidebar-collapsed .app-nav-link span { display: inline; }
            .app-layout.sidebar-collapsed .sidebar-footer { display: block; }
            .app-layout.sidebar-collapsed .app-nav-link { justify-content: flex-start; gap: 14px; padding-inline: 17px; }
            .app-layout.sidebar-collapsed .app-nav-link i,
            .app-layout.sidebar-collapsed .app-nav-link svg { width: 23px; }
            .sidebar-backdrop {
                position: fixed;
                z-index: 1030;
                inset: 0;
                background: rgba(8,20,39,.48);
            }
            .sidebar-backdrop.is-visible { display: block; }
        }

        @media (max-width: 480px) {
            .welcome-text { max-width: 160px; overflow: hidden; font-size: .9rem; text-overflow: ellipsis; white-space: nowrap; }
            .app-topbar { min-height: 66px; }
            .icon-action { width: 38px; height: 38px; }
            .app-content { min-height: calc(100svh - 66px); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
        }
    </style>
    @stack('styles')
</head>
<body @class(['executive-read-only' => auth()->user()?->isExecutiveViewer()])>
@php
    $currentUser = auth()->user();
    $isOwner = $currentUser->isExecutiveViewer();
    $isStandaloneDashboard = request()->routeIs('division.dashboard', 'shipping.dashboard');
    $isShippingArea = ! $isStandaloneDashboard && request()->is('shipping/*', 'vessel-certificates*');
    $isShippingApplications = request()->routeIs('shipping.applications', 'shipping.application.placeholder');
    $isYatiraArea = ! $isStandaloneDashboard && request()->is('yatira/*');
    $isYatiraApplications = request()->routeIs('yatira.applications');
    $isJmvArea = ! $isStandaloneDashboard && request()->is('jmv/*');
    $isJmvApplications = request()->routeIs('jmv.applications');
    $companiesActive = request()->routeIs('companies')
        || request()->is('shipping/*', 'vessel-certificates*', 'yatira/*', 'jmv/*', 'hyve/*', 'users*');
    $vesselAccess = app(\App\Services\VesselAccessService::class);
    $hasVesselScope = $isShippingArea && ($vesselAccess->canAccessAllVessels($currentUser)
        || $vesselAccess->scopeAccessible(\App\Models\Vessel::query(), $currentUser)->exists());
@endphp

<div class="app-layout" id="appLayout">
    <aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
        <div class="sidebar-heading">
        @if ($isShippingArea)
            <a class="sidebar-brand bg-white rounded-4 mb-3 p-2 text-decoration-none" href="{{ route('shipping.applications') }}" style="color:#17345f">
                <img src="{{ asset('images/companies/villa-shipping-lines.webp') }}" alt="Villa Shipping Lines" width="52" height="42" style="object-fit:contain">
                <span style="font-size:.84rem;letter-spacing:0">VILLA SHIPPING LINES</span>
            </a>
        @elseif ($isYatiraArea)
            <a class="sidebar-brand bg-white rounded-4 mb-3 p-2 text-decoration-none" href="{{ route('yatira.applications') }}" style="color:#17345f">
                <img src="{{ asset('images/companies/yatira.webp') }}" alt="Yatira Construction" width="48" height="42" style="object-fit:contain;border-radius:10px">
                <span style="font-size:.78rem;letter-spacing:0">YATIRA CONSTRUCTION</span>
            </a>
        @elseif ($isJmvArea)
            <a class="sidebar-brand bg-white rounded-4 mb-3 p-2 text-decoration-none" href="{{ route('jmv.applications') }}" style="color:#17345f">
                <img src="{{ asset('images/companies/jmv.webp') }}" alt="JMV Mining &amp; Development" width="48" height="42" style="object-fit:contain;border-radius:10px">
                <span style="font-size:.76rem;letter-spacing:0">JMV MINING</span>
            </a>
        @else
            <div class="sidebar-brand">
                <span class="sidebar-brand-mark">VG</span>
                <span>VILLA GROUP</span>
            </div>
        @endif
            <button class="sidebar-collapse-button" id="sidebarCollapseButton" type="button" aria-label="Hide sidebar" aria-expanded="true" title="Hide sidebar">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>
        </div>

        <nav class="app-nav">
            @if ($isShippingApplications)
                <a class="app-nav-link active" href="{{ route('shipping.applications') }}" title="Applications">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    <span>Applications</span>
                </a>
            @elseif ($isShippingArea)
                @if($currentUser->hasPermission('shipping.vessel_management.access'))
                <a class="app-nav-link {{ request()->routeIs('vessels.*', 'voyages.*', 'voyage.*', 'voyage-logs.*') ? 'active' : '' }}" href="{{ route('vessels.index') }}" title="Vessel Management">
                    <i class="bi bi-ship" aria-hidden="true"></i>
                    <span>Vessel Management</span>
                </a>
                @endif
                <a class="app-nav-link {{ request()->routeIs('shipping.calendar*') ? 'active' : '' }}" href="{{ route('shipping.calendar') }}" title="Calendar">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    <span>Calendar</span>
                </a>
                @if($currentUser->hasPermission('shipping.technical_defects.access') && ($currentUser->isAdmin() || in_array($currentUser->role, ['manager', 'captain'], true) || $currentUser->hasPermission('tech_defects.view_assigned')))
                <a class="app-nav-link {{ request()->routeIs('tech-defects.*') ? 'active' : '' }}" href="{{ route('tech-defects.index') }}" title="Technical &amp; Defect">
                    <i class="bi bi-tools" aria-hidden="true"></i>
                    <span>Technical &amp; Defect</span>
                </a>
                @endif
                @if($currentUser->hasPermission('shipping.certificates.access'))
                <a class="app-nav-link {{ request()->routeIs('vessel-certificates.*', 'vessel.certificates.*') ? 'active' : '' }}" href="{{ route('vessel-certificates.index') }}" title="Certificates">
                    <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                    <span>Certificates</span>
                </a>
                @endif
                @if($currentUser->hasPermission('shipping.dry_docking.access') && $hasVesselScope)
                    <a class="app-nav-link {{ request()->routeIs('dry-docking.*') ? 'active' : '' }}" href="{{ route('dry-docking.index') }}" title="Dry Docking">
                        <i class="bi bi-wrench-adjustable" aria-hidden="true"></i>
                        <span>Dry Docking</span>
                    </a>
                @endif
            @elseif ($isYatiraApplications)
                <a class="app-nav-link active" href="{{ route('yatira.applications') }}" title="Applications"><i class="bi bi-grid"></i><span>Applications</span></a>
            @elseif ($isYatiraArea)
                @if($currentUser->hasPermission('yatira.suppliers.view'))
                <a class="app-nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}" title="Suppliers"><i class="bi bi-buildings"></i><span>Suppliers</span></a>
                @endif
                @if($currentUser->hasPermission('yatira.assets.view') || $currentUser->hasPermission('yatira.consumables.view'))
                <a class="app-nav-link {{ request()->routeIs('yatira.inventory.*') ? 'active' : '' }}" href="{{ route('yatira.inventory.index') }}" title="Inventory"><i class="bi bi-clipboard-data"></i><span>Inventory</span></a>
                @endif
                @if($currentUser->hasPermission('yatira.reports.view'))
                <a class="app-nav-link" href="{{ route('supplier.report') }}" title="Reports"><i class="bi bi-file-earmark-text"></i><span>Reports</span></a>
                @endif
                @if($currentUser->hasPermission('yatira.sales.view'))
                <a class="app-nav-link {{ request()->routeIs('yatira.sales.*') ? 'active' : '' }}" href="{{ route('yatira.sales.index') }}" title="Sales Monitoring"><i class="bi bi-graph-up-arrow"></i><span>Sales Monitoring</span></a>
                @endif
            @elseif ($isJmvApplications)
                <a class="app-nav-link active" href="{{ route('jmv.applications') }}" title="Applications"><i class="bi bi-grid"></i><span>Applications</span></a>
            @elseif ($isJmvArea)
                @if($currentUser->hasPermission('jmv.operations.daily_production.view'))
                <a class="app-nav-link {{ request()->routeIs('jmv.operations.production.*') ? 'active' : '' }}" href="{{ route('jmv.operations.production.index') }}" title="Operations"><i class="bi bi-graph-up-arrow"></i><span>Operations</span></a>
                @endif
                @if($currentUser->hasPermission('jmv.inventory.view'))
                <a class="app-nav-link {{ request()->routeIs('jmv.inventory.*') ? 'active' : '' }}" href="{{ route('jmv.inventory.index') }}" title="Inventory"><i class="bi bi-box-seam"></i><span>Inventory</span></a>
                @endif
                @if($currentUser->isExecutiveViewer() || $currentUser->hasPermission('jmv.inventory.movements.manage'))
                <a class="app-nav-link {{ request()->routeIs('jmv.stockin.*') ? 'active' : '' }}" href="{{ route('jmv.stockin.index') }}" title="Stock In"><i class="bi bi-box-arrow-in-down"></i><span>Stock In</span></a>
                <a class="app-nav-link {{ request()->routeIs('jmv.stockout.*') ? 'active' : '' }}" href="{{ route('jmv.stockout.index') }}" title="Stock Out"><i class="bi bi-box-arrow-up"></i><span>Stock Out</span></a>
                @endif
                @if($currentUser->isExecutiveViewer() || $currentUser->hasPermission('jmv.inventory.requests.create') || $currentUser->hasPermission('jmv.inventory.requests.approve'))
                <a class="app-nav-link {{ request()->routeIs('jmv.requests.*') ? 'active' : '' }}" href="{{ route('jmv.requests.index') }}" title="Stock Requests"><i class="bi bi-clipboard-check"></i><span>Stock Requests</span></a>
                @endif
            @else
                @if ($currentUser->canViewExecutiveDashboards())
                    <a class="app-nav-link {{ request()->routeIs('dashboard', 'division.dashboard', 'shipping.dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" title="Dashboard">
                        <i class="bi bi-grid-1x2" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                @endif

                <a class="app-nav-link {{ $companiesActive ? 'active' : '' }}" href="{{ route('companies') }}" title="Companies">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    <span>Companies</span>
                </a>
            @endif
        </nav>

        <div class="sidebar-footer">Villa Group Operations System</div>
    </aside>

    <button class="sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="Close navigation"></button>

    <div class="app-main">
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="icon-action mobile-menu-button" id="mobileMenuButton" type="button" aria-label="Open navigation" aria-expanded="false">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <p class="welcome-text">Welcome, {{ $currentUser->name }}!</p>
            </div>

            <div class="topbar-actions">
                @if($currentUser->isExecutiveViewer())
                    <span class="d-none d-md-inline-flex align-items-center gap-1 rounded-pill border border-primary-subtle bg-primary-subtle px-3 py-2 small fw-bold text-primary-emphasis"><i class="bi bi-eye"></i> Read-only</span>
                @endif
                <a class="icon-action" href="{{ $currentUser->isAdmin() ? route('dashboard') : route('companies') }}" aria-label="Home">
                    <i class="bi bi-house" aria-hidden="true"></i>
                </a>
                <a class="icon-action notification-action" href="{{ route('notifications.index') }}" aria-label="Notifications">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    @php($unreadNotificationCount = \Illuminate\Support\Facades\Schema::hasTable('notifications') ? $currentUser->unreadNotifications()->count() : 0)
                    @if($unreadNotificationCount)<span class="notification-count">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>@endif
                </a>
                <div class="profile-menu-wrap" id="profileMenuWrap">
                    <button class="icon-action profile-action" id="profileMenuButton" type="button" aria-label="Open profile menu" aria-haspopup="menu" aria-controls="profileMenu" aria-expanded="false">
                        @if($currentUser->avatar_path)
                            <img src="{{ route('profile.avatar', ['v' => optional($currentUser->updated_at)->timestamp]) }}" alt="">
                        @else
                            <i class="bi bi-person-fill" aria-hidden="true"></i>
                        @endif
                    </button>
                    <div class="profile-menu" id="profileMenu" role="menu" aria-hidden="true">
                        <div class="profile-menu-header">
                            <span class="profile-avatar" style="overflow:hidden">
                                @if($currentUser->avatar_path)
                                    <img src="{{ route('profile.avatar', ['v' => optional($currentUser->updated_at)->timestamp]) }}" alt="" style="width:100%;height:100%;object-fit:cover">
                                @else
                                    <i class="bi bi-person-fill" aria-hidden="true"></i>
                                @endif
                            </span>
                            <div style="min-width:0">
                                <p class="profile-menu-name">{{ trim($currentUser->name.' '.$currentUser->lastname) }}</p>
                                <p class="profile-menu-role">{{ $isOwner ? 'Owner' : ($currentUser->isAdmin() ? 'Administrator' : $currentUser->role) }}</p>
                            </div>
                        </div>
                        <div class="profile-menu-list">
                            <a class="profile-menu-link" href="{{ route('profile') }}" role="menuitem">
                                <i class="bi bi-person-fill" aria-hidden="true"></i><span>Profile Management</span>
                            </a>
                            <a class="profile-menu-link" href="{{ route('password.change') }}" role="menuitem">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span>Password Management</span>
                            </a>
                            <form class="profile-menu-form" method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="profile-menu-link danger" type="submit" role="menuitem">
                                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Log Out</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="app-content">@yield('content')</main>
    </div>
</div>

<script>
    (() => {
        const layout = document.getElementById('appLayout');
        const sidebar = document.getElementById('appSidebar');
        const menuButton = document.getElementById('mobileMenuButton');
        const backdrop = document.getElementById('sidebarBackdrop');
        const collapseButton = document.getElementById('sidebarCollapseButton');

        const applyCollapsedState = (collapsed) => {
            layout.classList.toggle('sidebar-collapsed', collapsed);
            collapseButton.setAttribute('aria-expanded', String(!collapsed));
            collapseButton.setAttribute('aria-label', collapsed ? 'Show sidebar' : 'Hide sidebar');
            collapseButton.setAttribute('title', collapsed ? 'Show sidebar' : 'Hide sidebar');
        };

        try {
            applyCollapsedState(localStorage.getItem('villa-sidebar-collapsed') === 'true');
        } catch (error) {
            applyCollapsedState(false);
        }

        const setOpen = (open) => {
            sidebar.classList.toggle('is-open', open);
            backdrop.classList.toggle('is-visible', open);
            menuButton.setAttribute('aria-expanded', String(open));
            document.body.style.overflow = open ? 'hidden' : '';
        };

        menuButton.addEventListener('click', () => setOpen(!sidebar.classList.contains('is-open')));
        backdrop.addEventListener('click', () => setOpen(false));
        collapseButton.addEventListener('click', () => {
            const collapsed = !layout.classList.contains('sidebar-collapsed');
            applyCollapsedState(collapsed);

            try {
                localStorage.setItem('villa-sidebar-collapsed', String(collapsed));
            } catch (error) {
                // The sidebar still works when browser storage is unavailable.
            }
        });
        document.addEventListener('keydown', (event) => event.key === 'Escape' && setOpen(false));
    })();

    (() => {
        const wrap = document.getElementById('profileMenuWrap');
        const button = document.getElementById('profileMenuButton');
        const menu = document.getElementById('profileMenu');

        const setProfileMenuOpen = (open) => {
            menu.classList.toggle('is-open', open);
            menu.setAttribute('aria-hidden', String(!open));
            button.setAttribute('aria-expanded', String(open));
            button.setAttribute('aria-label', open ? 'Close profile menu' : 'Open profile menu');
        };

        button.addEventListener('click', () => setProfileMenuOpen(!menu.classList.contains('is-open')));
        document.addEventListener('click', (event) => { if (!wrap.contains(event.target)) setProfileMenuOpen(false); });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menu.classList.contains('is-open')) {
                setProfileMenuOpen(false);
                button.focus();
            }
        });
    })();
</script>
@stack('scripts')
</body>
</html>
