<?php

namespace App\Http\Controllers;

use App\Models\TechDefect;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use App\Models\VoyageLog;
use App\Models\VoyageLogHeader;

class DashboardController extends Controller
{
    public function index()
    {
        $divisions = ['vsli', 'yatira', 'mining', 'it', 'hr', 'rd'];

        return view('dashboard.main', compact('divisions'));
    }

    public function divisionDashboard($division)
    {
        $division = strtolower($division);
        $metrics = $this->buildShippingMetrics();

        if ($division === 'vsli') {
            return view('dashboard.vsli', $metrics);
        }

        return view('dashboard.coming-soon', [
            'division' => $division,
        ]);
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
