<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\BookingHeader;
use App\Models\FuelRobMonitoring;
use App\Models\ItemInventoryHeader;
use App\Models\Supplier;
use App\Models\YatiraConsumable;
use App\Models\YatiraFixedAsset;
use App\Models\TechDefect;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use App\Models\VesselPositionLog;
use App\Models\VoyageActivity;
use App\Models\VoyageLogHeader;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if (! $user->canViewExecutiveDashboards()) {
            return redirect()->route('companies');
        }

        return view('dashboard.owner', [
            'divisions' => Division::orderBy('id')->get(),
        ]);
    }

    public function companies()
    {
        $user = auth()->user();

        $divisions = ($user->isExecutiveViewer() || $user->isAdmin())
            ? Division::orderBy('id')->get()
            : Division::whereKey($user->division_id)->get();

        return view('dashboard.main', compact('divisions'));
    }

    public function divisionDashboard(Request $request, $division)
    {
        abort_unless(auth()->user()->canViewExecutiveDashboards(), 403, 'Dashboard access is limited to executive viewers and administrators.');

        $division = strtolower(trim($division));

        $div = Division::whereRaw('LOWER(name) = ?', [$division])->first();

        if (! $div) {
            abort(404);
        }

        $this->authorizeDivisionAccess($div);

        switch (strtolower($div->name)) {
            case 'villa shipping lines':
                return view('dashboard.vsli', $this->buildShippingMetrics($request));

            case 'yatira':
                $metrics = $this->buildSupplierMetrics();

                return view('dashboard.yatira', [
                    'division' => $div,
                    'metrics' => $metrics,
                ]);

            case 'jmv':
                return view('dashboard.jmv', [
                    'division' => $div,
                    'metrics' => $this->buildJmvMetrics(),
                ]);

            case 'corporate':
            case 'villa group':
                return view('dashboard.corporate', ['division' => $div]);

            case 'hyve':
                return view('dashboard.hyve', [
                    'division' => $div,
                    'metrics' => $this->buildHyveMetrics(),
                ]);

            default:
                return view('dashboard.coming-soon', ['division' => $div]);
        }
    }

    public function shippingDashboard(Request $request)
    {
        $division = Division::whereRaw('LOWER(name) = ?', ['villa shipping lines'])->firstOrFail();

        return redirect()->route('division.dashboard', ['division' => $division->name, ...$request->query()]);
    }

    public function shippingLiveFleet(string $division)
    {
        $divisionModel = Division::whereRaw('LOWER(name) = ?', [strtolower(trim($division))])->firstOrFail();
        $this->authorizeDivisionAccess($divisionModel);
        abort_unless(strcasecmp($divisionModel->name, 'Villa Shipping Lines') === 0, 404);

        return response()->json([
            'tracks' => $this->shippingVoyageTracks(),
            'updated_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    protected function buildShippingMetrics(Request $request): array
    {
        $databaseDriver = DB::connection()->getDriverName();
        [$yearExpression, $monthExpression] = match ($databaseDriver) {
            'sqlite' => ["strftime('%Y', date_created)", "strftime('%m', date_created)"],
            'pgsql' => ['EXTRACT(YEAR FROM date_created)', 'EXTRACT(MONTH FROM date_created)'],
            default => ['YEAR(date_created)', 'MONTH(date_created)'],
        };
        $dashboardRange = $this->resolveShippingDashboardRange($request);
        $rangeStart = $dashboardRange['start'];
        $rangeEnd = $dashboardRange['end'];
        $currentMonthLabel = $dashboardRange['label'];
        $applyDateRange = static function ($query, string $column) use ($rangeStart, $rangeEnd) {
            return $query
                ->when($rangeStart, fn ($builder) => $builder->whereDate($column, '>=', $rangeStart->toDateString()))
                ->when($rangeEnd, fn ($builder) => $builder->whereDate($column, '<=', $rangeEnd->toDateString()));
        };
        $applyTimestampRange = static function ($query, string $column) use ($rangeStart, $rangeEnd) {
            return $query
                ->when($rangeStart, fn ($builder) => $builder->where($column, '>=', $rangeStart))
                ->when($rangeEnd, fn ($builder) => $builder->where($column, '<=', $rangeEnd));
        };
        $normalizeLocation = fn ($value) => filled(trim((string) $value)) ? trim((string) $value) : 'Unassigned';

        $totalVoyages = $applyDateRange(VoyageLogHeader::query(), 'date_created')->count();
        $openVoyages = $applyDateRange(VoyageLogHeader::query(), 'date_created')->where(function ($query): void {
            $query->whereNull('status')->orWhereRaw("UPPER(status) != 'COMPLETED'");
        })->count();
        $completedVoyages = $applyDateRange(VoyageLogHeader::query(), 'date_created')->where('status', 'COMPLETED')->count();
        $activeVessels = Vessel::whereRaw("LOWER(COALESCE(vessel_status, '')) IN (?, ?, ?)", ['active', 'operational', 'sailing'])->count();

        $liveVoyageQuery = VoyageLogHeader::query()->where(function ($query): void {
            $query->whereNull('status')->orWhereRaw("UPPER(status) != 'COMPLETED'");
        });
        $liveOpenVoyages = (clone $liveVoyageQuery)->count();
        $liveSailingVoyages = (clone $liveVoyageQuery)
            ->whereRaw("LOWER(COALESCE(status, '')) = ?", ['sailing'])
            ->count();
        $liveAnchoredVoyages = (clone $liveVoyageQuery)
            ->whereRaw("LOWER(COALESCE(status, '')) = ?", ['anchored'])
            ->count();
        $delayedVoyages = (clone $liveVoyageQuery)
            ->whereNotNull('arrival_date')
            ->where('arrival_date', '<', now())
            ->count();

        $activeVoyageMapPoints = (clone $liveVoyageQuery)
            ->with(['vessel', 'positionLogs'])
            ->whereHas('vessel')
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->latest('voyage_id')
            ->get()
            ->unique('vessel_id')
            ->values()
            ->map(function (VoyageLogHeader $voyage): array {
                $latestPosition = $voyage->positionLogs->sortByDesc('recorded_at')->first();
                $positionUpdatedAt = $latestPosition?->recorded_at ?? $voyage->updated_at;

                return [
                    'id' => $voyage->voyage_id,
                    'vessel' => $voyage->vessel?->vessel_name ?? 'Unknown Vessel',
                    'voyage' => $voyage->voyage_no ?? $voyage->voyage_code,
                    'status' => strtoupper((string) ($voyage->status ?: 'OPEN')),
                    'location' => $voyage->current_location ?: 'Location not named',
                    'destination' => $voyage->port_destination ?: 'Destination not set',
                    'eta' => $voyage->arrival_date?->format('M d, Y h:i A'),
                    'lat' => (float) $voyage->current_latitude,
                    'lng' => (float) $voyage->current_longitude,
                    'last_update' => $positionUpdatedAt?->format('M d, Y h:i A'),
                    'is_stale' => ! $positionUpdatedAt || $positionUpdatedAt->lt(now()->subDay()),
                ];
            });
        $staleLocationCount = $activeVoyageMapPoints->where('is_stale', true)->count();

        $dashboardVoyageTracks = $this->shippingVoyageTracks();

        $expiredCertificates = VesselCertificate::effective()->expired()->count();
        $expiringCertificates = VesselCertificate::effective()->expiringWithinDays()->count();
        $validCertificates = VesselCertificate::effective()->where('expiry_date', '>', now()->copy()->addDays(30))->count();
        $effectiveCertificateCount = $expiredCertificates + $expiringCertificates + $validCertificates;
        $certificateComplianceRate = $effectiveCertificateCount > 0 ? round(($validCertificates / $effectiveCertificateCount) * 100, 1) : 100;

        $totalFuelConsumed = (float) $applyTimestampRange(FuelRobMonitoring::query(), 'created_at')->sum('total_consumed');
        $totalFuelReceived = (float) $applyTimestampRange(FuelRobMonitoring::query(), 'created_at')->sum('received_fuel');
        $averageFuelConsumed = (float) $applyTimestampRange(FuelRobMonitoring::query(), 'created_at')->avg('total_consumed');
        $fuelUpdatesToday = FuelRobMonitoring::whereDate('created_at', today())->count();
        $fuelPerCompletedVoyage = $completedVoyages > 0 ? round($totalFuelConsumed / $completedVoyages, 2) : 0;

        $recentVoyages = $applyDateRange(VoyageLogHeader::with('vessel'), 'date_created')
            ->latest('voyage_id')
            ->limit(10)
            ->get();

        $recentCargoVoyages = $applyDateRange(VoyageLogHeader::with('vessel'), 'date_created')
            ->where(function ($query) {
                $query->whereNotNull('cargo_type')
                    ->orWhereNotNull('cargo_volume');
            })
            ->latest('voyage_id')
            ->limit(10)
            ->get();

        $recentFuelMonitorings = $applyTimestampRange(FuelRobMonitoring::with(['vessel', 'voyage']), 'created_at')
            ->latest('fuel_id')
            ->limit(6)
            ->get();

        $recentActivities = $applyTimestampRange(VoyageActivity::with(['vessel', 'activity', 'detail']), 'start_date_time')
            ->latest('activity_id')
            ->limit(6)
            ->get();

        $recentDefects = $applyDateRange(TechDefect::with('vessel'), 'date_identified')
            ->latest('id')
            ->limit(5)
            ->get();

        $certificateAlerts = VesselCertificate::with('vessel')->effective()
            ->where('expiry_date', '<=', now()->copy()->addDays(30))
            ->orderBy('expiry_date')
            ->limit(5)
            ->get();

        $lowFuelVoyages = FuelRobMonitoring::with(['vessel', 'voyage'])
            ->whereNotNull('voyage_id')
            ->where('remaining_fuel', '<', 1000)
            ->whereIn('fuel_id', function ($query): void {
                $query->from('fuel_rob_monitorings')
                    ->selectRaw('MAX(fuel_id)')
                    ->whereNotNull('voyage_id')
                    ->groupBy('voyage_id');
            })
            ->orderBy('remaining_fuel')
            ->limit(5)
            ->get()
            ->map(function (FuelRobMonitoring $fuel) {
                $voyage = $fuel->voyage;

                if (! $voyage) {
                    return null;
                }

                $voyage->setRelation('vessel', $fuel->vessel);
                $voyage->setAttribute('fuel_balance', (float) $fuel->remaining_fuel);

                return $voyage;
            })
            ->filter()
            ->values();

        $monthlyVoyages = $applyDateRange(VoyageLogHeader::selectRaw("{$yearExpression} as year_num, {$monthExpression} as month_num, COUNT(*) as total"), 'date_created')
            ->whereNotNull('date_created')
            ->groupBy('year_num', 'month_num')
            ->orderBy('year_num')
            ->orderBy('month_num')
            ->get()
            ->map(fn ($row) => [
                'label' => sprintf('%02d/%d', $row->month_num, $row->year_num),
                'total' => (int) $row->total,
            ])
            ->take(-12)
            ->values();

        $defectStatusCounts = $applyDateRange(
            TechDefect::query()->selectRaw('status, COUNT(*) as total'),
            'date_identified'
        )->groupBy('status')->pluck('total', 'status')->map(fn ($total) => (int) $total)->all();
        $activeDefects = collect($defectStatusCounts)->except('Closed')->sum();
        $overdueDefects = $applyDateRange(TechDefect::query(), 'date_identified')
            ->where('status', '!=', 'Closed')
            ->whereNotNull('target_completion_date')
            ->whereDate('target_completion_date', '<', today())
            ->count();
        $liveOverdueDefects = TechDefect::query()
            ->where('status', '!=', 'Closed')
            ->whereNotNull('target_completion_date')
            ->whereDate('target_completion_date', '<', today())
            ->count();
        $closedDefects = (int) ($defectStatusCounts['Closed'] ?? 0);
        $defectClosureRate = array_sum($defectStatusCounts) > 0
            ? round(($closedDefects / array_sum($defectStatusCounts)) * 100, 1)
            : 0;
        $criticalOpenDefects = TechDefect::query()
            ->whereRaw('LOWER(severity_level) = ?', ['critical'])
            ->where('status', '!=', 'Closed')
            ->count();

        $lastDataUpdatedAt = collect([
            VoyageLogHeader::max('updated_at'),
            FuelRobMonitoring::max('updated_at'),
            TechDefect::max('updated_at'),
            VesselCertificate::max('updated_at'),
            VesselPositionLog::max('recorded_at'),
        ])->filter()->map(fn ($value) => Carbon::parse($value))->max();

        $fuelConsumptionByEngine = [
            'Main Engine' => (float) $applyTimestampRange(FuelRobMonitoring::query(), 'created_at')->sum('main_engine'),
            'Auxiliary Engine' => (float) $applyTimestampRange(FuelRobMonitoring::query(), 'created_at')->sum('auxiliary_engine'),
            'Boiler' => (float) $applyTimestampRange(FuelRobMonitoring::query(), 'created_at')->sum('boiler'),
            'Others' => (float) $applyTimestampRange(FuelRobMonitoring::query(), 'created_at')->sum('others'),
        ];

        $topFuelVessels = $applyTimestampRange(FuelRobMonitoring::with('vessel'), 'created_at')
            ->selectRaw('vessel_id, SUM(total_consumed) as total_consumed')
            ->groupBy('vessel_id')
            ->orderByDesc('total_consumed')
            ->limit(5)
            ->get();

        $monthlyVoyageSummary = $totalVoyages;

        $monthlyVoyagesPerVessel = $applyDateRange(VoyageLogHeader::with('vessel'), 'date_created')
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

        $monthlyFuelByVessel = $applyTimestampRange(FuelRobMonitoring::with('vessel'), 'created_at')
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

        $turnaroundPerPort = $applyDateRange(VoyageLogHeader::query(), 'date_created')
            ->selectRaw('port_location, COUNT(*) as total_voyages, AVG(NULLIF(total_hours_voyage, 0)) as average_turnaround_hours, SUM(COALESCE(total_hours_voyage, 0)) as total_turnaround_hours')
            ->groupBy('port_location')
            ->orderByDesc('average_turnaround_hours')
            ->get()
            ->groupBy(fn ($row) => $normalizeLocation($row->port_location))
            ->map(function ($rows, $locationName) {
                $totalVoyages = (int) $rows->sum('total_voyages');
                $totalHours = (float) $rows->sum('total_turnaround_hours');

                return [
                    'location_name' => $locationName,
                    'total_voyages' => $totalVoyages,
                    'average_turnaround_hours' => $totalVoyages > 0 ? round($totalHours / $totalVoyages, 2) : 0,
                    'total_turnaround_hours' => round($totalHours, 2),
                ];
            })
            ->sortByDesc('average_turnaround_hours')
            ->take(8)
            ->values();

        $loadingDurationByVessel = $applyTimestampRange(VoyageActivity::with('vessel'), 'start_date_time')
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

        $unloadingDurationByVessel = $applyTimestampRange(VoyageActivity::with('vessel'), 'start_date_time')
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
            'liveOpenVoyages' => $liveOpenVoyages,
            'liveSailingVoyages' => $liveSailingVoyages,
            'liveAnchoredVoyages' => $liveAnchoredVoyages,
            'delayedVoyages' => $delayedVoyages,
            'activeVoyageMapPoints' => $activeVoyageMapPoints,
            'dashboardVoyageTracks' => $dashboardVoyageTracks,
            'staleLocationCount' => $staleLocationCount,
            'lastDataUpdatedAt' => $lastDataUpdatedAt,
            'totalVoyages' => $totalVoyages,
            'totalLogs' => $totalVoyages,
            'openVoyages' => $openVoyages,
            'completedVoyages' => $completedVoyages,
            'anchored' => $applyDateRange(VoyageLogHeader::query(), 'date_created')->whereRaw("LOWER(COALESCE(status, '')) = ?", ['anchored'])->count(),
            'sailing' => $applyDateRange(VoyageLogHeader::query(), 'date_created')->whereRaw("LOWER(COALESCE(status, '')) = ?", ['sailing'])->count(),
            'totalCrew' => VoyageLogHeader::where(function ($query): void {
                $query->whereNull('status')->orWhereRaw("UPPER(status) != 'COMPLETED'");
            })->sum('crew_on_board'),
            'totalDefects' => array_sum($defectStatusCounts),
            'criticalDefects' => $applyDateRange(TechDefect::query(), 'date_identified')->whereRaw('LOWER(severity_level) = ?', ['critical'])->count(),
            'criticalOpenDefects' => $criticalOpenDefects,
            'activeDefects' => $activeDefects,
            'overdueDefects' => $overdueDefects,
            'liveOverdueDefects' => $liveOverdueDefects,
            'defectClosureRate' => $defectClosureRate,
            'expiredCertificates' => $expiredCertificates,
            'expiringCertificates' => $expiringCertificates,
            'validCertificates' => $validCertificates,
            'certificateComplianceRate' => $certificateComplianceRate,
            'totalFuelConsumed' => $totalFuelConsumed,
            'totalFuelReceived' => $totalFuelReceived,
            'averageFuelConsumed' => $averageFuelConsumed,
            'fuelUpdatesToday' => $fuelUpdatesToday,
            'fuelPerCompletedVoyage' => $fuelPerCompletedVoyage,
            'recentVoyages' => $recentVoyages,
            'recentCargoVoyages' => $recentCargoVoyages,
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
            'dashboardRange' => $dashboardRange,
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

    private function shippingVoyageTracks()
    {
        return VoyageLogHeader::query()
            ->with([
                'vessel',
                'positionLogs' => fn ($query) => $query->orderBy('id'),
                'fuelMonitorings:fuel_id,voyage_id,total_consumed',
            ])
            ->whereHas('vessel')
            ->where(function ($query): void {
                $query->where(function ($coordinates): void {
                    $coordinates->whereNotNull('origin_latitude')->whereNotNull('origin_longitude');
                })->orWhere(function ($coordinates): void {
                    $coordinates->whereNotNull('current_latitude')->whereNotNull('current_longitude');
                })->orWhere(function ($coordinates): void {
                    $coordinates->whereNotNull('destination_latitude')->whereNotNull('destination_longitude');
                })->orWhereHas('positionLogs');
            })
            ->latest('voyage_id')
            ->get()
            ->map(function (VoyageLogHeader $voyage): array {
                $latestPosition = $voyage->positionLogs->sortByDesc('id')->first();
                $positionUpdatedAt = $latestPosition?->recorded_at ?? $voyage->updated_at;

                return [
                    'id' => $voyage->voyage_id,
                    'vessel' => $voyage->vessel?->vessel_name ?? 'Unknown Vessel',
                    'voyage' => $voyage->voyage_no ?? $voyage->voyage_code,
                    'status' => strtoupper((string) ($voyage->status ?: 'OPEN')),
                    'completed' => strtoupper((string) $voyage->status) === 'COMPLETED',
                    'cargo' => trim(implode(' - ', array_filter([$voyage->cargo_type, $voyage->cargo_volume]))) ?: null,
                    'fuel_at_departure' => $voyage->fuel_rob,
                    'fuel_consumed' => $voyage->fuelMonitorings->isNotEmpty()
                        ? round((float) $voyage->fuelMonitorings->sum('total_consumed'), 2)
                        : null,
                    'eta' => $voyage->arrival_date?->format('M d, Y h:i A'),
                    'completed_at' => $voyage->date_completed?->format('M d, Y'),
                    'origin' => ['name' => $voyage->port_location, 'lat' => $voyage->origin_latitude, 'lng' => $voyage->origin_longitude],
                    'destination' => ['name' => $voyage->port_destination, 'lat' => $voyage->destination_latitude, 'lng' => $voyage->destination_longitude],
                    'current' => ['name' => $voyage->current_location, 'lat' => $voyage->current_latitude, 'lng' => $voyage->current_longitude],
                    'positions' => $voyage->positionLogs->map(fn (VesselPositionLog $position): array => [
                        'name' => $position->location_name,
                        'lat' => $position->latitude,
                        'lng' => $position->longitude,
                        'recorded_at' => $position->recorded_at?->format('M d, Y h:i A'),
                    ])->values(),
                    'last_update' => $positionUpdatedAt?->format('M d, Y h:i A'),
                ];
            })->values();
    }

    protected function resolveShippingDashboardRange(Request $request): array
    {
        $allowedRanges = [
            'today',
            'last_7_days',
            'last_30_days',
            'this_month',
            'last_month',
            'this_year',
            'all_time',
            'custom',
        ];
        $rangeKey = (string) $request->input('range', 'this_month');

        if (! in_array($rangeKey, $allowedRanges, true)) {
            $rangeKey = 'this_month';
        }

        $today = today();
        [$start, $end, $label] = match ($rangeKey) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay(), 'Today'],
            'last_7_days' => [$today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay(), 'Last 7 Days'],
            'last_30_days' => [$today->copy()->subDays(29)->startOfDay(), $today->copy()->endOfDay(), 'Last 30 Days'],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                $today->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
                $today->copy()->subMonthNoOverflow()->format('F Y'),
            ],
            'this_year' => [$today->copy()->startOfYear()->startOfDay(), $today->copy()->endOfYear()->endOfDay(), $today->format('Y')],
            'all_time' => [null, null, 'All Time'],
            'custom' => $this->resolveCustomShippingDashboardRange($request),
            default => [$today->copy()->startOfMonth()->startOfDay(), $today->copy()->endOfMonth()->endOfDay(), $today->format('F Y')],
        };

        return [
            'key' => $rangeKey,
            'start' => $start,
            'end' => $end,
            'from' => $start?->toDateString(),
            'to' => $end?->toDateString(),
            'label' => $label,
        ];
    }

    protected function resolveCustomShippingDashboardRange(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ], [
            'date_from.required' => 'Select the start date for the custom dashboard range.',
            'date_to.required' => 'Select the end date for the custom dashboard range.',
            'date_to.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        $start = Carbon::parse($validated['date_from'])->startOfDay();
        $end = Carbon::parse($validated['date_to'])->endOfDay();

        return [$start, $end, $start->format('M d, Y').' - '.$end->format('M d, Y')];
    }

    protected function buildSupplierMetrics(): array
    {
        $yatiraId = Division::whereRaw('LOWER(name) = ?', ['yatira'])->value('id');
        $base = Supplier::query()->where('division_id', $yatiraId);
        $daily = (clone $base)->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->pluck('total', 'date');
        $topProducts = (clone $base)->where('status', true)->pluck('products')
            ->flatMap(fn ($products) => preg_split('/[,;\r\n]+/', (string) $products) ?: [])
            ->map(fn ($product) => trim($product))->filter()->countBy()->sortDesc()->keys()->take(5)->values();
        $assets = YatiraFixedAsset::query()->where('division_id', $yatiraId);
        $consumables = YatiraConsumable::query()->where('division_id', $yatiraId);

        return [
            'totalSuppliers' => (clone $base)->count(),
            'activeSuppliers' => (clone $base)->where('status', true)->count(),
            'inactiveSuppliers' => (clone $base)->where('status', false)->count(),
            'todaySuppliers' => (clone $base)->whereDate('created_at', now())->count(),
            'thisMonthSuppliers' => (clone $base)->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'topProducts' => $topProducts,
            'totalAssets' => (clone $assets)->count(),
            'maintenanceAssets' => (clone $assets)->where('status', 'Under Maintenance')->count(),
            'disposedAssets' => (clone $assets)->where('status', 'Disposed')->count(),
            'totalConsumableItems' => (clone $consumables)->where('status', true)->count(),
            'lowStockItems' => (clone $consumables)->where('status', true)->whereColumn('stock_on_hand', '<=', 'reorder_level')->count(),
            'chartLabels' => $daily->keys(),
            'chartData' => $daily->values(),
        ];
    }

    protected function buildJmvMetrics(): array
    {
        if (! Schema::hasTable('item_inventory_header')) {
            return ['totalItems' => 0, 'stockOnHand' => 0, 'lowStock' => 0, 'outOfStock' => 0];
        }

        return [
            'totalItems' => ItemInventoryHeader::where('status', 1)->count(),
            'stockOnHand' => (int) ItemInventoryHeader::where('status', 1)->sum('stock_on_hand'),
            'lowStock' => ItemInventoryHeader::where('status', 1)
                ->whereColumn('stock_on_hand', '<=', 'minimum_quantity')->where('stock_on_hand', '>', 0)->count(),
            'outOfStock' => ItemInventoryHeader::where('status', 1)->where('stock_on_hand', '<=', 0)->count(),
        ];
    }

    protected function buildHyveMetrics(): array
    {
        if (! Schema::hasTable('booking_headers')) {
            return ['totalBookings' => 0, 'confirmedBookings' => 0, 'pendingBookings' => 0, 'totalRevenue' => 0];
        }

        return [
            'totalBookings' => BookingHeader::count(),
            'confirmedBookings' => BookingHeader::where('status', 'confirmed')->count(),
            'pendingBookings' => BookingHeader::whereIn('status', ['pending', 'pending_verification'])->count(),
            'totalRevenue' => (float) BookingHeader::where('payment_status', 'verified')->sum('total_amount'),
        ];
    }

    public function exportSupplierReport()
    {
        $this->authorizeDivisionNameAccess('yatira');
        abort_unless(auth()->user()->isAdmin() || auth()->user()->hasPermission('yatira.reports.view'), 403, 'Yatira report access is not assigned to your position.');

        $metrics = $this->buildSupplierMetrics();

        $yatiraId = Division::whereRaw('LOWER(name) = ?', ['yatira'])->value('id');
        $suppliers = Supplier::with('user')->where('division_id', $yatiraId)
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

        if ($user->canViewExecutiveDashboards()) {
            return;
        }

        $user->loadMissing('division');

        abort_unless(
            strcasecmp((string) $user->division?->name, $divisionName) === 0,
            403
        );
    }
}
