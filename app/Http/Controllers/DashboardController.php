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
use Illuminate\Support\Facades\DB;

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
        $activityDurationSql = 'SUM(TIMESTAMPDIFF(MINUTE, start_date_time, COALESCE(end_date_time, NOW())))';
        $locationNameSql = "COALESCE(NULLIF(TRIM(port_location), ''), 'Unassigned')";
        $currentMonthStart = now()->copy()->startOfMonth();
        $currentMonthEnd = now()->copy()->endOfMonth();
        $currentMonthLabel = $currentMonthStart->format('F Y');

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

        $vesselOperatingHours = VoyageActivity::with('vessel')
            ->selectRaw("vessel_id, {$activityDurationSql} as total_minutes, COUNT(*) as total_activities")
            ->whereNotNull('start_date_time')
            ->groupBy('vessel_id')
            ->orderByDesc('total_minutes')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $minutes = (float) ($row->total_minutes ?? 0);

                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'hours' => round($minutes / 60, 2),
                    'activities' => (int) ($row->total_activities ?? 0),
                ];
            });

        $locationHours = VoyageActivity::query()
            ->selectRaw("{$locationNameSql} as location_name, {$activityDurationSql} as total_minutes, COUNT(*) as total_activities")
            ->whereNotNull('start_date_time')
            ->groupBy(DB::raw($locationNameSql))
            ->orderByDesc('total_minutes')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $minutes = (float) ($row->total_minutes ?? 0);

                return [
                    'location_name' => $row->location_name ?? 'Unassigned',
                    'hours' => round($minutes / 60, 2),
                    'activities' => (int) ($row->total_activities ?? 0),
                ];
            });

        $latestFuelByVessel = FuelRobMonitoring::query()
            ->select('vessel_id', 'remaining_fuel')
            ->whereIn('fuel_id', function ($query) {
                $query->from('fuel_rob_monitorings')
                    ->selectRaw('MAX(fuel_id)')
                    ->groupBy('vessel_id');
            })
            ->get()
            ->keyBy('vessel_id');

        $fuelByVessel = FuelRobMonitoring::with('vessel')
            ->selectRaw('vessel_id, SUM(total_consumed) as total_consumed, SUM(received_fuel) as total_received, COUNT(*) as total_entries')
            ->groupBy('vessel_id')
            ->orderByDesc('total_consumed')
            ->limit(8)
            ->get()
            ->map(function ($row) use ($latestFuelByVessel) {
                $latestRemaining = $latestFuelByVessel->get($row->vessel_id);

                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'total_consumed' => round((float) ($row->total_consumed ?? 0), 2),
                    'total_received' => round((float) ($row->total_received ?? 0), 2),
                    'remaining_fuel' => round((float) ($latestRemaining?->remaining_fuel ?? 0), 2),
                    'entries' => (int) ($row->total_entries ?? 0),
                ];
            });

        $voyageLogsByVessel = VoyageLogHeader::with('vessel')
            ->selectRaw('vessel_id, COUNT(*) as total_voyages, SUM(COALESCE(total_hours_voyage, 0)) as total_voyage_hours, AVG(NULLIF(total_hours_voyage, 0)) as average_voyage_hours')
            ->groupBy('vessel_id')
            ->orderByDesc('total_voyages')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'total_voyages' => (int) ($row->total_voyages ?? 0),
                    'total_voyage_hours' => round((float) ($row->total_voyage_hours ?? 0), 2),
                    'average_voyage_hours' => round((float) ($row->average_voyage_hours ?? 0), 2),
                ];
            });

        $portStayByVessel = VoyageLogHeader::with('vessel')
            ->selectRaw("vessel_id, {$locationNameSql} as location_name, COUNT(*) as total_voyages, SUM(COALESCE(total_hours_voyage, 0)) as total_voyage_hours")
            ->groupBy('vessel_id', DB::raw($locationNameSql))
            ->orderByDesc('total_voyage_hours')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'location_name' => $row->location_name ?? 'Unassigned',
                    'total_voyages' => (int) ($row->total_voyages ?? 0),
                    'total_voyage_hours' => round((float) ($row->total_voyage_hours ?? 0), 2),
                ];
            });

        $activityHoursByVessel = VoyageActivity::with('vessel')
            ->selectRaw('vessel_id, COUNT(*) as total_activities, SUM(COALESCE(total_hours, 0)) as total_activity_hours, AVG(NULLIF(total_hours, 0)) as average_activity_hours')
            ->groupBy('vessel_id')
            ->orderByDesc('total_activity_hours')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'total_activities' => (int) ($row->total_activities ?? 0),
                    'total_activity_hours' => round((float) ($row->total_activity_hours ?? 0), 2),
                    'average_activity_hours' => round((float) ($row->average_activity_hours ?? 0), 2),
                ];
            });

        $monthlyVoyageSummary = VoyageLogHeader::whereBetween('date_created', [
                $currentMonthStart->toDateString(),
                $currentMonthEnd->toDateString(),
            ])
            ->count();

        $monthlyVoyagesPerVessel = VoyageLogHeader::with('vessel')
            ->whereBetween('date_created', [
                $currentMonthStart->toDateString(),
                $currentMonthEnd->toDateString(),
            ])
            ->selectRaw('vessel_id, COUNT(*) as total_voyages, SUM(COALESCE(total_hours_voyage, 0)) as total_voyage_hours')
            ->groupBy('vessel_id')
            ->orderByDesc('total_voyages')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'total_voyages' => (int) ($row->total_voyages ?? 0),
                    'total_voyage_hours' => round((float) ($row->total_voyage_hours ?? 0), 2),
                ];
            });

        $monthlyFuelByVessel = FuelRobMonitoring::with('vessel')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->selectRaw('vessel_id, SUM(total_consumed) as total_consumed, SUM(received_fuel) as total_received, AVG(NULLIF(total_consumed, 0)) as average_consumed')
            ->groupBy('vessel_id')
            ->orderByDesc('total_consumed')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'total_consumed' => round((float) ($row->total_consumed ?? 0), 2),
                    'total_received' => round((float) ($row->total_received ?? 0), 2),
                    'average_consumed' => round((float) ($row->average_consumed ?? 0), 2),
                ];
            });

        $turnaroundPerPort = VoyageLogHeader::query()
            ->whereBetween('date_created', [
                $currentMonthStart->toDateString(),
                $currentMonthEnd->toDateString(),
            ])
            ->selectRaw("{$locationNameSql} as location_name, COUNT(*) as total_voyages, AVG(NULLIF(total_hours_voyage, 0)) as average_turnaround_hours, SUM(COALESCE(total_hours_voyage, 0)) as total_turnaround_hours")
            ->groupBy(DB::raw($locationNameSql))
            ->orderByDesc('average_turnaround_hours')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'location_name' => $row->location_name ?? 'Unassigned',
                    'total_voyages' => (int) ($row->total_voyages ?? 0),
                    'average_turnaround_hours' => round((float) ($row->average_turnaround_hours ?? 0), 2),
                    'total_turnaround_hours' => round((float) ($row->total_turnaround_hours ?? 0), 2),
                ];
            });

        $loadingDurationByVessel = VoyageActivity::with('vessel')
            ->whereBetween('start_date_time', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('activity', function ($query) {
                $query->whereRaw("LOWER(name) LIKE '%load%'")
                    ->whereRaw("LOWER(name) NOT LIKE '%unload%'");
            })
            ->selectRaw('vessel_id, COUNT(*) as total_activities, SUM(COALESCE(total_hours, 0)) as total_duration_hours, AVG(NULLIF(total_hours, 0)) as average_duration_hours')
            ->groupBy('vessel_id')
            ->orderByDesc('total_duration_hours')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'total_activities' => (int) ($row->total_activities ?? 0),
                    'total_duration_hours' => round((float) ($row->total_duration_hours ?? 0), 2),
                    'average_duration_hours' => round((float) ($row->average_duration_hours ?? 0), 2),
                ];
            });

        $unloadingDurationByVessel = VoyageActivity::with('vessel')
            ->whereBetween('start_date_time', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('activity', function ($query) {
                $query->whereRaw("LOWER(name) LIKE '%unload%'");
            })
            ->selectRaw('vessel_id, COUNT(*) as total_activities, SUM(COALESCE(total_hours, 0)) as total_duration_hours, AVG(NULLIF(total_hours, 0)) as average_duration_hours')
            ->groupBy('vessel_id')
            ->orderByDesc('total_duration_hours')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel',
                    'total_activities' => (int) ($row->total_activities ?? 0),
                    'total_duration_hours' => round((float) ($row->total_duration_hours ?? 0), 2),
                    'average_duration_hours' => round((float) ($row->average_duration_hours ?? 0), 2),
                ];
            });

        $loadingByVesselMap = $loadingDurationByVessel->keyBy('vessel_name');
        $unloadingByVesselMap = $unloadingDurationByVessel->keyBy('vessel_name');
        $loadingUnloadingLabels = $loadingDurationByVessel->pluck('vessel_name')
            ->merge($unloadingDurationByVessel->pluck('vessel_name'))
            ->unique()
            ->values();

        $loadingDurationChartData = $loadingUnloadingLabels
            ->map(fn ($vesselName) => (float) ($loadingByVesselMap->get($vesselName)['total_duration_hours'] ?? 0))
            ->values();

        $unloadingDurationChartData = $loadingUnloadingLabels
            ->map(fn ($vesselName) => (float) ($unloadingByVesselMap->get($vesselName)['total_duration_hours'] ?? 0))
            ->values();

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
            'vesselOperatingHours' => $vesselOperatingHours,
            'locationHours' => $locationHours,
            'locationHourLabels' => $locationHours->pluck('location_name')->values(),
            'locationHourData' => $locationHours->pluck('hours')->values(),
            'fuelByVessel' => $fuelByVessel,
            'voyageLogsByVessel' => $voyageLogsByVessel,
            'portStayByVessel' => $portStayByVessel,
            'activityHoursByVessel' => $activityHoursByVessel,
            'currentMonthLabel' => $currentMonthLabel,
            'monthlyVoyageSummary' => $monthlyVoyageSummary,
            'monthlyVoyagesPerVessel' => $monthlyVoyagesPerVessel,
            'monthlyVoyageVesselLabels' => $monthlyVoyagesPerVessel->pluck('vessel_name')->values(),
            'monthlyVoyageVesselData' => $monthlyVoyagesPerVessel->pluck('total_voyages')->values(),
            'monthlyFuelByVessel' => $monthlyFuelByVessel,
            'monthlyFuelVesselLabels' => $monthlyFuelByVessel->pluck('vessel_name')->values(),
            'monthlyFuelVesselData' => $monthlyFuelByVessel->pluck('total_consumed')->values(),
            'turnaroundPerPort' => $turnaroundPerPort,
            'turnaroundPortLabels' => $turnaroundPerPort->pluck('location_name')->values(),
            'turnaroundPortData' => $turnaroundPerPort->pluck('average_turnaround_hours')->values(),
            'loadingDurationByVessel' => $loadingDurationByVessel,
            'unloadingDurationByVessel' => $unloadingDurationByVessel,
            'loadingUnloadingLabels' => $loadingUnloadingLabels,
            'loadingDurationChartData' => $loadingDurationChartData,
            'unloadingDurationChartData' => $unloadingDurationChartData,
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
