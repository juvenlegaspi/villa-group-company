<?php

namespace App\Http\Controllers;

use App\Models\TechDefect;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use App\Models\VoyageLog;
use App\Models\VoyageLogHeader;
use App\Models\Department;
use App\Models\Division;

class DashboardController extends Controller
{
    public function index()
    {
        $divisions = Division::all(); // dynamic gikan DB
        return view('dashboard.main', compact('divisions'));
    }

    public function divisionDashboard($division)
{
    $division = strtolower(trim($division));

    // kuha gikan DB
    $div = Division::whereRaw('LOWER(name) = ?', [$division])->first();
    
    if (!$div) {
        abort(404);
    }


    // 🔥 dynamic routing based sa division name
    switch (strtolower($div->name)) {

        case 'villa shipping lines':
            $metrics = $this->buildShippingMetrics();
            return view('dashboard.vsli', $metrics);

        case 'yatira':
            return view('dashboard.yatira', ['division' => $div]);

        case 'jmv':
            return view('dashboard.jmv', ['division' => $div]);

        case 'corporate':
            return view('dashboard.corporate', ['division' => $div]);

        default:
            abort(404);
    }
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
