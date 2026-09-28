<?php

namespace App\Http\Controllers;

use App\Models\TechDefect;
use App\Models\TechDefectAudit;
use App\Models\TechDefectAttachment;
use App\Models\TechDefectInformationRequest;
use App\Models\ThirdPartySupport;
use App\Models\User;
use App\Models\Vessel;
use App\Notifications\TechDefectNotification;
use App\Services\SimpleXlsxWriter;
use App\Services\VesselAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TechDefectController extends Controller
{
    public function __construct(private readonly VesselAccessService $vesselAccess) {}

    public const STATUS_NEW_REPORT = 'New Report';
    public const STATUS_FOR_REVIEW = 'For Review';
    public const STATUS_FOR_ASSESSMENT = 'For Assessment';
    public const STATUS_FOR_ACTION = 'For Action';
    public const STATUS_ONGOING = 'Ongoing';
    public const STATUS_FOR_VERIFICATION = 'For Verification';
    public const STATUS_CLOSED = 'Closed';
    public const STATUSES = [self::STATUS_NEW_REPORT, self::STATUS_FOR_REVIEW, self::STATUS_FOR_ASSESSMENT, self::STATUS_FOR_ACTION, self::STATUS_ONGOING, self::STATUS_FOR_VERIFICATION, self::STATUS_CLOSED];
    public const ATTACHMENT_CATEGORIES = ['Checklist', 'Before Repair', 'After Repair', 'Inspection', 'Quotation', 'Service Report', 'Other'];
    public const SEVERITIES = ['Minor', 'Major', 'Critical'];
    public const IMPACTS = ['None', 'Limited', 'Stopped'];
    public const YES_NO = ['Yes', 'No'];
    public const SYSTEMS = ['Deck', 'Main Engine', 'Auxiliary Engine', 'Crane', 'Safety Equipment', 'Cargo Handling', 'Accommodation', 'Other'];

    public function dashboard()
    {
        abort_unless(
            auth()->user()->isExecutiveViewer()
                || ($this->canManageAllDefects(auth()->user()) && $this->vesselAccess->canAccessAllVessels(auth()->user())),
            403
        );
        $driver = DB::connection()->getDriverName();
        [$yearExpression, $monthExpression] = match ($driver) {
            'sqlite' => ["strftime('%Y', date_identified)", "strftime('%m', date_identified)"],
            'pgsql' => ['EXTRACT(YEAR FROM date_identified)', 'EXTRACT(MONTH FROM date_identified)'],
            default => ['YEAR(date_identified)', 'MONTH(date_identified)'],
        };
        $statusCounts = TechDefect::selectRaw('LOWER(status) as normalized_status, COUNT(*) as total')
            ->groupBy('normalized_status')->pluck('total', 'normalized_status');
        $totalReports = TechDefect::count();
        $open = (int) ($statusCounts->get(strtolower(self::STATUS_NEW_REPORT)) ?? 0);
        $ongoing = (int) ($statusCounts->get(strtolower(self::STATUS_ONGOING)) ?? 0);
        $waiting = collect([self::STATUS_FOR_REVIEW, self::STATUS_FOR_ASSESSMENT, self::STATUS_FOR_ACTION])->sum(fn ($status) => (int) ($statusCounts->get(strtolower($status)) ?? 0));
        $forVerification = (int) ($statusCounts->get(strtolower(self::STATUS_FOR_VERIFICATION)) ?? 0);
        $completed = (int) ($statusCounts->get(strtolower(self::STATUS_CLOSED)) ?? 0);
        $vesselDefects = TechDefect::selectRaw('vessel_id, COUNT(*) as total')->groupBy('vessel_id')->with('vessel')->get();
        $latestReports = TechDefect::with('vessel')->latest('id')->limit(6)->get();
        $monthlyDefects = TechDefect::selectRaw("{$yearExpression} as year_num, {$monthExpression} as month_num, COUNT(*) as total")
            ->whereNotNull('date_identified')->groupBy('year_num', 'month_num')->orderBy('year_num')->orderBy('month_num')->get()
            ->map(fn ($row) => ['label' => Carbon::create()->setDate((int) $row->year_num, (int) $row->month_num, 1)->format('M Y'), 'total' => (int) $row->total])
            ->take(-6)->values();
        $topVessel = TechDefect::selectRaw('vessel_id, COUNT(*) as total')->groupBy('vessel_id')->orderByDesc('total')->with('vessel')->first();
        $criticalDefects = TechDefect::whereRaw('LOWER(severity_level) = ?', ['critical'])->count();
        $highSeverityDefects = TechDefect::whereIn(DB::raw('LOWER(severity_level)'), ['critical', 'major'])->count();
        $thirdPartyCases = TechDefect::whereHas('supports', fn ($query) => $query->whereRaw('LOWER(status) != ?', ['done']))->count();
        $resolvedThisMonth = TechDefect::whereBetween('date_completed', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count();
        $severityBreakdown = collect(self::SEVERITIES)->mapWithKeys(fn (string $severity) => [
            $severity => TechDefect::whereRaw('LOWER(severity_level) = ?', [strtolower($severity)])->count(),
        ])->all();
        $systemBreakdown = TechDefect::selectRaw("COALESCE(NULLIF(system_affected, ''), 'UNSPECIFIED') as system_name, COUNT(*) as total")
            ->groupBy('system_name')->orderByDesc('total')->limit(6)->get();
        $portBreakdown = TechDefect::selectRaw("COALESCE(NULLIF(port_location, ''), 'UNASSIGNED') as port_name, COUNT(*) as total")
            ->groupBy('port_name')->orderByDesc('total')->limit(6)->get();
        $riskVessels = TechDefect::with('vessel')
            ->selectRaw("vessel_id, SUM(CASE WHEN LOWER(severity_level) = 'critical' THEN 1 ELSE 0 END) as critical_total, SUM(CASE WHEN LOWER(status) != ? THEN 1 ELSE 0 END) as active_total, COUNT(*) as total_reports", [strtolower(self::STATUS_CLOSED)])
            ->groupBy('vessel_id')->orderByDesc('critical_total')->orderByDesc('active_total')->limit(8)->get()
            ->map(fn ($row) => ['vessel_name' => $row->vessel?->vessel_name ?? 'Unknown Vessel', 'critical_total' => (int) $row->critical_total, 'active_total' => (int) $row->active_total, 'total_reports' => (int) $row->total_reports]);

        return view('shipping.tech_defects.dashboard', compact('totalReports', 'open', 'ongoing', 'waiting', 'forVerification', 'completed', 'vesselDefects', 'latestReports', 'monthlyDefects', 'topVessel', 'criticalDefects', 'highSeverityDefects', 'thirdPartyCases', 'resolvedThisMonth', 'severityBreakdown', 'systemBreakdown', 'portBreakdown', 'riskVessels'));
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $this->authorizeModuleAccess($user);
        $showArchived = $request->boolean('archived') && $this->canDeleteDefects($user);
        $query = $showArchived ? TechDefect::onlyTrashed() : TechDefect::query();
        $accessibleVessels = $this->vesselAccess->scopeAccessible(Vessel::query(), $user)->select('id');
        $query->with(['vessel', 'supports'])->where(function ($scope) use ($accessibleVessels, $user): void {
            $scope->whereIn('vessel_id', $accessibleVessels)
                ->orWhere('assigned_to_user_id', $user->id);
        });
        if ($request->filled('status') && in_array($request->status, self::STATUSES, true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(fn ($defects) => $defects->where('id', 'like', "%{$search}%")
                ->orWhere('defect_description', 'like', "%{$search}%")
                ->orWhereHas('vessel', fn ($vessels) => $vessels->where('vessel_name', 'like', "%{$search}%")));
        }
        $statusOrder = collect(self::STATUSES)
            ->map(fn (string $status, int $index) => 'WHEN status = ? THEN '.($index + 1))
            ->implode(' ');
        $reports = $query->orderByRaw('CASE '.$statusOrder.' ELSE '.(count(self::STATUSES) + 1).' END', self::STATUSES)
            ->orderByDesc('id')->paginate(10)->withQueryString();
        $firstReportDate = TechDefect::min('date_identified');
        $latestReportDate = TechDefect::max('date_identified');

        return view('shipping.tech_defects.index', [
            'reports' => $reports,
            'showArchived' => $showArchived,
            'canCreate' => $this->canCreateDefects($user),
            'canDashboard' => $user->isExecutiveViewer() || $this->canManageAllDefects($user),
            'canExport' => $this->canManageAllDefects($user) && $this->vesselAccess->canAccessAllVessels($user),
            'canArchive' => $this->canDeleteDefects($user),
            'exportDateFrom' => $firstReportDate ? Carbon::parse($firstReportDate)->toDateString() : now()->startOfMonth()->toDateString(),
            'exportDateTo' => $latestReportDate ? Carbon::parse($latestReportDate)->toDateString() : now()->toDateString(),
            'exportReportCount' => TechDefect::count(),
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $this->authorizeModuleAccess($user);
        abort_unless($this->canCreateDefects($user), 403, 'Your position or access profile cannot create technical defect reports.');
        return view('shipping.tech_defects.create', ['vessels' => $this->getAccessibleVessels($user), 'reportingUsers' => $this->getReportingUsers()]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $this->authorizeModuleAccess($user);
        abort_unless($this->canCreateDefects($user), 403, 'Your position or access profile cannot create technical defect reports.');
        $data = $this->validateTechDefect($request, false);
        $this->authorizeVesselSelection($user, (int) $data['vessel_id']);
        $uploads = $request->validate([
            'checklist_document' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx|max:10240',
            'defect_photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:10240',
        ], [
            'checklist_document.required' => 'Upload the completed checklist or initial evidence document.',
            'checklist_document.mimes' => 'The checklist must be an image, PDF, Word, or Excel document.',
            'checklist_document.max' => 'The checklist must not exceed 10 MB.',
            'defect_photo.required' => 'Upload a clear photo of the affected defect.',
            'defect_photo.mimes' => 'The affected defect photo must be a JPG, PNG, or WEBP image.',
            'defect_photo.max' => 'The affected defect photo must not exceed 10 MB.',
        ]);
        $checklist = $uploads['checklist_document'];
        $defectPhoto = $uploads['defect_photo'];
        $normalized = $this->normalizeTechDefectData($data);
        $storedPaths = [];
        try {
            $report = DB::transaction(function () use ($normalized, $user, $checklist, $defectPhoto, &$storedPaths): TechDefect {
                $report = TechDefect::create([...$normalized, 'status' => self::STATUS_NEW_REPORT, 'progress_percent' => 0]);
                $this->recordAudit($report, 'created', null, self::STATUS_NEW_REPORT, $normalized, 'Technical defect report created by vessel personnel.', $user);
                foreach ([
                    ['file' => $checklist, 'category' => 'Checklist', 'description' => 'Initial checklist document uploaded.'],
                    ['file' => $defectPhoto, 'category' => 'Before Repair', 'description' => 'Affected defect photo uploaded with the initial report.'],
                ] as $upload) {
                    $path = $upload['file']->store("tech-defects/{$report->id}", 'local');
                    $storedPaths[] = $path;
                    $attachment = $report->attachments()->create([
                        'uploaded_by' => $user->id,
                        'category' => $upload['category'],
                        'original_name' => $upload['file']->getClientOriginalName(),
                        'path' => $path,
                        'mime_type' => $upload['file']->getMimeType(),
                        'size' => $upload['file']->getSize(),
                    ]);
                    $this->recordAudit($report, 'attachment_uploaded', self::STATUS_NEW_REPORT, self::STATUS_NEW_REPORT, [
                        'attachment_id' => $attachment->id,
                        'category' => $upload['category'],
                        'name' => $attachment->original_name,
                    ], $upload['description'], $user);
                }
                return $report;
            });
        } catch (\Throwable $exception) {
            if ($storedPaths) Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
        $this->notifyOnCreation($report->fresh(['assignee']));
        return redirect()->route('tech-defects.show', $report)->with('success', 'Report added successfully.');
    }

    public function edit($id)
    {
        $report = TechDefect::findOrFail($id);
        $this->authorizeVesselSelection(auth()->user(), (int) $report->vessel_id);
        abort_unless($report->status === self::STATUS_NEW_REPORT, 422, 'A report can be edited only before it is submitted for review.');
        return view('shipping.tech_defects.edit', ['report' => $report, 'vessels' => $this->getAccessibleVessels(auth()->user()), 'reportingUsers' => $this->getReportingUsers()]);
    }

    public function update(Request $request, $id)
    {
        $report = TechDefect::findOrFail($id);
        $this->authorizeTechDefectAccess($report);
        return match ((string) $request->action) {
            'submit_review' => $this->submitForReview($report),
            'review_confirm' => $this->confirmReview($request, $report),
            'return_report' => $this->returnReportForCorrection($request, $report),
            'save_assessment' => $this->saveAssessment($request, $report, false),
            'complete_assessment' => $this->saveAssessment($request, $report, true),
            'request_information' => $this->requestAdditionalInformation($request, $report),
            'respond_information' => $this->respondAdditionalInformation($request, $report),
            'start_action', 'start' => $this->startRepair($report),
            'update_progress' => $this->updateProgress($request, $report),
            'add_support' => $this->addThirdPartySupport($request, $report),
            'complete', 'submit_completion' => $this->submitForVerification($report),
            'verify_resolution' => $this->verifyResolution($request, $report),
            'confirm_completion', 'verify_completion' => $this->confirmCompletion($request, $report),
            'reopen_issue', 'return_repair' => $this->reopenIssue($request, $report),
            'update_closeout' => $this->updateRepairCloseout($request, $report),
            'update_details' => $this->updateReportDetails($request, $report),
            default => str_starts_with((string) $request->action, 'done_')
                ? $this->markSupportAsDone($request, $report, (int) str_replace('done_', '', (string) $request->action))
                : abort(422, 'Invalid technical defect action.'),
        };
    }

    public function destroy($id)
    {
        $actor = auth()->user();
        abort_unless($this->canDeleteDefects($actor), 403, 'Only a manager or administrator can archive reports.');
        DB::transaction(function () use ($id, $actor): void {
            $report = TechDefect::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->recordAudit($report, 'archived', $report->status, $report->status, [], 'Report archived; history preserved.', $actor);
            $report->delete();
        });
        return redirect()->route('tech-defects.index')->with('success', 'Report archived successfully. Its audit history was preserved.');
    }

    public function restore($id)
    {
        $actor = auth()->user();
        abort_unless($this->canDeleteDefects($actor), 403, 'Only a manager or administrator can restore reports.');
        DB::transaction(function () use ($id, $actor): void {
            $report = TechDefect::onlyTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
            $report->restore();
            $this->recordAudit($report, 'restored', $report->status, $report->status, [], 'Archived report restored.', $actor);
        });
        return redirect()->route('tech-defects.show', $id)->with('success', 'Report restored successfully.');
    }

    public function show($id)
    {
        $report = TechDefect::with(['vessel', 'reporter', 'assignee.position', 'reviewSubmitter', 'reviewer', 'assessor', 'actionAssigner', 'progressUpdater', 'repairCompleter', 'verifier', 'closer', 'attachments.uploader', 'informationRequests.requester', 'informationRequests.responder', 'supports.creator', 'supports.completer', 'audits.user'])->findOrFail($id);
        $this->authorizeTechDefectAccess($report);
        $user = auth()->user();
        $eligibleVerifiers = $report->status === self::STATUS_FOR_VERIFICATION
            ? $this->getShippingUsers()->filter(fn (User $candidate) =>
                (int) $candidate->id !== (int) $report->repair_completed_by
                && $this->canVerifyDefects($candidate, $report)
            )->values()
            : collect();
        return view('shipping.tech_defects.show', [
            'report' => $report,
            'supports' => $report->supports,
            'technicalUsers' => $this->getTechnicalUsers(),
            'canEdit' => $this->canEditReport($user, $report),
            'canSubmitReview' => $this->canSubmitReview($user, $report),
            'canReview' => $this->canReviewDefects($user, $report),
            'canAssess' => $this->canAssessDefects($user, $report),
            'canPerformAction' => $this->canPerformAction($user, $report),
            'canCloseout' => $this->canPerformPermission($user, $report, 'tech_defects.update_closeout'),
            'canUpload' => $this->canPerformPermission($user, $report, 'tech_defects.upload_evidence'),
            'canRequestSupport' => $this->canManageThirdPartySupport($user, $report),
            'canSubmitVerification' => $this->canPerformPermission($user, $report, 'tech_defects.submit'),
            'canDelete' => $this->canDeleteDefects($user),
            'canVerify' => $this->canVerifyDefects($user, $report),
            'isRepairSubmitter' => (int) $report->repair_completed_by === (int) $user->id,
            'eligibleVerifiers' => $eligibleVerifiers,
        ]);
    }

    public function editRepairCloseout($id)
    {
        $report = TechDefect::with(['vessel', 'assignee'])->findOrFail($id);
        $this->authorizeTechDefectAccess($report);
        abort_unless($report->status === self::STATUS_ONGOING, 422, 'Repair close-out is available only while corrective work is ongoing.');
        abort_unless($this->canPerformPermission(auth()->user(), $report, 'tech_defects.update_closeout'), 403, 'You cannot update this repair close-out.');
        if ((int) $report->progress_percent < 100) {
            return redirect()->route('tech-defects.show', $report)->withErrors([
                'closeout' => 'Complete corrective action progress to 100% before opening repair close-out.',
            ]);
        }
        if ($report->supports()->whereRaw('LOWER(status) != ?', ['done'])->exists()) {
            return redirect()->route('tech-defects.show', $report)->withErrors([
                'closeout' => 'Complete the active third-party support before opening repair close-out.',
            ]);
        }
        return view('shipping.tech_defects.repair-closeout', compact('report'));
    }

    protected function submitForReview(TechDefect $report)
    {
        abort_unless($this->canSubmitReview(auth()->user(), $report), 403, 'Only authorized vessel personnel can submit this report.');
        DB::transaction(function () use ($report): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_NEW_REPORT, 422, 'Only a new report can be submitted for review.');
            if (! $locked->attachments()->where('category', 'Before Repair')->where('mime_type', 'like', 'image/%')->exists()) {
                throw ValidationException::withMessages([
                    'before_repair_attachment' => 'Upload at least one photo of the affected defect before submitting the report for review.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            $locked->update(['status' => self::STATUS_FOR_REVIEW, 'review_submitted_by' => auth()->id(), 'review_submitted_at' => now(), 'review_remarks' => null]);
            $this->recordAudit($locked, 'submitted_for_review', self::STATUS_NEW_REPORT, self::STATUS_FOR_REVIEW, [], 'Report submitted for vessel management review.');
        });
        $this->notifyReviewers($report->fresh(), 'Technical defect awaiting review', "{$report->report_code} was submitted for review.", 'review');
        return back()->with('success', 'Report submitted for review.');
    }

    protected function confirmReview(Request $request, TechDefect $report)
    {
        abort_unless($this->canReviewDefects(auth()->user(), $report), 403, 'Technical Defects review authority is required.');
        $data = $request->validate(['review_remarks' => 'nullable|string|max:3000']);
        DB::transaction(function () use ($report, $data): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_FOR_REVIEW, 422, 'Only reports awaiting review can be confirmed.');
            $locked->update(['status' => self::STATUS_FOR_ASSESSMENT, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'review_remarks' => $data['review_remarks'] ?? null]);
            $this->recordAudit($locked, 'review_confirmed', self::STATUS_FOR_REVIEW, self::STATUS_FOR_ASSESSMENT, [], 'Vessel management confirmed the report for technical assessment.');
        });
        $this->notifyTechnicalTeam($report->fresh(), 'Defect ready for assessment', "{$report->report_code} requires technical assessment.", 'assessment');
        return back()->with('success', 'Report confirmed and sent for technical assessment.');
    }

    protected function returnReportForCorrection(Request $request, TechDefect $report)
    {
        abort_unless($this->canReviewDefects(auth()->user(), $report), 403, 'Technical Defects review authority is required.');
        $data = $request->validate(['review_remarks' => 'required|string|max:3000']);
        DB::transaction(function () use ($report, $data): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_FOR_REVIEW, 422, 'Only reports awaiting review can be returned.');
            $locked->update(['status' => self::STATUS_NEW_REPORT, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'review_remarks' => $data['review_remarks']]);
            $this->recordAudit($locked, 'report_returned', self::STATUS_FOR_REVIEW, self::STATUS_NEW_REPORT, ['review_remarks' => $data['review_remarks']], 'Report returned to vessel personnel for correction.');
        });
        $this->notifyReporter($report->fresh(), 'Defect report returned for correction', $data['review_remarks'], 'returned');
        return back()->with('success', 'Report returned for correction.');
    }

    protected function saveAssessment(Request $request, TechDefect $report, bool $complete)
    {
        abort_unless($this->canAssessDefects(auth()->user(), $report), 403, 'A Technical Team position is required.');
        $identifiedDate = $report->date_identified?->toDateString() ?? today()->toDateString();
        $rules = [
            'technical_assessment' => [$complete ? 'required' : 'nullable', 'string', 'max:5000'],
            'technical_findings' => [$complete ? 'required' : 'nullable', 'string', 'max:5000'],
            'technical_recommendation' => [$complete ? 'required' : 'nullable', 'string', 'max:5000'],
            'assigned_to_user_id' => [$complete ? 'required' : 'nullable', 'integer', 'exists:users,id'],
            'target_completion_date' => [$complete ? 'required' : 'nullable', 'date', 'after_or_equal:'.$identifiedDate],
        ];
        $data = $request->validate($rules, [
            'target_completion_date.after_or_equal' => 'Target completion must be on or after the date identified.',
        ]);
        if ($complete) abort_unless($this->getTechnicalUsers()->contains('id', (int) $data['assigned_to_user_id']), 422, 'Assigned PIC must have an active technical position or corrective-action permission.');
        foreach (['technical_assessment', 'technical_findings', 'technical_recommendation'] as $field) if (filled($data[$field] ?? null)) $data[$field] = mb_strtoupper(trim($data[$field]));
        DB::transaction(function () use ($report, $data, $complete): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_FOR_ASSESSMENT, 422, 'Assessment can be updated only during For Assessment status.');
            $changes = [...$data, 'assessed_by' => auth()->id(), 'assessed_at' => now()];
            if ($complete) $changes = [...$changes, 'status' => self::STATUS_FOR_ACTION, 'action_assigned_by' => auth()->id(), 'action_assigned_at' => now(), 'progress_percent' => 0];
            $locked->update($changes);
            $this->recordAudit($locked, $complete ? 'assessment_completed' : 'assessment_updated', self::STATUS_FOR_ASSESSMENT, $complete ? self::STATUS_FOR_ACTION : self::STATUS_FOR_ASSESSMENT, $data, $complete ? 'Assessment completed and corrective action assigned.' : 'Technical assessment draft updated.');
        });
        if ($complete) $this->notifyAssignee($report->fresh(), 'Corrective action assigned', "You are assigned to {$report->report_code}.", 'assignment');
        return back()->with('success', $complete ? 'Assessment completed and corrective action assigned.' : 'Assessment saved.');
    }

    protected function requestAdditionalInformation(Request $request, TechDefect $report)
    {
        abort_unless($this->canAssessDefects(auth()->user(), $report), 403, 'A Technical Team position is required.');
        $data = $request->validate(['information_request' => 'required|string|max:3000']);
        DB::transaction(function () use ($report, $data): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_FOR_ASSESSMENT, 422, 'Additional information can be requested only during assessment.');
            $informationRequest = $locked->informationRequests()->create([
                'requested_by' => auth()->id(),
                'request_text' => $data['information_request'],
                'requested_at' => now(),
                'status' => 'Pending',
            ]);
            $locked->update(['information_request' => $data['information_request'], 'information_response' => null, 'information_requested_by' => auth()->id(), 'information_requested_at' => now(), 'information_responded_by' => null, 'information_responded_at' => null]);
            $this->recordAudit($locked, 'information_requested', $locked->status, $locked->status, ['information_request_id' => $informationRequest->id, 'information_request' => $data['information_request']], 'Technical Team requested additional vessel information.');
        });
        $this->notifyReporter($report->fresh(), 'Additional defect information requested', $data['information_request'], 'information');
        return back()->with('success', 'Information request sent to vessel personnel.');
    }

    protected function respondAdditionalInformation(Request $request, TechDefect $report)
    {
        abort_unless($this->canSubmitReview(auth()->user(), $report), 403, 'Only authorized vessel personnel can respond.');
        $data = $request->validate([
            'information_request_id' => 'required|integer|exists:tech_defect_information_requests,id',
            'information_response' => 'required|string|max:3000',
        ]);
        DB::transaction(function () use ($report, $data): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_FOR_ASSESSMENT, 422, 'Information can only be submitted during assessment.');
            $informationRequest = TechDefectInformationRequest::where('tech_defect_id', $locked->id)
                ->whereKey($data['information_request_id'])->lockForUpdate()->firstOrFail();
            abort_unless($informationRequest->status === 'Pending' && blank($informationRequest->response_text), 422, 'This information request has already been answered.');
            $informationRequest->update([
                'responded_by' => auth()->id(),
                'response_text' => $data['information_response'],
                'responded_at' => now(),
                'status' => 'Responded',
            ]);
            $locked->update(['information_response' => $data['information_response'], 'information_responded_by' => auth()->id(), 'information_responded_at' => now()]);
            $this->recordAudit($locked, 'information_responded', $locked->status, $locked->status, ['information_request_id' => $informationRequest->id, 'information_response' => $data['information_response']], 'Vessel personnel responded to the information request.');
        });
        $this->notifyTechnicalTeam($report->fresh(), 'Additional information received', "A response was added to {$report->report_code}.", 'information');
        return back()->with('success', 'Additional information submitted.');
    }

    protected function startRepair(TechDefect $report)
    {
        abort_unless($this->canPerformAction(auth()->user(), $report), 403, 'Only the assigned technical PIC or an authorized technical supervisor can start corrective action.');
        DB::transaction(function () use ($report): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_FOR_ACTION, 422, 'Corrective action can start only after technical assessment and assignment.');
            abort_unless($locked->assigned_to_user_id && $locked->target_completion_date, 422, 'Assign a technician/PIC and target completion date before starting repair.');
            $changes = ['status' => self::STATUS_ONGOING, 'progress_percent' => max(1, (int) $locked->progress_percent), 'progress_updated_by' => auth()->id(), 'progress_updated_at' => now()];
            if ($locked->operational_impact === 'Stopped' && ! $locked->downtime_started_at) $changes['downtime_started_at'] = $locked->created_at ?? now();
            $locked->update($changes);
            $this->recordAudit($locked, 'corrective_action_started', self::STATUS_FOR_ACTION, self::STATUS_ONGOING, ['progress_percent' => $changes['progress_percent']], 'Assigned corrective action started.');
        });
        return back()->with('success', 'Corrective action started successfully.');
    }

    protected function updateProgress(Request $request, TechDefect $report)
    {
        abort_unless($this->canPerformAction(auth()->user(), $report), 403, 'Only the assigned technical PIC or an authorized technical supervisor can update progress.');
        $data = $request->validate(['progress_percent' => 'required|integer|min:1|max:100', 'progress_notes' => 'required|string|max:3000']);
        DB::transaction(function () use ($report, $data): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_ONGOING, 422, 'Progress can be updated only while corrective action is ongoing.');
            abort_if((int) $locked->progress_percent >= 100, 422, 'Corrective action progress is already complete and can no longer be updated.');
            abort_unless((int) $data['progress_percent'] >= (int) $locked->progress_percent, 422, 'Progress cannot be reduced. Reopen the issue if rework is required.');
            $locked->update([...$data, 'progress_updated_by' => auth()->id(), 'progress_updated_at' => now()]);
            $this->recordAudit($locked, 'progress_updated', $locked->status, $locked->status, $data, "Corrective action progress updated to {$data['progress_percent']}%.");
        });
        return back()->with('success', 'Corrective action progress updated.');
    }

    protected function addThirdPartySupport(Request $request, TechDefect $report)
    {
        abort_unless($this->canManageThirdPartySupport(auth()->user(), $report), 403, 'Only authorized Technical Department personnel or a manager can assign third-party support.');
        $data = $request->validate(['vendor_name' => 'required|string|max:255', 'contact_person' => 'nullable|string|max:255', 'contact_number' => 'nullable|string|max:50', 'reason_for_support' => 'required|string|max:255', 'spares_required' => ['required', Rule::in(self::YES_NO)], 'tools_required' => 'required|string|max:255', 'expected_completion_date' => 'required|date|after_or_equal:today', 'quoted_cost' => 'nullable|numeric|min:0|max:9999999999']);
        DB::transaction(function () use ($report, $data): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_ONGOING, 422, 'Third-party support can only be requested for an ongoing repair.');
            if ($locked->supports()->whereRaw('LOWER(status) != ?', ['done'])->exists()) {
                throw ValidationException::withMessages([
                    'support' => 'Complete the active third-party support before assigning another provider.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            if ((int) $locked->progress_percent >= 100) {
                throw ValidationException::withMessages([
                    'support' => 'Third-party support must be assigned before corrective action progress reaches 100%.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            $support = ThirdPartySupport::create(['tech_defect_id' => $locked->id, ...$data, 'status' => 'Ongoing', 'created_by' => auth()->id()]);
            $locked->update(['third_party_required' => 'Yes']);
            $this->recordAudit($locked, 'support_requested', self::STATUS_ONGOING, self::STATUS_ONGOING, ['support_id' => $support->id, ...$data], 'Third-party support requested while corrective action remains ongoing.');
        });
        return back()->with('success', 'Third-party support added successfully.');
    }

    protected function markSupportAsDone(Request $request, TechDefect $report, int $supportId)
    {
        abort_unless($this->canManageThirdPartySupport(auth()->user(), $report), 403, 'Only authorized Technical Department personnel or a manager can complete third-party support.');
        $data = $request->validate(['actual_cost' => 'required|numeric|min:0|max:9999999999']);
        DB::transaction(function () use ($report, $supportId, $data): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_ONGOING, 422, 'Support can be completed only while corrective action is ongoing.');
            $support = ThirdPartySupport::where('tech_defect_id', $locked->id)->whereKey($supportId)->lockForUpdate()->firstOrFail();
            abort_if(strcasecmp($support->status, 'Done') === 0, 422, 'This support work is already completed.');
            $support->update(['status' => 'Done', 'actual_cost' => $data['actual_cost'], 'completed_by' => auth()->id(), 'completed_at' => now()]);
            $this->recordAudit($locked, 'support_completed', self::STATUS_ONGOING, self::STATUS_ONGOING, ['support_id' => $support->id, 'actual_cost' => $data['actual_cost']], 'Third-party support marked done.');
        });
        return back()->with('success', 'Support marked as done.');
    }

    protected function submitForVerification(TechDefect $report)
    {
        $this->authorizeDefectAction('tech_defects.submit', $report);
        $locked = DB::transaction(function () use ($report): TechDefect {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== self::STATUS_ONGOING) {
                throw ValidationException::withMessages([
                    'verification' => 'Only an ongoing repair can be submitted for verification.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            if ($locked->supports()->whereRaw('LOWER(status) != ?', ['done'])->exists()) {
                throw ValidationException::withMessages([
                    'verification' => 'Complete all third-party support work before submitting for verification.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            if ((int) $locked->progress_percent !== 100) {
                throw ValidationException::withMessages([
                    'verification' => 'Update corrective action progress to 100% before submitting for verification.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            $missing = collect(['root_cause' => 'root cause', 'corrective_action' => 'corrective action'])->filter(fn ($label, $field) => blank($locked->{$field}));
            if (in_array($locked->severity_level, ['Major', 'Critical'], true) && blank($locked->preventive_action)) $missing->put('preventive_action', 'preventive action');
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages(['closeout' => 'Complete the '.implode(', ', $missing->values()->all()).' before submission.'])
                    ->redirectTo(route('tech-defects.repair-closeout', $locked));
            }
            if (! $locked->attachments()->where('category', 'After Repair')->where('mime_type', 'like', 'image/%')->exists()) {
                throw ValidationException::withMessages([
                    'attachment' => 'Upload at least one repair completion photo before submitting for verification.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            $end = $locked->downtime_started_at ? ($locked->downtime_ended_at ?? now()) : null;
            $hours = $locked->downtime_started_at ? round($locked->downtime_started_at->diffInMinutes($end) / 60, 2) : null;
            $locked->update(['status' => self::STATUS_FOR_VERIFICATION, 'repair_completed_by' => auth()->id(), 'repair_completed_at' => now(), 'downtime_ended_at' => $end, 'total_downtime_hours' => $hours]);
            $this->recordAudit($locked, 'repair_submitted', self::STATUS_ONGOING, self::STATUS_FOR_VERIFICATION, [], 'Repair submitted for independent manager verification.');
            return $locked;
        });
        $this->notifyManagers($locked, 'Repair awaiting verification', "{$locked->report_code} is ready for verification.", 'verification');
        return back()->with('success', 'Repair submitted for manager verification.');
    }

    protected function verifyResolution(Request $request, TechDefect $report)
    {
        abort_unless($this->canVerifyDefects(auth()->user(), $report), 403, 'Technical Defects approval authority is required.');
        $data = $request->validate(['verification_remarks' => 'required|string|max:2000']);
        $locked = DB::transaction(function () use ($report, $data): TechDefect {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== self::STATUS_FOR_VERIFICATION) {
                throw ValidationException::withMessages([
                    'verification' => 'Only submitted repairs can be verified.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            if ($locked->verified_at) {
                throw ValidationException::withMessages([
                    'verification' => 'This resolution is already verified. Confirm completion or reopen the issue.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            if ((int) $locked->repair_completed_by === (int) auth()->id()) {
                throw ValidationException::withMessages([
                    'verification' => 'Independent verification is required. The repair submitter cannot verify the same resolution; ask another authorized Vessel Manager, Chief Engineer, or Operations approver to verify it.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            $locked->update(['verified_by' => auth()->id(), 'verified_at' => now(), 'verification_remarks' => $data['verification_remarks']]);
            $this->recordAudit($locked, 'resolution_verified', self::STATUS_FOR_VERIFICATION, self::STATUS_FOR_VERIFICATION, [], 'Resolution evidence was independently verified.');
            return $locked;
        });
        $this->notifyReviewers($locked, 'Resolution verified', "{$locked->report_code} is ready for final closure.", 'verification');
        return back()->with('success', 'Resolution verified. The report is ready for final completion.');
    }

    protected function confirmCompletion(Request $request, TechDefect $report)
    {
        abort_unless($this->canVerifyDefects(auth()->user(), $report), 403, 'Technical Defects approval authority is required.');
        $data = $request->validate([
            'completion_cost' => 'required|numeric|min:0|max:9999999999',
        ]);
        $locked = DB::transaction(function () use ($report, $data): TechDefect {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_FOR_VERIFICATION, 422, 'Only verified resolutions can be completed.');
            abort_unless($locked->verified_by && $locked->verified_at, 422, 'Verify the resolution before confirming completion.');
            abort_unless(
                $locked->attachments()->where('category', 'After Repair')->where('mime_type', 'like', 'image/%')->exists(),
                422,
                'A repair completion photo is required before closing the report.'
            );
            $locked->update(['status' => self::STATUS_CLOSED, 'date_completed' => today(), 'completion_cost' => $data['completion_cost'], 'closed_by' => auth()->id(), 'closed_at' => now()]);
            $this->recordAudit($locked, 'report_closed', self::STATUS_FOR_VERIFICATION, self::STATUS_CLOSED, ['completion_cost' => $data['completion_cost']], 'Verified technical defect report closed.');
            return $locked;
        });
        $this->notifyParticipants($locked, 'Technical defect closed', "{$locked->report_code} was verified and closed.", 'completed');
        return back()->with('success', 'Report confirmed complete and closed.');
    }

    protected function reopenIssue(Request $request, TechDefect $report)
    {
        abort_unless($this->canVerifyDefects(auth()->user(), $report), 403, 'Technical Defects approval authority is required.');
        $data = $request->validate(['verification_remarks' => 'required|string|max:2000']);
        $locked = DB::transaction(function () use ($report, $data): TechDefect {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_FOR_VERIFICATION, 422, 'Only submitted repairs can be returned.');
            $locked->update(['status' => self::STATUS_ONGOING, 'verification_remarks' => $data['verification_remarks'], 'repair_completed_by' => null, 'repair_completed_at' => null, 'verified_by' => null, 'verified_at' => null, 'progress_percent' => 90, 'downtime_ended_at' => null, 'total_downtime_hours' => null]);
            $this->recordAudit($locked, 'issue_reopened', self::STATUS_FOR_VERIFICATION, self::STATUS_ONGOING, ['verification_remarks' => $data['verification_remarks'], 'progress_percent' => 90], 'Resolution rejected and issue reopened for corrective work.');
            return $locked;
        });
        $this->notifyParticipants($locked, 'Repair returned for correction', "{$locked->report_code}: {$data['verification_remarks']}", 'returned');
        return back()->with('success', 'Repair returned to the assigned team.');
    }

    protected function updateRepairCloseout(Request $request, TechDefect $report)
    {
        $this->authorizeDefectAction('tech_defects.update_closeout', $report);
        $data = $request->validate([
            'downtime_started_at' => 'nullable|date',
            'downtime_ended_at' => 'nullable|date|after_or_equal:downtime_started_at',
            'root_cause' => 'nullable|string|max:5000',
            'corrective_action' => 'nullable|string|max:5000',
            'preventive_action' => 'nullable|string|max:5000',
        ]);
        foreach (['root_cause', 'corrective_action', 'preventive_action'] as $field) if (filled($data[$field] ?? null)) $data[$field] = mb_strtoupper(trim($data[$field]));
        $data['total_downtime_hours'] = ! empty($data['downtime_started_at']) && ! empty($data['downtime_ended_at'])
            ? round(Carbon::parse($data['downtime_started_at'])->diffInMinutes(Carbon::parse($data['downtime_ended_at'])) / 60, 2) : null;
        DB::transaction(function () use ($report, $data): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_ONGOING, 422, 'Repair close-out can be changed only while corrective work is ongoing.');
            if ((int) $locked->progress_percent < 100) {
                throw ValidationException::withMessages([
                    'closeout' => 'Complete corrective action progress to 100% before updating repair close-out.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            if ($locked->supports()->whereRaw('LOWER(status) != ?', ['done'])->exists()) {
                throw ValidationException::withMessages([
                    'closeout' => 'Complete the active third-party support before updating repair close-out.',
                ])->redirectTo(route('tech-defects.show', $locked));
            }
            $before = $locked->only(array_keys($data));
            $locked->update($data);
            $changes = collect($data)->filter(fn ($value, $key) => ($before[$key] ?? null) != $value)->all();
            $this->recordAudit($locked, 'repair_closeout_updated', $locked->status, $locked->status, $changes, 'Repair close-out information updated.');
        });
        return redirect()->route('tech-defects.show', $report)->with('success', 'Repair close-out updated successfully.');
    }

    public function storeAttachment(Request $request, $id)
    {
        $report = TechDefect::findOrFail($id);
        $this->authorizeTechDefectAccess($report);
        abort_if($report->trashed() || $report->status === self::STATUS_CLOSED, 422, 'Attachments cannot be added to a closed or archived report.');

        $category = (string) $request->input('category');
        if ($category === 'Before Repair') {
            abort_unless($report->status === self::STATUS_NEW_REPORT, 422, 'The affected defect photo can only be uploaded while the report is new.');
            abort_unless($this->canSubmitReview(auth()->user(), $report), 403, 'Only authorized vessel personnel can upload the affected defect photo.');
            $attachmentRules = 'required|file|mimes:jpg,jpeg,png,webp|max:10240';
        } elseif ($category === 'After Repair') {
            abort_unless($report->status === self::STATUS_ONGOING, 422, 'The repair completion photo can only be uploaded during ongoing corrective action.');
            abort_unless((int) $report->progress_percent === 100, 422, 'Complete corrective action progress to 100% before uploading the repair completion photo.');
            $this->authorizeDefectAction('tech_defects.upload_evidence', $report);
            $attachmentRules = 'required|file|mimes:jpg,jpeg,png,webp|max:10240';
        } else {
            abort_if($category === 'Checklist', 422, 'The checklist document can only be uploaded when creating a new report.');
            abort_unless(in_array($report->status, [self::STATUS_ONGOING, self::STATUS_FOR_VERIFICATION], true), 422, 'Supporting documents can only be uploaded during repair close-out or verification.');
            $this->authorizeDefectAction('tech_defects.upload_evidence', $report);
            $attachmentRules = 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx|max:10240';
        }

        $data = $request->validate([
            'category' => ['required', Rule::in(self::ATTACHMENT_CATEGORIES)],
            'attachment' => $attachmentRules,
        ]);
        $file = $data['attachment'];
        $path = $file->store("tech-defects/{$report->id}", 'local');
        try {
            $attachment = $report->attachments()->create(['uploaded_by' => auth()->id(), 'category' => $data['category'], 'original_name' => $file->getClientOriginalName(), 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
            $description = match ($attachment->category) {
                'Before Repair' => 'Affected defect photo uploaded before management review.',
                'After Repair' => 'Repair completion photo uploaded for final verification.',
                default => 'Supporting repair document uploaded.',
            };
            $this->recordAudit($report, 'attachment_uploaded', $report->status, $report->status, ['attachment_id' => $attachment->id, 'category' => $attachment->category, 'name' => $attachment->original_name], $description);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        return back()->with('success', 'Attachment uploaded securely.');
    }

    public function attachment($id, TechDefectAttachment $attachment)
    {
        $report = TechDefect::findOrFail($id);
        $this->authorizeTechDefectAccess($report);
        abort_unless($attachment->tech_defect_id === $report->id && Storage::disk('local')->exists($attachment->path), 404);
        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function exportPdf($id)
    {
        $report = TechDefect::with([
            'vessel',
            'reporter.position',
            'assignee.position',
            'reviewSubmitter',
            'reviewer',
            'assessor',
            'actionAssigner',
            'progressUpdater',
            'repairCompleter',
            'verifier',
            'closer',
            'attachments.uploader',
            'informationRequests.requester',
            'informationRequests.responder',
            'supports.creator',
            'supports.completer',
            'audits.user',
        ])->findOrFail($id);
        $this->authorizeTechDefectAccess($report);
        $pdfImageData = $report->attachments
            ->whereIn('category', ['Before Repair', 'After Repair'])
            ->filter(fn (TechDefectAttachment $attachment) => str_starts_with((string) $attachment->mime_type, 'image/'))
            ->mapWithKeys(fn (TechDefectAttachment $attachment) => [$attachment->id => $this->pdfImageDataUri($attachment)])
            ->filter()
            ->all();

        return Pdf::loadView('shipping.tech_defects.reports.individual', compact('report', 'pdfImageData'))
            ->setPaper('a4')
            ->download($report->report_code.'.pdf');
    }

    public function exportSummaryPdf(Request $request)
    {
        abort_unless($this->canManageAllDefects(auth()->user()) && $this->vesselAccess->canAccessAllVessels(auth()->user()), 403);
        [$reports, $filters] = $this->reportQuery($request);
        return Pdf::loadView('shipping.tech_defects.reports.summary', compact('reports', 'filters'))->setPaper('a4', 'landscape')->download('technical-defects-'.$filters['month'].'.pdf');
    }

    public function exportSummaryCsv(Request $request)
    {
        abort_unless($this->canManageAllDefects(auth()->user()) && $this->vesselAccess->canAccessAllVessels(auth()->user()), 403);
        [$reports, $filters] = $this->reportQuery($request);
        return response()->streamDownload(function () use ($reports, $filters): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM keeps vessel names and report text readable when opened in Microsoft Excel.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['VILLA SHIPPING LINES - TECHNICAL & DEFECT REPORT']);
            fputcsv($out, ['Selected period', $filters['date_from'].' to '.$filters['date_to']]);
            fputcsv($out, ['Total reports', $reports->count()]);
            fputcsv($out, []);
            fputcsv($out, [
                'Report ID', 'Vessel', 'Date Identified', 'Created Date & Time', 'Port / Location',
                'Reported By', 'System Affected', 'Defect Description', 'Severity', 'Operational Impact',
                'Temporary Repair', 'Status', 'Assigned PIC', 'Target Completion', 'Progress %',
                'Downtime Hours', 'Root Cause', 'Corrective Action', 'Preventive Action',
                'Date Completed', 'Report Completion Cost', 'Third-Party Actual Cost', 'Total Recorded Cost', 'Remarks',
            ]);
            foreach ($reports as $report) {
                $row = [
                    $report->report_code,
                    $report->vessel?->vessel_name,
                    $report->date_identified?->toDateString(),
                    $report->created_at?->format('Y-m-d h:i A'),
                    $report->port_location,
                    $report->reported_by,
                    $report->system_affected,
                    $report->defect_description,
                    $report->severity_level,
                    $report->operational_impact,
                    $report->temporary_repair,
                    $report->status,
                    trim(($report->assignee?->name ?? '').' '.($report->assignee?->lastname ?? '')),
                    $report->target_completion_date?->toDateString(),
                    $report->progress_percent,
                    $report->total_downtime_hours,
                    $report->root_cause,
                    $report->corrective_action,
                    $report->preventive_action,
                    $report->date_completed?->toDateString(),
                    $report->completion_cost === null ? null : (float) $report->completion_cost,
                    round((float) $report->supports->sum('actual_cost'), 2),
                    round((float) $report->completion_cost + (float) $report->supports->sum('actual_cost'), 2),
                    $report->remarks,
                ];
                fputcsv($out, array_map(fn ($value) => is_string($value) && preg_match('/^[=+\-@]/', ltrim($value)) ? "'".$value : $value, $row));
            }
            fclose($out);
        }, 'technical-defects-'.$filters['period'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportSummaryExcel(Request $request, SimpleXlsxWriter $xlsx)
    {
        abort_unless($this->canManageAllDefects(auth()->user()) && $this->vesselAccess->canAccessAllVessels(auth()->user()), 403);
        [$reports, $filters] = $this->reportQuery($request);
        $headers = [
            'Report ID', 'Vessel', 'Date Identified', 'Created Date & Time', 'Port / Location',
            'Reported By', 'System Affected', 'Defect Description', 'Severity', 'Operational Impact',
            'Temporary Repair', 'Status', 'Assigned PIC', 'Target Completion', 'Progress %',
            'Downtime Hours', 'Root Cause', 'Corrective Action', 'Preventive Action',
            'Date Completed', 'Report Completion Cost', 'Third-Party Actual Cost', 'Total Recorded Cost', 'Remarks',
        ];
        $rows = $reports->map(fn (TechDefect $report) => [
            $report->report_code,
            $report->vessel?->vessel_name,
            $report->date_identified?->toDateString(),
            $report->created_at?->format('Y-m-d h:i A'),
            $report->port_location,
            $report->reported_by,
            $report->system_affected,
            $report->defect_description,
            $report->severity_level,
            $report->operational_impact,
            $report->temporary_repair,
            $report->status,
            trim(($report->assignee?->name ?? '').' '.($report->assignee?->lastname ?? '')),
            $report->target_completion_date?->toDateString(),
            $report->progress_percent,
            $report->total_downtime_hours === null ? null : (float) $report->total_downtime_hours,
            $report->root_cause,
            $report->corrective_action,
            $report->preventive_action,
            $report->date_completed?->toDateString(),
            $report->completion_cost === null ? null : (float) $report->completion_cost,
            round((float) $report->supports->sum('actual_cost'), 2),
            round((float) $report->completion_cost + (float) $report->supports->sum('actual_cost'), 2),
            $report->remarks,
        ])->all();
        $contents = $xlsx->make(
            'VILLA SHIPPING LINES - TECHNICAL & DEFECT REPORT',
            [
                ['Selected period', $filters['date_from'].' to '.$filters['date_to']],
                ['Total reports', (string) $reports->count()],
                ['Generated', now()->format('M d, Y h:i A')],
            ],
            $headers,
            $rows,
            [16, 22, 15, 21, 21, 22, 20, 42, 13, 20, 17, 18, 22, 18, 12, 17, 28, 34, 34, 17, 20, 20, 20, 34],
        );
        $filename = 'technical-defects-'.$filters['period'].'.xlsx';

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    protected function reportQuery(Request $request): array
    {
        $data = $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'date_from' => 'nullable|required_with:date_to|date',
            'date_to' => 'nullable|required_with:date_from|date|after_or_equal:date_from',
            'vessel_id' => 'nullable|exists:vessels,id',
        ]);

        if (! empty($data['date_from']) && ! empty($data['date_to'])) {
            $start = Carbon::parse($data['date_from'])->startOfDay();
            $end = Carbon::parse($data['date_to'])->endOfDay();
            $month = null;
            $period = $start->toDateString().'-to-'.$end->toDateString();
        } else {
            $month = $data['month'] ?? now()->format('Y-m');
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $period = $month;
        }

        $reports = TechDefect::with(['vessel', 'assignee', 'supports'])
            ->whereBetween('date_identified', [$start->toDateString(), $end->toDateString()])
            ->when($data['vessel_id'] ?? null, fn ($q, $id) => $q->where('vessel_id', $id))
            ->orderBy('date_identified')
            ->orderBy('id')
            ->get();

        return [$reports, [
            'month' => $month,
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
            'period' => $period,
            'vessel_id' => $data['vessel_id'] ?? null,
        ]];
    }

    protected function updateReportDetails(Request $request, TechDefect $report)
    {
        abort_unless($report->status === self::STATUS_NEW_REPORT, 422, 'The report can be edited only before management review.');
        abort_unless($this->canEditReport(auth()->user(), $report), 403, 'You cannot edit this report.');
        $data = $this->validateTechDefect($request, false);
        $this->authorizeVesselSelection(auth()->user(), (int) $data['vessel_id']);
        $normalized = $this->normalizeTechDefectData($data);
        $oldAssignee = $report->assigned_to_user_id;
        DB::transaction(function () use ($report, $normalized): void {
            $locked = TechDefect::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === self::STATUS_NEW_REPORT, 422, 'This report can no longer be edited.');
            $before = $locked->only(array_keys($normalized));
            $locked->update($normalized);
            $changes = collect($normalized)->filter(fn ($value, $key) => ($before[$key] ?? null) != $value)->all();
            $this->recordAudit($locked, 'details_updated', $locked->status, $locked->status, $changes, 'Report details updated.');
        });
        if ((int) $oldAssignee !== (int) ($normalized['assigned_to_user_id'] ?? 0)) $this->notifyAssignee($report->fresh(), 'Technical defect assigned to you', "You are assigned to {$report->report_code}.", 'assignment');
        return redirect()->route('tech-defects.edit', $report)->with('success', 'Report updated successfully.');
    }

    protected function validateTechDefect(Request $request, bool $requireAssignment = false): array
    {
        $data = $request->validate([
            'vessel_id' => 'required|exists:vessels,id', 'date_identified' => 'required|date|before_or_equal:today',
            'port_location' => 'nullable|string|max:255',
            'reported_by_user_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', true))],
            'assigned_to_user_id' => [$requireAssignment ? 'required' : 'nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', true))],
            'target_completion_date' => [$requireAssignment ? 'required' : 'nullable', 'date', 'after_or_equal:date_identified'],
            'system_affected' => ['required', Rule::in(self::SYSTEMS)], 'defect_description' => 'required|string|max:5000',
            'initial_cause' => 'nullable|string|max:5000', 'severity_level' => ['required', Rule::in(self::SEVERITIES)],
            'operational_impact' => ['required', Rule::in(self::IMPACTS)], 'temporary_repair' => ['required', Rule::in(self::YES_NO)],
            'remarks' => 'nullable|string|max:5000',
        ]);
        $reporter = $this->getReportingUsers()->firstWhere('id', (int) $data['reported_by_user_id']);
        if (! $reporter) {
            throw ValidationException::withMessages([
                'reported_by_user_id' => 'Reported by must be an active Marine Operations or Technical Department user.',
            ]);
        }
        if (! empty($data['assigned_to_user_id'])) abort_unless($this->getTechnicalUsers()->contains('id', (int) $data['assigned_to_user_id']), 422, 'Assigned PIC must have an active technical position or corrective-action permission.');
        $data['reported_by'] = mb_strtoupper(trim($reporter->name.' '.$reporter->lastname));
        return $data;
    }

    protected function normalizeTechDefectData(array $data): array
    {
        foreach (['port_location', 'reported_by', 'defect_description', 'initial_cause', 'root_cause', 'corrective_action', 'preventive_action', 'remarks'] as $field) {
            if (array_key_exists($field, $data) && filled($data[$field])) $data[$field] = mb_strtoupper(trim((string) $data[$field]));
        }
        if (! empty($data['downtime_started_at']) && ! empty($data['downtime_ended_at'])) {
            $data['total_downtime_hours'] = round(Carbon::parse($data['downtime_started_at'])->diffInMinutes(Carbon::parse($data['downtime_ended_at'])) / 60, 2);
        } elseif (array_key_exists('downtime_started_at', $data)) {
            $data['total_downtime_hours'] = null;
        }
        return $data;
    }

    protected function notifyOnCreation(TechDefect $report): void
    {
        $this->notifyAssignee($report, 'New technical defect assignment', "{$report->report_code} was assigned to you.", 'assignment');
        if ($report->severity_level === 'Critical') $this->notifyManagers($report, 'Critical technical defect reported', "{$report->report_code} on {$report->vessel?->vessel_name} requires immediate attention.", 'critical');
    }

    protected function notifyManagers(TechDefect $report, string $title, string $message, string $type): void
    {
        $this->getShippingUsers()->filter(fn (User $user) => $this->canManageAllDefects($user))->each(fn (User $user) => $user->notify(new TechDefectNotification($this->notificationPayload($report, $title, $message, $type))));
    }

    protected function notifyReviewers(TechDefect $report, string $title, string $message, string $type): void
    {
        $this->getShippingUsers()->filter(fn (User $user) => $this->canReviewDefects($user, $report))->each(fn (User $user) => $user->notify(new TechDefectNotification($this->notificationPayload($report, $title, $message, $type))));
    }

    protected function notifyTechnicalTeam(TechDefect $report, string $title, string $message, string $type): void
    {
        $this->getShippingUsers()->filter(fn (User $user) => $this->canAssessDefects($user, $report))->each(fn (User $user) => $user->notify(new TechDefectNotification($this->notificationPayload($report, $title, $message, $type))));
    }

    protected function notifyReporter(TechDefect $report, string $title, string $message, string $type): void
    {
        $report->reporter?->notify(new TechDefectNotification($this->notificationPayload($report, $title, $message, $type)));
    }

    protected function notifyAssignee(TechDefect $report, string $title, string $message, string $type): void
    {
        $report->assignee?->notify(new TechDefectNotification($this->notificationPayload($report, $title, $message, $type)));
    }

    protected function notifyParticipants(TechDefect $report, string $title, string $message, string $type): void
    {
        User::whereIn('id', array_values(array_unique(array_filter([$report->assigned_to_user_id, $report->reported_by_user_id]))))->get()->each(fn (User $user) => $user->notify(new TechDefectNotification($this->notificationPayload($report, $title, $message, $type))));
    }

    protected function notificationPayload(TechDefect $report, string $title, string $message, string $type): array
    {
        return ['title' => $title, 'message' => $message, 'type' => $type, 'tech_defect_id' => $report->id, 'report_code' => $report->report_code, 'url' => route('tech-defects.show', $report, false)];
    }

    protected function getShippingUsers(): Collection
    {
        return User::where('status', true)->whereHas('division', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['villa shipping lines']))
            ->orderBy('name')->orderBy('lastname')->get();
    }

    protected function getReportingUsers(): Collection
    {
        return User::query()
            ->where('status', true)
            ->whereHas('division', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['villa shipping lines']))
            ->whereHas('department', fn ($query) => $query->whereRaw('LOWER(TRIM(name)) IN (?, ?)', ['marine operations', 'technical department']))
            ->with(['department', 'position'])
            ->orderBy('name')->orderBy('lastname')->get();
    }

    protected function getTechnicalUsers(): Collection
    {
        return $this->getShippingUsers()->filter(function (User $user): bool {
            $user->loadMissing('position.permissions');
            return (bool) $user->position?->permissions->contains('slug', 'tech_defects.perform_action');
        })
            ->sortBy(fn (User $user) => strtolower(($user->position?->name ?? '').' '.$user->name.' '.$user->lastname))->values();
    }

    protected function getAccessibleVessels(User $user): Collection
    {
        return $this->vesselAccess->scopeAccessible(Vessel::query(), $user)->orderBy('vessel_name')->get();
    }

    protected function authorizeModuleAccess(User $user): void
    {
        abort_unless($user->isExecutiveViewer() || $this->canManageAllDefects($user) || $user->role === 'captain' || $user->hasPermission('tech_defects.view_assigned'), 403, 'Technical defect access is not assigned to this account.');
    }

    protected function authorizeVesselSelection(User $user, int $vesselId): void
    {
        $this->authorizeModuleAccess($user);
        $this->vesselAccess->authorize($user, $vesselId);
    }

    protected function authorizeTechDefectAccess(TechDefect $report): void
    {
        $user = auth()->user();
        $this->authorizeModuleAccess($user);
        abort_unless(
            $this->vesselAccess->canAccess($user, (int) $report->vessel_id)
                || (int) $report->assigned_to_user_id === (int) $user->id,
            403,
            'This technical defect is not assigned to your account.'
        );
    }

    protected function authorizeDefectAction(string $permission, TechDefect $report): void
    {
        abort_unless($this->canPerformPermission(auth()->user(), $report, $permission), 403, 'Your position or access profile does not allow this action.');
    }

    protected function canCreateDefects(User $user): bool
    {
        return ! $user->isExecutiveViewer() && ($user->is_admin || $user->role === 'admin' || $user->hasPermission('tech_defects.create') || $user->role === 'captain');
    }

    protected function canEditReport(User $user, TechDefect $report): bool
    {
        if ($user->isExecutiveViewer()) return false;
        if ($report->status !== self::STATUS_NEW_REPORT) return false;
        if ($user->is_admin || $user->role === 'admin' || $user->hasPermission('tech_defects.view_company')) return true;
        return (int) $report->reported_by_user_id === (int) $user->id
            || $this->vesselAccess->canAccess($user, (int) $report->vessel_id);
    }

    protected function canSubmitReview(User $user, TechDefect $report): bool
    {
        if ($user->isExecutiveViewer()) return false;
        if ($user->is_admin || $user->role === 'admin') return true;
        $hasVesselScope = (int) $report->reported_by_user_id === (int) $user->id
            || $this->vesselAccess->canAccess($user, (int) $report->vessel_id);
        return $hasVesselScope && ($user->role === 'captain' || $user->hasPermission('tech_defects.submit_review'));
    }

    protected function canReviewDefects(User $user, TechDefect $report): bool
    {
        if ($user->isExecutiveViewer()) return false;
        if ($user->is_admin || $user->role === 'admin') return true;
        $user->loadMissing('position');
        $hasManagerPosition = strtolower(trim((string) $user->position?->legacy_role)) === 'manager';
        return $hasManagerPosition
            && $this->vesselAccess->canAccess($user, (int) $report->vessel_id)
            && $user->hasPermission('tech_defects.review')
            && $user->hasApprovalAuthority('technical_defects', $user->division_id);
    }

    protected function canAssessDefects(User $user, TechDefect $report): bool
    {
        return ! $user->isExecutiveViewer()
            && $this->vesselAccess->canAccess($user, (int) $report->vessel_id)
            && ($user->is_admin || $user->role === 'admin' || $user->hasPermission('tech_defects.assess'));
    }

    protected function canPerformPermission(User $user, TechDefect $report, string $permission): bool
    {
        if ($user->isExecutiveViewer()) return false;
        if ($user->is_admin || $user->role === 'admin') return true;

        $user->loadMissing('position');
        $isCaptain = $user->role === 'captain'
            || strtolower(trim((string) $user->position?->legacy_role)) === 'captain'
            || strtolower(trim((string) $user->position?->code)) === 'vessel-captain';
        if ($isCaptain && in_array($permission, [
            'tech_defects.perform_action',
            'tech_defects.update_closeout',
            'tech_defects.upload_evidence',
            'tech_defects.submit',
        ], true)) return false;

        if (! $user->hasPermission($permission)) return false;
        $hasRecordScope = $this->vesselAccess->canAccess($user, (int) $report->vessel_id)
            || (int) $report->assigned_to_user_id === (int) $user->id;
        return $hasRecordScope && ($user->hasPermission('tech_defects.view_company')
            || (int) $report->assigned_to_user_id === (int) $user->id
            || $user->hasPermission('tech_defects.view_assigned'));
    }

    protected function canPerformAction(User $user, TechDefect $report): bool
    {
        return $this->canPerformPermission($user, $report, 'tech_defects.perform_action');
    }

    protected function canManageThirdPartySupport(User $user, TechDefect $report): bool
    {
        if ($user->isExecutiveViewer()) return false;
        if ($user->is_admin || $user->role === 'admin') return true;

        $user->loadMissing(['position', 'department']);
        $hasRecordScope = $this->vesselAccess->canAccess($user, (int) $report->vessel_id)
            || (int) $report->assigned_to_user_id === (int) $user->id;
        if (! $hasRecordScope) return false;

        $positionRole = strtolower(trim((string) $user->position?->legacy_role));
        if ($positionRole === 'captain') return false;

        $isManager = $positionRole === 'manager';
        $isTechnicalDepartment = strtolower(trim((string) $user->department?->name)) === 'technical department';

        return $isManager || ($isTechnicalDepartment && $user->hasPermission('tech_defects.request_support'));
    }

    protected function canManageAllDefects(User $user): bool
    {
        return ! $user->isExecutiveViewer() && ($user->is_admin || $user->role === 'admin' || $user->hasPermission('tech_defects.view_company'));
    }

    protected function canViewAllDefects(User $user): bool
    {
        return $user->isExecutiveViewer()
            || $this->canManageAllDefects($user)
            || $user->hasPermission('tech_defects.review')
            || $user->hasPermission('tech_defects.assess');
    }

    protected function canDeleteDefects(User $user): bool
    {
        return ! $user->isExecutiveViewer() && ($user->is_admin || $user->role === 'admin' || $user->hasPermission('tech_defects.archive'));
    }

    protected function canVerifyDefects(User $user, TechDefect $report): bool
    {
        if ($user->isExecutiveViewer()) return false;
        if ($user->is_admin || $user->role === 'admin') return true;

        $user->loadMissing('position');
        $isCaptain = $user->role === 'captain'
            || strtolower(trim((string) $user->position?->legacy_role)) === 'captain'
            || strtolower(trim((string) $user->position?->code)) === 'vessel-captain';
        if ($isCaptain) return false;

        return $this->vesselAccess->canAccess($user, (int) $report->vessel_id)
            && $user->hasPermission('tech_defects.verify')
            && $user->hasApprovalAuthority('technical_defects', $user->division_id);
    }

    protected function pdfImageDataUri(TechDefectAttachment $attachment): ?string
    {
        if (! Storage::disk('local')->exists($attachment->path)) return null;
        $rawImage = Storage::disk('local')->get($attachment->path);
        $rawDataUri = 'data:'.($attachment->mime_type ?: 'image/jpeg').';base64,'.base64_encode($rawImage);

        $requiredGdFunctions = [
            'imagecreatefromstring', 'imagesx', 'imagesy', 'imagecreatetruecolor',
            'imagecolorallocate', 'imagefill', 'imagecopyresampled', 'imagedestroy',
        ];
        if (collect($requiredGdFunctions)->contains(fn (string $function) => ! function_exists($function))) {
            return $rawDataUri;
        }

        $source = @imagecreatefromstring($rawImage);
        if (! $source) return $rawDataUri;

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, 900 / max(1, $sourceWidth), 650 / max(1, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        ob_start();
        $outputMime = 'image/jpeg';
        if (function_exists('imagejpeg')) {
            \imagejpeg($canvas, null, 78);
        } elseif (function_exists('imagepng')) {
            $outputMime = 'image/png';
            \imagepng($canvas, null, 6);
        } else {
            ob_end_clean();
            imagedestroy($canvas);
            imagedestroy($source);
            return $rawDataUri;
        }
        $encoded = base64_encode((string) ob_get_clean());
        imagedestroy($canvas);
        imagedestroy($source);

        return 'data:'.$outputMime.';base64,'.$encoded;
    }

    protected function recordAudit(TechDefect $report, string $action, ?string $fromStatus, ?string $toStatus, array $changes = [], ?string $description = null, ?User $actor = null): void
    {
        TechDefectAudit::create(['tech_defect_id' => $report->id, 'user_id' => ($actor ?? auth()->user())?->id, 'action' => $action, 'from_status' => $fromStatus, 'to_status' => $toStatus, 'changes' => $changes ?: null, 'description' => $description]);
    }
}
