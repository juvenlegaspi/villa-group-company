<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Supplier;
use App\Models\TechDefect;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use App\Models\VoyageLog;
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
