<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use Illuminate\Support\Facades\Auth;

class VesselCertificateController extends Controller
{
    //
    public function index()
{
    $user = auth()->user();

    // COMMON COUNTS
    $withCounts = function ($query) {
        return $query->withCount([
            'certificates as expired_count' => function($q){
                $q->where('expiry_date', '<', now());
            },
            'certificates as expiring_count' => function($q){
                $q->whereBetween('expiry_date', [now(), now()->copy()->addDays(30)]);
            }
        ]);
    };

    //  ADMIN or MANAGER → TANAN
    if ($user->is_admin == 1 || ($user->role == 'manager' && $user->department_id == 1)) {

        $vessels = $withCounts(Vessel::query())->get();

    } else {

        // CAPTAIN / OTHER → assigned vessel only
        $vessels = $withCounts(
            Vessel::where('captain_id', $user->id)
        )->get();
    }

    return view('vessel_certificates.index', compact('vessels'));
}
    public function create($vessel)
    {
        $vessel = Vessel::findOrFail($vessel);
        return view('vessel_certificates.create', compact('vessel'));
    }
    public function store(Request $request)
{
    $data = $request->validate([
        'vessel_id' => 'required',
        'certificate_name' => 'required',
        'issue_date' => 'required|date',
        'expiry_date' => 'required|date',
        'remarks' => 'nullable',
        'document' => 'nullable|file|max:5120' // 5MB
    ]);

    // FILE UPLOAD
    if ($request->hasFile('document')) {
        $file = $request->file('document');
        $filename = time().'_'.$file->getClientOriginalName();
        $file->move(public_path('uploads/certificates'), $filename);
        $data['document'] = $filename;
    }

    VesselCertificate::create($data);

    return redirect()->route('vessel-certificates.show', $data['vessel_id'])
        ->with('success', 'Certificate saved!');
}
    /*public function vesselCertificates($id)
    {
        $vessel = Vessel::findOrFail($id);
        $certificates = VesselCertificate::where('vessel_id', $id)
            ->with('vessel')
            ->get();
        $today = now();
        $expired = VesselCertificate::where('vessel_id',$id)
            ->where('expiry_date','<',$today)
            ->count();
        $expiringSoon = VesselCertificate::where('vessel_id',$id)
            ->whereBetween('expiry_date',[$today,$today->copy()->addDays(30)])
            ->count();
        return view('vessel_certificates.vessel_certificates', compact(
            'vessel',
            'certificates',
            'expired',
            'expiringSoon'
        ));
    }*/
    public function show(Request $request, $id)
    {
        $vessel = \App\Models\Vessel::findOrFail($id);
        $query = \App\Models\VesselCertificate::where('vessel_id', $id);
        // SEARCH
        if ($request->search) {
            $query->where('certificate_name', 'like', '%' . $request->search . '%');
        }
        // FILTER
        if ($request->filter == 'expired') {
            $query->where('expiry_date', '<', now());
        }
        if ($request->filter == 'expiring') {
            $query->whereBetween('expiry_date', [now(), now()->copy()->addDays(30)]);
        }
        $certificates = $query->orderBy('expiry_date', 'asc')->get();
        $today = now();
        return view('vessel_certificates.show', compact(
            'vessel',
            'certificates',
            'today'
        ));
    }
    public function edit($id)
    {
        $certificate = VesselCertificate::with('vessel')->findOrFail($id);
        return view('vessel_certificates.edit', compact('certificate'));
    }
    public function update(Request $request, $id)
{
    $certificate = VesselCertificate::findOrFail($id);

    $data = $request->validate([
        'certificate_name' => 'required',
        'issue_date' => 'required|date',
        'expiry_date' => 'required|date',
        'remarks' => 'nullable',
        'document' => 'nullable|file|max:5120'
    ]);

    // FILE UPLOAD
    if ($request->hasFile('document')) {

        // OPTIONAL: delete old file
        if ($certificate->document && file_exists(public_path('uploads/certificates/'.$certificate->document))) {
            unlink(public_path('uploads/certificates/'.$certificate->document));
        }

        $file = $request->file('document');
        $filename = time().'_'.$file->getClientOriginalName();
        $file->move(public_path('uploads/certificates'), $filename);

        $data['document'] = $filename;
    }

    $certificate->update($data);

    return redirect()->route('vessel-certificates.show', $certificate->vessel_id)
        ->with('success', 'Certificate updated!');
}
    public function dashboard()
{
    $totalCertificates = VesselCertificate::count();

    $expiredCertificates = VesselCertificate::where('expiry_date','<',now())->count();

    $expiringCertificates = VesselCertificate::whereBetween(
        'expiry_date',
        [now(), now()->addDays(30)]
    )->count();

    $validCertificates = VesselCertificate::where('expiry_date','>',now()->addDays(30))->count();

    // lists
    $expiredList = VesselCertificate::where('expiry_date','<',now())
                    ->orderBy('expiry_date','asc')
                    ->limit(5)
                    ->get();

    $expiringList = VesselCertificate::whereBetween(
                    'expiry_date',[now(), now()->addDays(30)])
                    ->orderBy('expiry_date','asc')
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
}
