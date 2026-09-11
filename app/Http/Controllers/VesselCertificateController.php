<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use App\Models\VesselCertificate;
use App\Models\VesselCertificateAudit;
use App\Models\VesselCertificateDocument;
use App\Services\VesselAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VesselCertificateController extends Controller
{
    private const DOCUMENT_RULES = 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:5120';

    public function __construct(private readonly VesselAccessService $vesselAccess) {}

    public function index()
    {
        $withCounts = fn ($query) => $query->withCount([
            'certificates as expired_count' => fn ($q) => $q->effective()->expired(),
            'certificates as expiring_count' => fn ($q) => $q->effective()->expiringWithinDays(),
            'certificates as certificate_count' => fn ($q) => $q->effective(),
        ]);
        $vessels = $withCounts($this->vesselAccess->scopeAccessible(Vessel::query(), auth()->user()))
            ->orderBy('vessel_name')->get();

        return view('vessel_certificates.index', ['vessels' => $vessels, 'canManage' => $this->canManageCertificates()]);
    }

    public function create(Vessel $vessel)
    {
        $this->authorizeVesselManagement($vessel);
        return view('vessel_certificates.create', ['vessel' => $vessel, 'renewal' => null]);
    }

    public function renew(VesselCertificate $certificate)
    {
        $certificate->load('vessel');
        $this->authorizeVesselManagement($certificate->vessel);
        abort_unless($certificate->workflow_status === 'active', 422, 'Only an active certificate can be renewed.');
        $suggestedIssueDate = $certificate->expiry_date
            ? $certificate->expiry_date->copy()->addDay()->max(today())
            : today();
        $validityDays = $certificate->issue_date && $certificate->expiry_date
            ? max(1, $certificate->issue_date->diffInDays($certificate->expiry_date))
            : null;
        $suggestedExpiryDate = $validityDays
            ? $suggestedIssueDate->copy()->addDays($validityDays)
            : null;

        return view('vessel_certificates.create', [
            'vessel' => $certificate->vessel,
            'renewal' => $certificate,
            'suggestedIssueDate' => $suggestedIssueDate,
            'suggestedExpiryDate' => $suggestedExpiryDate,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCertificate($request, true);
        $vessel = Vessel::findOrFail($data['vessel_id']);
        $this->authorizeVesselManagement($vessel);
        $upload = $this->storeDocument($request->file('document'));
        try {
            $certificate = DB::transaction(function () use ($data, $upload, $vessel): VesselCertificate {
                Vessel::whereKey($vessel->id)->lockForUpdate()->firstOrFail();
                $this->ensureNameAvailable($vessel->id, $data['certificate_name']);
                $certificate = VesselCertificate::create([...$data, ...$upload, 'workflow_status' => 'active', 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
                $this->recordDocument($certificate, $upload);
                $this->recordAudit($certificate, 'created_and_activated', ['certificate_name' => $certificate->certificate_name]);
                return $certificate;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($upload['document']);
            throw $exception;
        }
        return redirect()->route('vessel.certificates.show', $certificate->vessel_id)->with('success', 'Certificate saved successfully.');
    }

    public function storeRenewal(Request $request, VesselCertificate $certificate)
    {
        $certificate->load('vessel');
        $this->authorizeVesselManagement($certificate->vessel);
        $data = $this->validateCertificate($request, true);
        abort_unless((int) $data['vessel_id'] === (int) $certificate->vessel_id, 422, 'The renewal vessel cannot be changed.');
        $upload = $this->storeDocument($request->file('document'));
        try {
            $renewal = DB::transaction(function () use ($certificate, $data, $upload): VesselCertificate {
                $locked = VesselCertificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();
                Vessel::whereKey($locked->vessel_id)->lockForUpdate()->firstOrFail();
                abort_if($locked->workflow_status === 'superseded', 422, 'This certificate has already been renewed.');
                $this->ensureNameAvailable($locked->vessel_id, $data['certificate_name'], [$locked->id]);
                $locked->update(['workflow_status' => 'superseded', 'updated_by' => auth()->id()]);
                $renewal = VesselCertificate::create([...$data, ...$upload, 'workflow_status' => 'active', 'previous_certificate_id' => $locked->id, 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
                $this->recordDocument($renewal, $upload);
                $this->recordAudit($locked, 'superseded', ['renewed_by_certificate_id' => $renewal->id]);
                $this->recordAudit($renewal, 'renewed_and_activated', ['previous_certificate_id' => $locked->id]);
                return $renewal;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($upload['document']);
            throw $exception;
        }
        return redirect()->route('vessel.certificates.show', $renewal->vessel_id)->with('success', 'Certificate renewed and activated. The previous record was preserved as history.');
    }

    public function show(Request $request, $id)
    {
        $vessel = Vessel::findOrFail($id);
        $this->authorizeVesselAccess($vessel);
        $base = VesselCertificate::query()->where('vessel_id', $vessel->id);
        $current = (clone $base)->effective();
        $counts = [
            'total' => (clone $current)->count(),
            'valid' => (clone $current)->whereDate('expiry_date', '>', today()->addDays(30))->count(),
            'expiring' => (clone $current)->expiringWithinDays()->count(),
            'expired' => (clone $current)->expired()->count(),
        ];
        $query = clone $base;
        if ($request->filled('search')) $query->where('certificate_name', 'like', '%'.trim((string) $request->search).'%');
        match ($request->filter) {
            'expired' => $query->current()->expired(),
            'expiring' => $query->current()->expiringWithinDays(),
            'valid' => $query->current()->whereDate('expiry_date', '>', today()->addDays(30)),
            'superseded' => $query->where('workflow_status', 'superseded'),
            'draft' => $query->where('workflow_status', 'draft'),
            'pending_approval' => $query->where('workflow_status', 'pending_approval'),
            default => $query->current(),
        };
        $certificates = $query->with(['creator', 'updater'])->orderBy('expiry_date')->paginate(10)->withQueryString();
        return view('vessel_certificates.show', compact('vessel', 'certificates', 'counts') + [
            'today' => today(), 'canManage' => $this->canManageCertificates(),
        ]);
    }

    public function edit($id)
    {
        $certificate = VesselCertificate::with(['vessel', 'documents.uploader', 'audits.user'])->findOrFail($id);
        $this->authorizeVesselManagement($certificate->vessel);
        abort_unless(in_array($certificate->workflow_status, ['draft', 'active'], true), 422, 'Submitted and historical certificates are read-only.');
        return view('vessel_certificates.edit', compact('certificate'));
    }

    public function update(Request $request, $id)
    {
        $certificate = VesselCertificate::with('vessel')->findOrFail($id);
        $this->authorizeVesselManagement($certificate->vessel);
        abort_unless(in_array($certificate->workflow_status, ['draft', 'active'], true), 422, 'Submitted and historical certificates are read-only.');
        $data = $this->validateCertificate($request, false);
        unset($data['vessel_id']);
        $upload = $request->hasFile('document') ? $this->storeDocument($request->file('document')) : null;
        try {
            DB::transaction(function () use ($certificate, $data, $upload): void {
                $locked = VesselCertificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();
                Vessel::whereKey($locked->vessel_id)->lockForUpdate()->firstOrFail();
                $this->ensureNameAvailable($locked->vessel_id, $data['certificate_name'], array_filter([$locked->id, $locked->previous_certificate_id]));
                $before = $locked->only(['certificate_name', 'certificate_number', 'certificate_type', 'issuing_authority', 'issue_date', 'expiry_date', 'remarks', 'document']);
                if ($upload) {
                    VesselCertificateDocument::where('vessel_certificate_id', $locked->id)->update(['is_current' => false]);
                    $data = [...$data, ...$upload];
                }
                $locked->update([...$data, 'updated_by' => auth()->id()]);
                if ($upload) $this->recordDocument($locked, $upload);
                $this->recordAudit($locked, 'updated', ['before' => $before, 'after' => $locked->fresh()->only(array_keys($before))]);
            });
        } catch (\Throwable $exception) {
            if ($upload) Storage::disk('local')->delete($upload['document']);
            throw $exception;
        }
        return redirect()->route('vessel-certificates.edit', $certificate)->with('success', 'Certificate updated. Previous documents remain in the document history.');
    }

    public function dashboard()
    {
        abort_unless(
            auth()->user()->isExecutiveViewer() || $this->vesselAccess->canAccessAllVessels(auth()->user()),
            403
        );
        [$yearExpression, $monthExpression] = match (DB::connection()->getDriverName()) {
            'sqlite' => ["strftime('%Y', expiry_date)", "strftime('%m', expiry_date)"],
            'pgsql' => ['EXTRACT(YEAR FROM expiry_date)', 'EXTRACT(MONTH FROM expiry_date)'],
            default => ['YEAR(expiry_date)', 'MONTH(expiry_date)'],
        };
        $today = today();
        $current = VesselCertificate::query()->effective();
        $totalCertificates = (clone $current)->count();
        $expiredCertificates = (clone $current)->expired()->count();
        $expiringCertificates = (clone $current)->expiringWithinDays()->count();
        $validCertificates = (clone $current)->whereDate('expiry_date', '>', $today->copy()->addDays(30))->count();
        $vesselsWithCertificates = Vessel::whereHas('certificates', fn (Builder $q) => $q->effective())->count();
        $renewedThisMonth = VesselCertificate::whereNotNull('previous_certificate_id')->whereBetween('created_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])->count();
        $expiredList = VesselCertificate::with('vessel')->effective()->expired()->orderBy('expiry_date')->limit(6)->get();
        $expiringList = VesselCertificate::with('vessel')->effective()->expiringWithinDays()->orderBy('expiry_date')->limit(6)->get();
        $recentCertificates = VesselCertificate::with('vessel')->effective()->latest('updated_at')->limit(6)->get();
        $statusCounts = ['Expired' => $expiredCertificates, 'Expiring Soon' => $expiringCertificates, 'Valid' => $validCertificates];
        $vesselRiskSummary = Vessel::whereHas('certificates', fn (Builder $q) => $q->effective()
            ->whereDate('expiry_date', '<=', $today->copy()->addDays(30)))
            ->withCount([
            'certificates as expired_count' => fn ($q) => $q->effective()->expired(),
            'certificates as expiring_count' => fn ($q) => $q->effective()->expiringWithinDays(),
            'certificates as total_certificates_count' => fn ($q) => $q->effective(),
        ])->orderByRaw('(expired_count + expiring_count) DESC')->orderBy('vessel_name')->limit(8)->get()->map(fn ($v) => [
            'vessel_name' => $v->vessel_name, 'expired_count' => (int) $v->expired_count,
            'expiring_count' => (int) $v->expiring_count, 'total_certificates_count' => (int) $v->total_certificates_count,
        ]);
        $expiryTrend = VesselCertificate::effective()->selectRaw("{$yearExpression} as year_num, {$monthExpression} as month_num, COUNT(*) as total")
            ->whereNotNull('expiry_date')->whereDate('expiry_date', '>=', $today->copy()->startOfMonth())
            ->groupBy('year_num', 'month_num')->orderBy('year_num')->orderBy('month_num')->limit(6)->get()
            ->map(fn ($row) => ['label' => sprintf('%s %d', now()->setDate($row->year_num, $row->month_num, 1)->format('M'), $row->year_num), 'total' => (int) $row->total]);
        $certificateTypes = VesselCertificate::effective()->selectRaw('certificate_name, COUNT(*) as total')->groupBy('certificate_name')->orderByDesc('total')->limit(6)->get();
        return view('vessel_certificates.dashboard', [
            'today' => $today, 'totalCertificates' => $totalCertificates, 'expiredCertificates' => $expiredCertificates,
            'expiringCertificates' => $expiringCertificates, 'validCertificates' => $validCertificates,
            'vesselsWithCertificates' => $vesselsWithCertificates, 'renewedThisMonth' => $renewedThisMonth,
            'expiredList' => $expiredList, 'expiringList' => $expiringList, 'recentCertificates' => $recentCertificates,
            'certificateStatusLabels' => array_keys($statusCounts), 'certificateStatusData' => array_values($statusCounts),
            'vesselRiskSummary' => $vesselRiskSummary, 'vesselRiskLabels' => $vesselRiskSummary->pluck('vessel_name')->values(),
            'vesselRiskData' => $vesselRiskSummary->map(fn ($r) => $r['expired_count'] + $r['expiring_count'])->values(),
            'expiryTrend' => $expiryTrend, 'certificateTypeLabels' => $certificateTypes->pluck('certificate_name')->values(),
            'certificateTypeData' => $certificateTypes->pluck('total')->values(),
        ]);
    }

    public function downloadDocument($id): BinaryFileResponse
    {
        $certificate = VesselCertificate::with('vessel')->findOrFail($id);
        $this->authorizeVesselAccess($certificate->vessel);
        abort_unless($certificate->document, 404);
        $this->recordAudit($certificate, 'document_downloaded');
        return $this->downloadPath($certificate->document, $certificate->document_original_name);
    }

    public function downloadVersion(VesselCertificate $certificate, VesselCertificateDocument $document): BinaryFileResponse
    {
        abort_unless((int) $document->vessel_certificate_id === (int) $certificate->id, 404);
        $certificate->load('vessel');
        $this->authorizeVesselAccess($certificate->vessel);
        $this->recordAudit($certificate, 'document_version_downloaded', ['document_id' => $document->id]);
        return $this->downloadPath($document->path, $document->original_name);
    }

    private function validateCertificate(Request $request, bool $documentRequired): array
    {
        return $request->validate([
            'vessel_id' => 'required|integer|exists:vessels,id', 'certificate_name' => 'required|string|max:255',
            'certificate_number' => 'nullable|string|max:255', 'certificate_type' => 'nullable|string|max:255',
            'issuing_authority' => 'nullable|string|max:255',
            'issue_date' => 'required|date', 'expiry_date' => 'required|date|after_or_equal:issue_date',
            'remarks' => 'nullable|string|max:2000', 'document' => ($documentRequired ? 'required|' : 'nullable|').self::DOCUMENT_RULES,
        ]);
    }

    private function ensureNameAvailable(int $vesselId, string $name, array $ignoreIds = []): void
    {
        $exists = VesselCertificate::current()->where('vessel_id', $vesselId)
            ->whereRaw('LOWER(certificate_name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreIds, fn ($q) => $q->whereNotIn('id', $ignoreIds))->exists();
        if ($exists) throw ValidationException::withMessages(['certificate_name' => 'An active certificate with this name already exists for the vessel. Use Renew instead.']);
    }

    private function storeDocument(UploadedFile $file): array
    {
        return ['document' => $file->store('certificates', 'local'), 'document_original_name' => $file->getClientOriginalName(), 'document_mime_type' => $file->getMimeType(), 'document_size' => $file->getSize()];
    }

    private function recordDocument(VesselCertificate $certificate, array $upload): void
    {
        $certificate->documents()->create(['path' => $upload['document'], 'original_name' => $upload['document_original_name'], 'mime_type' => $upload['document_mime_type'], 'size' => $upload['document_size'], 'is_current' => true, 'uploaded_by' => auth()->id()]);
    }

    private function recordAudit(VesselCertificate $certificate, string $action, array $changes = []): void
    {
        VesselCertificateAudit::create(['vessel_certificate_id' => $certificate->id, 'user_id' => auth()->id(), 'action' => $action, 'changes' => $changes ?: null, 'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent() ? mb_substr(request()->userAgent(), 0, 1000) : null]);
    }

    private function downloadPath(string $path, ?string $downloadName): BinaryFileResponse
    {
        if (Storage::disk('local')->exists($path)) return response()->download(Storage::disk('local')->path($path), $downloadName ?: basename($path));
        $legacyPath = public_path('uploads/certificates/'.basename($path));
        abort_unless(is_file($legacyPath), 404);
        return response()->download($legacyPath, $downloadName ?: basename($legacyPath));
    }

    private function canManageCertificates(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        $user->loadMissing(['accessRole', 'position', 'department']);
        if ($user->isSystemAdministrator() || $user->accessRole?->slug === 'company-administrator') return true;

        return strtolower(trim((string) $user->department?->name)) === 'marine operations'
            && in_array($user->position?->code, ['operations-manager', 'operation-manager', 'marine-operations-manager', 'liaison-officer'], true)
            && $user->hasPermission('certificates.manage');
    }

    protected function authorizeVesselAccess(Vessel $vessel): void { $this->vesselAccess->authorize(auth()->user(), $vessel); }
    protected function authorizeVesselManagement(Vessel $vessel): void
    {
        $this->authorizeVesselAccess($vessel);
        abort_unless($this->canManageCertificates(), 403, 'Certificate management permission is required.');
    }

}
