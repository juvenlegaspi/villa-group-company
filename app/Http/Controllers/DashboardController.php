<?php

namespace App\Http\Controllers;

use App\Models\TechDefect;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use App\Models\VoyageLog;
use App\Models\VoyageLogHeader;
use App\Models\Department;
use App\Models\Division;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Supplier;

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
            $metrics = $this->buildSupplierMetrics();
            return view('dashboard.yatira', compact('division', 'metrics'));

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
    protected function buildSupplierMetrics(): array
    {
        $daily = \App\Models\Supplier::selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->pluck('total', 'date');

        return [
            'totalSuppliers' => \App\Models\Supplier::count(),
            'todaySuppliers' => \App\Models\Supplier::whereDate('created_at', now())->count(),
            'thisMonthSuppliers' => \App\Models\Supplier::whereMonth('created_at', now()->month)->count(),
            'topProducts' => \App\Models\Supplier::select('products')
                                ->groupBy('products')
                                ->orderByRaw('COUNT(*) DESC')
                                ->limit(5)
                                ->pluck('products'),

            // 📊 chart data
            'chartLabels' => $daily->keys(),
            'chartData' => $daily->values(),
        ];
    }
    public function exportSupplierReport()
    {
        $metrics = $this->buildSupplierMetrics();

        $suppliers = Supplier::with('user')
            ->orderBy('name', 'asc')
            ->get();

        $pdf = Pdf::loadView('reports.supplier', compact('metrics', 'suppliers'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('supplier_report.pdf');
    }
}
