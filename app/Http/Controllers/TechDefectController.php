<?php

namespace App\Http\Controllers;

use App\Models\TechDefect;
use App\Models\ThirdPartySupport;
use App\Models\Vessel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TechDefectController extends Controller
{
    protected const STATUS_OPEN = 'Open';
    protected const STATUS_ONGOING = 'Ongoing';
    protected const STATUS_WAITING_THIRD_PARTY = 'Waiting 3rd Party';
    protected const STATUS_COMPLETED = 'Completed';

    public function dashboard()
    {
        $totalReports = TechDefect::count();
        $open = TechDefect::where('status', self::STATUS_OPEN)->count();
        $ongoing = TechDefect::where('status', self::STATUS_ONGOING)->count();
        $waiting = TechDefect::where('status', self::STATUS_WAITING_THIRD_PARTY)->count();
        $completed = TechDefect::where('status', self::STATUS_COMPLETED)->count();
        $vesselDefects = TechDefect::selectRaw('vessel_id, COUNT(*) as total')
            ->groupBy('vessel_id')
            ->with('vessel')
            ->get();
        $latestReports = TechDefect::with('vessel')->latest()->take(5)->get();
        $monthlyDefects = TechDefect::selectRaw('MONTH(date_identified) as month, COUNT(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();
        $topVessel = TechDefect::selectRaw('vessel_id, COUNT(*) as total')
            ->groupBy('vessel_id')
            ->orderByDesc('total')
            ->with('vessel')
            ->first();
        $criticalDefects = TechDefect::where('severity_level', 'critical')->count();

        return view('shipping.tech_defects.dashboard', compact(
            'totalReports',
            'open',
            'ongoing',
            'waiting',
            'completed',
            'vesselDefects',
            'latestReports',
            'monthlyDefects',
            'topVessel',
            'criticalDefects'
        ));
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = TechDefect::query()
            ->with(['vessel', 'supports'])
            ->when(! $user->isAdmin(), function ($techDefectQuery) use ($user) {
                $techDefectQuery->whereHas('vessel', function ($vesselQuery) use ($user) {
                    $vesselQuery->where('captain_id', $user->id);
                });
            });

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($techDefectQuery) use ($search) {
                $techDefectQuery->where('id', 'like', "%{$search}%")
                    ->orWhereHas('vessel', function ($vesselQuery) use ($search) {
                        $vesselQuery->where('vessel_name', 'like', "%{$search}%");
                    });
            });
        }

        $reports = $query
            ->orderByRaw("
                CASE
                    WHEN status = ? THEN 1
                    WHEN status = ? THEN 2
                    WHEN status = ? THEN 3
                    WHEN status = ? THEN 4
                    ELSE 5
                END
            ", [
                self::STATUS_OPEN,
                self::STATUS_ONGOING,
                self::STATUS_WAITING_THIRD_PARTY,
                self::STATUS_COMPLETED,
            ])
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('shipping.tech_defects.index', compact('reports'));
    }

    public function create()
    {
        $vessels = $this->getAccessibleVessels(auth()->user());

        return view('shipping.tech_defects.create', compact('vessels'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $data = $this->validateTechDefect($request);

        $this->authorizeVesselSelection($user, (int) $data['vessel_id']);

        $report = TechDefect::create([
            ...$this->normalizeTechDefectData($data),
            'status' => self::STATUS_OPEN,
        ]);

        return redirect()
            ->route('tech-defects.show', $report->id)
            ->with('success', 'Report added successfully.');
    }

    public function edit($id)
    {
        $report = TechDefect::findOrFail($id);
        $vessels = $this->getAccessibleVessels(auth()->user());

        return view('shipping.tech_defects.edit', compact('report', 'vessels'));
    }

    public function update(Request $request, $id)
    {
        $report = TechDefect::findOrFail($id);

        return match ($request->action) {
            'start' => $this->startRepair($report),
            'add_support' => $this->addThirdPartySupport($request, $report),
            'complete' => $this->completeReport($report),
            default => str_starts_with((string) $request->action, 'done_')
                ? $this->markSupportAsDone($report, (int) str_replace('done_', '', (string) $request->action))
                : $this->updateReportDetails($request, $report),
        };
    }

    public function destroy($id)
    {
        TechDefect::findOrFail($id)->delete();

        return redirect()->route('tech-defects.index')
            ->with('success', 'Report deleted successfully.');
    }

    public function show($id)
    {
        $report = TechDefect::with(['vessel', 'supports'])->findOrFail($id);
        $supports = $report->supports;
        $allSupportDone = $supports->every(fn ($support) => $support->status === 'Done');

        return view('shipping.tech_defects.show', compact(
            'report',
            'supports',
            'allSupportDone'
        ));
    }

    protected function startRepair(TechDefect $report)
    {
        $report->update(['status' => self::STATUS_ONGOING]);

        return back()->with('success', 'Repair started successfully.');
    }

    protected function addThirdPartySupport(Request $request, TechDefect $report)
    {
        $data = $request->validate([
            'reason_for_support' => 'required|string',
            'spares_required' => 'required|string|max:255',
            'tools_required' => 'required|string|max:255',
        ]);

        ThirdPartySupport::create([
            'tech_defect_id' => $report->id,
            'reason_for_support' => $data['reason_for_support'],
            'spares_required' => $data['spares_required'],
            'tools_required' => $data['tools_required'],
            'status' => 'Ongoing',
        ]);

        $report->update([
            'status' => self::STATUS_WAITING_THIRD_PARTY,
            'third_party_required' => 'Yes',
        ]);

        return back()->with('success', '3rd party support added successfully.');
    }

    protected function markSupportAsDone(TechDefect $report, int $supportId)
    {
        $support = ThirdPartySupport::where('tech_defect_id', $report->id)->findOrFail($supportId);
        $support->update(['status' => 'Done']);

        $hasOpenSupport = ThirdPartySupport::where('tech_defect_id', $report->id)
            ->where('status', '!=', 'Done')
            ->exists();

        if (! $hasOpenSupport) {
            $report->update(['status' => self::STATUS_ONGOING]);
        }

        return back()->with('success', 'Support marked as done.');
    }

    protected function completeReport(TechDefect $report)
    {
        $report->update([
            'status' => self::STATUS_COMPLETED,
            'date_completed' => now()->toDateString(),
        ]);

        return back()->with('success', 'Report completed successfully.');
    }

    protected function updateReportDetails(Request $request, TechDefect $report)
    {
        $data = $this->validateTechDefect($request);
        $this->authorizeVesselSelection(auth()->user(), (int) $data['vessel_id']);

        $report->update($this->normalizeTechDefectData($data));

        return back()->with('success', 'Report updated successfully.');
    }

    protected function validateTechDefect(Request $request): array
    {
        return $request->validate([
            'vessel_id' => 'required|exists:vessels,id',
            'date_identified' => 'required|date',
            'port_location' => 'nullable|string|max:255',
            'reported_by' => 'nullable|string|max:255',
            'system_affected' => 'nullable|string|max:255',
            'defect_description' => 'required|string',
            'initial_cause' => 'nullable|string',
            'severity_level' => 'required|string|max:255',
            'operational_impact' => 'required|string|max:255',
            'temporary_repair' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);
    }

    protected function normalizeTechDefectData(array $data): array
    {
        foreach (['port_location', 'reported_by', 'system_affected', 'defect_description', 'initial_cause', 'remarks'] as $field) {
            if (array_key_exists($field, $data) && filled($data[$field])) {
                $data[$field] = strtoupper($data[$field]);
            }
        }

        return $data;
    }

    protected function getAccessibleVessels($user): Collection
    {
        if ($user->isAdmin()) {
            return Vessel::orderBy('vessel_name')->get();
        }

        if ($user->role === 'manager' && $user->department_id == 1) {
            return Vessel::query()
                ->when(
                    $this->hasColumn('vessels', 'department_id'),
                    fn ($query) => $query->where('department_id', $user->department_id)
                )
                ->orderBy('vessel_name')
                ->get();
        }

        if ($user->role === 'captain' && $user->department_id == 1) {
            return Vessel::where('captain_id', $user->id)
                ->orderBy('vessel_name')
                ->get();
        }

        return collect();
    }

    protected function authorizeVesselSelection($user, int $vesselId): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $allowedVessels = $this->getAccessibleVessels($user)->pluck('id')->all();

        if (! in_array($vesselId, $allowedVessels, true)) {
            abort(403, 'Unauthorized vessel.');
        }
    }

    protected function hasColumn(string $table, string $column): bool
    {
        return collect(Schema::getColumnListing($table))->contains($column);
    }
}
