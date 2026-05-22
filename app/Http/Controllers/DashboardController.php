<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\FuelRobMonitoring;
use App\Models\Supplier;
use App\Models\TechDefect;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use App\Models\VoyageActivity;
use App\Models\VoyageLogHeader;
use Barryvdh\DomPDF\Facade\Pdf;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $divisions = $user->isAdmin()
            ? Division::orderBy('name')->get()
            : Division::whereKey($user->division_id)->get();

        return view('dashboard.main', compact('divisions'));
    }

    public function divisionDashboard($division)
    {
        $division = strtolower(trim($division));

        $div = Division::whereRaw('LOWER(name) = ?', [$division])->first();

        if (! $div) {
            abort(404);
        }

        $this->authorizeDivisionAccess($div);

        switch (strtolower($div->name)) {
            case 'villa shipping lines':
                $metrics = $this->buildShippingMetrics();

                return view('dashboard.vsli', $metrics);

            case 'yatira':
                $metrics = $this->buildSupplierMetrics();

                return view('dashboard.yatira', [
                    'division' => $div,
                    'metrics' => $metrics,
                ]);

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
        $totalVoyages = VoyageLogHeader::count();
        $openVoyages = VoyageLogHeader::where('status', 'OPEN')->count();
        $completedVoyages = VoyageLogHeader::where('status', 'COMPLETED')->count();
        $activeVessels = VoyageLogHeader::where('status', 'OPEN')
            ->distinct('vessel_id')
            ->count('vessel_id');

        $expiredCertificates = VesselCertificate::expired()->count();
        $expiringCertificates = VesselCertificate::expiringWithinDays()->count();

        $totalFuelConsumed = (float) FuelRobMonitoring::sum('total_consumed');
        $totalFuelReceived = (float) FuelRobMonitoring::sum('received_fuel');
        $averageFuelConsumed = (float) FuelRobMonitoring::avg('total_consumed');
        $fuelUpdatesToday = FuelRobMonitoring::whereDate('created_at', today())->count();

        $recentVoyages = VoyageLogHeader::with('vessel')
            ->latest('voyage_id')
            ->limit(6)
            ->get();

        $recentFuelMonitorings = FuelRobMonitoring::with(['vessel', 'voyage'])
            ->latest('fuel_id')
            ->limit(6)
            ->get();

        $recentActivities = VoyageActivity::with(['vessel', 'activity', 'detail'])
            ->latest('activity_id')
            ->limit(6)
            ->get();

        $recentDefects = TechDefect::with('vessel')
            ->latest('id')
            ->limit(5)
            ->get();

        $certificateAlerts = VesselCertificate::with('vessel')
            ->where('expiry_date', '<=', now()->copy()->addDays(30))
            ->orderBy('expiry_date')
            ->limit(5)
            ->get();

        $lowFuelVoyages = VoyageLogHeader::with('vessel')
            ->selectRaw("voyage_logs_header.*, CAST(REPLACE(fuel_rob, ' Liters', '') AS DECIMAL(10,2)) as fuel_balance")
            ->whereNotNull('fuel_rob')
            ->whereRaw("CAST(REPLACE(fuel_rob, ' Liters', '') AS DECIMAL(10,2)) < 1000")
            ->orderByRaw("CAST(REPLACE(fuel_rob, ' Liters', '') AS DECIMAL(10,2)) asc")
            ->limit(5)
            ->get();

        $monthlyVoyages = VoyageLogHeader::selectRaw('YEAR(date_created) as year_num, MONTH(date_created) as month_num, COUNT(*) as total')
            ->whereNotNull('date_created')
            ->groupBy('year_num', 'month_num')
            ->orderBy('year_num')
            ->orderBy('month_num')
            ->get()
            ->map(fn ($row) => [
                'label' => sprintf('%02d/%d', $row->month_num, $row->year_num),
                'total' => (int) $row->total,
            ])
            ->take(-6)
            ->values();

        $defectStatusCounts = [
            'Open' => TechDefect::where('status', 'Open')->count(),
            'Ongoing' => TechDefect::where('status', 'Ongoing')->count(),
            'Waiting 3rd Party' => TechDefect::where('status', 'Waiting 3rd Party')->count(),
            'Completed' => TechDefect::where('status', 'Completed')->count(),
        ];

        $fuelConsumptionByEngine = [
            'Main Engine' => (float) FuelRobMonitoring::sum('main_engine'),
            'Auxiliary Engine' => (float) FuelRobMonitoring::sum('auxiliary_engine'),
            'Boiler' => (float) FuelRobMonitoring::sum('boiler'),
            'Others' => (float) FuelRobMonitoring::sum('others'),
        ];

        $topFuelVessels = FuelRobMonitoring::with('vessel')
            ->selectRaw('vessel_id, SUM(total_consumed) as total_consumed')
            ->groupBy('vessel_id')
            ->orderByDesc('total_consumed')
            ->limit(5)
            ->get();

        return [
            'totalVessels' => Vessel::count(),
            'activeVessels' => $activeVessels,
            'totalVoyages' => $totalVoyages,
            'totalLogs' => $totalVoyages,
            'openVoyages' => $openVoyages,
            'completedVoyages' => $completedVoyages,
            'anchored' => $openVoyages,
            'sailing' => $completedVoyages,
            'totalCrew' => VoyageLogHeader::sum('crew_on_board'),
            'totalDefects' => TechDefect::count(),
            'criticalDefects' => TechDefect::where('severity_level', 'critical')->count(),
            'expiredCertificates' => $expiredCertificates,
            'expiringCertificates' => $expiringCertificates,
            'validCertificates' => VesselCertificate::where('expiry_date', '>', now()->copy()->addDays(30))->count(),
            'totalFuelConsumed' => $totalFuelConsumed,
            'totalFuelReceived' => $totalFuelReceived,
            'averageFuelConsumed' => $averageFuelConsumed,
            'fuelUpdatesToday' => $fuelUpdatesToday,
            'recentVoyages' => $recentVoyages,
            'recentFuelMonitorings' => $recentFuelMonitorings,
            'recentActivities' => $recentActivities,
            'recentDefects' => $recentDefects,
            'certificateAlerts' => $certificateAlerts,
            'lowFuelVoyages' => $lowFuelVoyages,
            'monthlyVoyages' => $monthlyVoyages,
            'defectStatusLabels' => array_keys($defectStatusCounts),
            'defectStatusData' => array_values($defectStatusCounts),
            'fuelEngineLabels' => array_keys($fuelConsumptionByEngine),
            'fuelEngineData' => array_values($fuelConsumptionByEngine),
            'topFuelVesselLabels' => $topFuelVessels->map(fn ($row) => $row->vessel?->vessel_name ?? 'Unknown')->values(),
            'topFuelVesselData' => $topFuelVessels->map(fn ($row) => (float) $row->total_consumed)->values(),
        ];
    }

    protected function buildSupplierMetrics(): array
    {
        $daily = Supplier::selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->pluck('total', 'date');

        return [
            'totalSuppliers' => Supplier::count(),
            'todaySuppliers' => Supplier::whereDate('created_at', now())->count(),
            'thisMonthSuppliers' => Supplier::whereMonth('created_at', now()->month)->count(),
            'topProducts' => Supplier::select('products')
                ->groupBy('products')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(5)
                ->pluck('products'),
            'chartLabels' => $daily->keys(),
            'chartData' => $daily->values(),
        ];
    }

    public function exportSupplierReport()
    {
        $this->authorizeDivisionNameAccess('yatira');

        $metrics = $this->buildSupplierMetrics();

        $suppliers = Supplier::with('user')
            ->orderBy('name', 'asc')
            ->get();

        $pdf = Pdf::loadView('reports.supplier', compact('metrics', 'suppliers'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('supplier_report.pdf');
    }

    protected function authorizeDivisionAccess(Division $division): void
    {
        $this->authorizeDivisionNameAccess($division->name);
    }

    protected function authorizeDivisionNameAccess(string $divisionName): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        $user->loadMissing('division');

        abort_unless(
            strcasecmp((string) $user->division?->name, $divisionName) === 0,
            403
        );
    }
}
