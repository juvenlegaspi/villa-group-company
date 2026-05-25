<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use App\Models\VesselCertificate;
use Illuminate\Http\Request;

class VesselCertificateController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $withCounts = fn ($query) => $query->withCount([
            'certificates as expired_count' => fn ($certificateQuery) => $certificateQuery->expired(),
            'certificates as expiring_count' => fn ($certificateQuery) => $certificateQuery->expiringWithinDays(),
        ]);

        $vessels = $user->is_admin == 1 || ($user->role === 'manager' && $user->department_id == 6)
            ? $withCounts(Vessel::query())->get()
            : $withCounts(Vessel::where('captain_id', $user->id))->get();

        return view('vessel_certificates.index', compact('vessels'));
    }

    public function create($vessel)
    {
        $vessel = Vessel::findOrFail($vessel);
        $this->authorizeVesselAccess($vessel);

        return view('vessel_certificates.create', compact('vessel'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vessel_id' => 'required',
            'certificate_name' => 'required|unique:vessel_certificates,certificate_name',
            'issue_date' => 'required|date',
            'expiry_date' => 'required|date|after_or_equal:issue_date',
            'remarks' => 'required',
            'document' => 'required|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ], [
            'certificate_name.required' => 'Certificate name is required.',
            'certificate_name.unique'   => 'Certificate name is already taken.',
            'issue_date.required'       => 'Issue date is required.',
            'expiry_date.required'      => 'Expiry date is required.',
            'remarks.required'          => 'Input N/A if none.',
            'document.required'         => 'Please upload a document.',
        ]);

        $vessel = Vessel::findOrFail($data['vessel_id']);
        $this->authorizeVesselManagement($vessel);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/certificates'), $filename);
            $data['document'] = $filename;
        }
        VesselCertificate::create($data);
        return redirect()->route('vessel.certificates.show', $data['vessel_id'])
            ->with('success', 'Certificate saved successfully.');
    }

    public function show(Request $request, $id)
    {
        $vessel = Vessel::findOrFail($id);
        $this->authorizeVesselAccess($vessel);
        $query = VesselCertificate::query()->where('vessel_id', $id);

        if ($request->filled('search')) {
            $query->where('certificate_name', 'like', '%' . $request->search . '%');
        }

        if ($request->filter === 'expired') {
            $query->expired();
        }

        if ($request->filter === 'expiring') {
            $query->expiringWithinDays();
        }

        if ($request->filter === 'valid') {
            $query->where('expiry_date', '>', now()->copy()->addDays(30));
        }

        $certificates = $query->orderBy('expiry_date')->paginate(10);
        $today = now();

        return view('vessel_certificates.show', compact('vessel', 'certificates', 'today'));
    }

    public function edit($id)
    {
        $certificate = VesselCertificate::with('vessel')->findOrFail($id);
        $this->authorizeVesselManagement($certificate->vessel);

        return view('vessel_certificates.edit', compact('certificate'));
    }

    public function update(Request $request, $id)
    {
        $certificate = VesselCertificate::findOrFail($id);
        $this->authorizeVesselManagement(Vessel::findOrFail($certificate->vessel_id));

        $data = $request->validate([
            'certificate_name' => 'required',
            'issue_date' => 'required|date',
            'expiry_date' => 'required|date|after_or_equal:issue_date',
            'remarks' => 'nullable',
            'document' => 'nullable|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('document')) {
            if ($certificate->document && file_exists(public_path('uploads/certificates/' . $certificate->document))) {
                unlink(public_path('uploads/certificates/' . $certificate->document));
            }

            $file = $request->file('document');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/certificates'), $filename);
            $data['document'] = $filename;
        }

        $certificate->update($data);

        return redirect()->route('vessel.certificates.show', $certificate->vessel_id)
            ->with('success', 'Certificate updated successfully.');
    }

    public function dashboard()
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->role === 'manager', 403);

        $today = now();
        $windowEnd = $today->copy()->addDays(30);

        $totalCertificates = VesselCertificate::count();
        $expiredCertificates = VesselCertificate::expired()->count();
        $expiringCertificates = VesselCertificate::expiringWithinDays()->count();
        $validCertificates = VesselCertificate::where('expiry_date', '>', $windowEnd)->count();
        $vesselsWithCertificates = Vessel::has('certificates')->count();
        $renewedThisMonth = VesselCertificate::whereMonth('issue_date', $today->month)
            ->whereYear('issue_date', $today->year)
            ->count();

        $expiredList = VesselCertificate::with('vessel')
            ->expired()
            ->orderBy('expiry_date')
            ->limit(6)
            ->get();

        $expiringList = VesselCertificate::with('vessel')
            ->expiringWithinDays()
            ->orderBy('expiry_date')
            ->limit(6)
            ->get();

        $recentCertificates = VesselCertificate::with('vessel')
            ->latest('issue_date')
            ->limit(6)
            ->get();

        $certificateStatusCounts = [
            'Expired' => $expiredCertificates,
            'Expiring Soon' => $expiringCertificates,
            'Valid' => $validCertificates,
        ];

        $vesselRiskSummary = Vessel::withCount([
                'certificates as expired_count' => fn ($query) => $query->expired(),
                'certificates as expiring_count' => fn ($query) => $query->expiringWithinDays(),
                'certificates as total_certificates_count',
            ])
            ->havingRaw('(expired_count + expiring_count) > 0')
            ->orderByRaw('(expired_count + expiring_count) DESC')
            ->orderBy('vessel_name')
            ->limit(8)
            ->get()
            ->map(function ($vessel) {
                return [
                    'vessel_name' => $vessel->vessel_name,
                    'expired_count' => (int) $vessel->expired_count,
                    'expiring_count' => (int) $vessel->expiring_count,
                    'total_certificates_count' => (int) $vessel->total_certificates_count,
                ];
            });

        $expiryTrend = VesselCertificate::query()
            ->selectRaw('YEAR(expiry_date) as year_num, MONTH(expiry_date) as month_num, COUNT(*) as total')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today->copy()->startOfMonth())
            ->groupBy('year_num', 'month_num')
            ->orderBy('year_num')
            ->orderBy('month_num')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'label' => sprintf('%s %d', now()->copy()->setDate($row->year_num, $row->month_num, 1)->format('M'), $row->year_num),
                'total' => (int) $row->total,
            ]);

        $certificateTypes = VesselCertificate::query()
            ->selectRaw('certificate_name, COUNT(*) as total')
            ->groupBy('certificate_name')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return view('vessel_certificates.dashboard', [
            'today' => $today,
            'totalCertificates' => $totalCertificates,
            'expiredCertificates' => $expiredCertificates,
            'expiringCertificates' => $expiringCertificates,
            'validCertificates' => $validCertificates,
            'vesselsWithCertificates' => $vesselsWithCertificates,
            'renewedThisMonth' => $renewedThisMonth,
            'expiredList' => $expiredList,
            'expiringList' => $expiringList,
            'recentCertificates' => $recentCertificates,
            'certificateStatusLabels' => array_keys($certificateStatusCounts),
            'certificateStatusData' => array_values($certificateStatusCounts),
            'vesselRiskSummary' => $vesselRiskSummary,
            'vesselRiskLabels' => $vesselRiskSummary->pluck('vessel_name')->values(),
            'vesselRiskData' => $vesselRiskSummary->map(fn ($row) => $row['expired_count'] + $row['expiring_count'])->values(),
            'expiryTrend' => $expiryTrend,
            'certificateTypeLabels' => $certificateTypes->pluck('certificate_name')->values(),
            'certificateTypeData' => $certificateTypes->pluck('total')->values(),
        ]);
    }

    protected function authorizeVesselAccess(Vessel $vessel): void
    {
        $user = auth()->user();

        if ($user->isAdmin() || $user->role === 'manager') {
            return;
        }

        abort_unless($vessel->captain_id === $user->id, 403);
    }

    protected function authorizeVesselManagement(Vessel $vessel): void
    {
        $user = auth()->user();

        if ($user->isAdmin() || $user->role === 'manager') {
            return;
        }

        abort_unless($vessel->captain_id === $user->id, 403);
    }
}
