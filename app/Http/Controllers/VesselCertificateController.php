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
            'certificate_name' => 'required',
            'issue_date' => 'required|date',
            'expiry_date' => 'required|date|after_or_equal:issue_date',
            'remarks' => 'nullable',
            'document' => 'nullable|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
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

        $certificates = $query->orderBy('expiry_date')->get();
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

        $totalCertificates = VesselCertificate::count();
        $expiredCertificates = VesselCertificate::expired()->count();
        $expiringCertificates = VesselCertificate::expiringWithinDays()->count();
        $validCertificates = VesselCertificate::where('expiry_date', '>', now()->copy()->addDays(30))->count();

        $expiredList = VesselCertificate::expired()
            ->orderBy('expiry_date')
            ->limit(5)
            ->get();

        $expiringList = VesselCertificate::expiringWithinDays()
            ->orderBy('expiry_date')
            ->limit(5)
            ->get();

        return view('vessel_certificates.dashboard', compact(
            'totalCertificates',
            'expiredCertificates',
            'expiringCertificates',
            'validCertificates',
            'expiredList',
            'expiringList'
        ));
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
