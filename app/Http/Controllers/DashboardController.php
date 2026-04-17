<?php

namespace App\Http\Controllers;

use App\Models\TechDefect;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use App\Models\VoyageLog;
use App\Models\VoyageLogHeader;
use App\Models\Department;

class DashboardController extends Controller
{
    public function index()
    {
        $departments = Department::all(); // dynamic gikan DB
        return view('dashboard.main', compact('departments'));
    }

    public function divisionDashboard($division)
{
    $division = strtolower(trim($division));

    // 🔥 mapping gikan DB → system key
    $map = [
        'villa shipping' => 'vsli',
        'yatira construction' => 'yatira',
        'mining' => 'mining',
        'it' => 'it',
        'hr' => 'hr',
        'r & d' => 'rd',
        'r&d' => 'rd',
    ];

    $key = $map[$division] ?? null;

    if (!$key) {
        abort(404);
    }

    // 🔥 VSli (Villa Shipping) → FULL DASHBOARD
    if ($key === 'vsli') {
        $metrics = $this->buildShippingMetrics();
        return view('dashboard.vsli', $metrics);
    }

    // 🔥 Other divisions (pwede nimo fill later)
    if ($key === 'yatira') {
        return view('dashboard.yatira', [
        'division' => $division
        ]);
    }

   if ($key === 'mining') {
    return view('dashboard.mining', [
        'division' => $division
    ]);
}

if ($key === 'it') {
    return view('dashboard.it', [
        'division' => $division
    ]);
}

if ($key === 'hr') {
    return view('dashboard.hr', [
        'division' => $division
    ]);
}

if ($key === 'rd') {
    return view('dashboard.rd', [
        'division' => $division
    ]);
}

    abort(404);
}

    protected function buildShippingMetrics(): array
    {
        return [
            'totalVessels' => Vessel::count(),
            'totalLogs' => VoyageLogHeader::count(),
            'anchored' => VoyageLog::where('voyage_status', 'anchored')->count(),
            'sailing' => VoyageLog::where('voyage_status', 'sailing')->count(),
            'totalCrew' => VoyageLog::sum('crew_on_board'),
            'totalDefects' => TechDefect::count(),
            'expiredCertificates' => VesselCertificate::expired()->count(),
            'expiringCertificates' => VesselCertificate::expiringWithinDays()->count(),
        ];
    }
}
