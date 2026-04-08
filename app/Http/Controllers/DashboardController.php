<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use App\Models\VoyageLog;
use App\Models\TechDefect;
use App\Models\VesselCertificate;

class DashboardController extends Controller
{
    public function index()
    {
        $totalVessels = Vessel::count();
        $totalLogs = \App\Models\VoyageLogHeader::count();
        $anchored = VoyageLog::where('voyage_status', 'anchored')->count();
        $sailing = VoyageLog::where('voyage_status', 'sailing')->count();
        $totalCrew = VoyageLog::sum('crew_on_board');
        $totalDefects = TechDefect::count();
        $expiredCertificates = VesselCertificate::where('expiry_date','<',now())->count();
        $expiringCertificates = VesselCertificate::whereBetween('expiry_date',[now(), now()->addDays(30)])->count();
        return view('dashboard', compact(
            'totalVessels',
            'totalLogs',
            'anchored',
            'sailing',
            'totalCrew',
            'totalDefects',
            'expiredCertificates',
            'expiringCertificates'
        ));
    }
}
