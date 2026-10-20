<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\JmvDailyProductionAttachment;
use App\Models\JmvDailyProductionAudit;
use App\Models\JmvDailyProductionLog;
use App\Models\User;
use App\Notifications\JmvDailyProductionNotification;
use App\Services\SimpleXlsxWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JmvDailyProductionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('jmv.operations.daily_production.view');
        $query = $this->filteredQuery($request);
        $summaryQuery = (clone $query)->where('status', JmvDailyProductionLog::STATUS_COMPLETED);
        $logs = $query->with(['creator:id,name,lastname', 'reviewer:id,name,lastname'])->latest('log_date')->latest('id')->paginate(15)->withQueryString();
        $summary = [
            'logs' => (clone $summaryQuery)->count(),
            'workers' => (int) (clone $summaryQuery)->sum('total_workers'),
            'soil' => (int) (clone $summaryQuery)->sum('soil_output'),
            'coal' => (int) (clone $summaryQuery)->sum('coal_output'),
            'fuel' => (float) (clone $summaryQuery)->sum('fuel_consumed'),
        ];
        $divisionId = $this->jmvDivisionId();
        $locations = $this->visibleQuery()->distinct()->orderBy('location')->pluck('location');
        $shifts = $this->visibleQuery()->distinct()->orderBy('shift')->pluck('shift');

        return view('jmv.operations.production.index', compact('logs', 'summary', 'locations', 'shifts') + [
            'canCreate' => $this->can('jmv.operations.daily_production.create'),
            'canExport' => $this->can('jmv.operations.daily_production.reports.view'),
            'canApprove' => $this->can('jmv.operations.daily_production.approve'),
            'statuses' => JmvDailyProductionLog::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('jmv.operations.daily_production.create');
        $divisionId = $this->jmvDivisionId();
        $data = $this->validated($request, $divisionId);
        $storedPaths = [];
        try {
            $log = DB::transaction(function () use ($request, $data, $divisionId, &$storedPaths): JmvDailyProductionLog {
                $log = JmvDailyProductionLog::create([...$data, 'division_id' => $divisionId, 'log_no' => $this->generateLogNo(), 'status' => JmvDailyProductionLog::STATUS_DRAFT, 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
                $this->storeAttachments($request, $log, $storedPaths);
                $this->audit($log, 'log_created', ['log_no' => $log->log_no]);
                return $log;
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route('jmv.operations.production.show', $log)->with('success', 'Daily production log saved as a draft. Review it, then submit it for approval.');
    }

    public function show(JmvDailyProductionLog $productionLog)
    {
        $this->authorizePermission('jmv.operations.daily_production.view');
        $this->ensureJmvLog($productionLog);
        $this->ensureVisibleLog($productionLog);
        $productionLog->load(['creator:id,name,lastname', 'updater:id,name,lastname', 'submitter:id,name,lastname', 'reviewer:id,name,lastname', 'attachments.uploader:id,name,lastname', 'audits.user:id,name,lastname']);
        return view('jmv.operations.production.show', [
            'log' => $productionLog,
            'canUpdate' => $this->canEditLog($productionLog),
            'canSubmit' => $this->canSubmitLog($productionLog),
            'canReview' => $this->canReviewLog($productionLog),
        ]);
    }

    public function edit(JmvDailyProductionLog $productionLog)
    {
        $this->authorizePermission('jmv.operations.daily_production.update');
        $this->ensureJmvLog($productionLog);
        $this->ensureVisibleLog($productionLog);
        abort_unless($this->canEditLog($productionLog), 422, 'Only your own Draft or For Correction log can be edited.');
        return view('jmv.operations.production.edit', ['log' => $productionLog]);
    }

    public function update(Request $request, JmvDailyProductionLog $productionLog)
    {
        $this->authorizePermission('jmv.operations.daily_production.update');
        $this->ensureJmvLog($productionLog);
        $this->ensureVisibleLog($productionLog);
        abort_unless($this->canEditLog($productionLog), 422, 'Only your own Draft or For Correction log can be edited.');
        $data = $this->validated($request, $this->jmvDivisionId(), $productionLog);
        $before = $productionLog->only(array_keys($data));
        $storedPaths = [];
        try {
            DB::transaction(function () use ($request, $productionLog, $data, $before, &$storedPaths): void {
                $productionLog->update([...$data, 'updated_by' => auth()->id()]);
                $this->storeAttachments($request, $productionLog, $storedPaths);
                $this->audit($productionLog, 'log_updated', ['before' => $before, 'after' => $productionLog->fresh()->only(array_keys($data))]);
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) Storage::disk('local')->delete($path);
            throw $exception;
        }
        return redirect()->route('jmv.operations.production.show', $productionLog)->with('success', 'Daily production draft updated successfully.');
    }

    public function submit(JmvDailyProductionLog $productionLog)
    {
        $this->authorizePermission('jmv.operations.daily_production.submit');
        $this->ensureJmvLog($productionLog);
        abort_unless($this->canSubmitLog($productionLog), 422, 'Only your own Draft or For Correction log can be submitted.');
        abort_unless($productionLog->attachments()->exists(), 422, 'Upload the supporting Daily Accomplishment Report before submitting for review.');

        DB::transaction(function () use ($productionLog): void {
            $log = JmvDailyProductionLog::whereKey($productionLog->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($log->status, [JmvDailyProductionLog::STATUS_DRAFT, JmvDailyProductionLog::STATUS_FOR_CORRECTION], true), 422, 'This log is no longer available for submission.');
            $previousStatus = $log->status;
            $log->update([
                'status' => JmvDailyProductionLog::STATUS_PENDING_REVIEW,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_remarks' => null,
                'updated_by' => auth()->id(),
            ]);
            $this->audit($log, $previousStatus === JmvDailyProductionLog::STATUS_FOR_CORRECTION ? 'log_resubmitted' : 'log_submitted', ['from' => $previousStatus, 'to' => JmvDailyProductionLog::STATUS_PENDING_REVIEW]);
        });

        $this->notifyApprovers($productionLog->fresh());
        return back()->with('success', 'Daily production log submitted and is now Pending Review.');
    }

    public function approve(Request $request, JmvDailyProductionLog $productionLog)
    {
        $this->authorizePermission('jmv.operations.daily_production.approve');
        $this->ensureJmvLog($productionLog);
        $data = $request->validate(['review_remarks' => 'nullable|string|max:2000']);
        abort_if((int) $productionLog->created_by === (int) auth()->id(), 422, 'You cannot approve a daily production log that you created.');

        DB::transaction(function () use ($productionLog, $data): void {
            $log = JmvDailyProductionLog::whereKey($productionLog->id)->lockForUpdate()->firstOrFail();
            abort_unless($log->status === JmvDailyProductionLog::STATUS_PENDING_REVIEW, 422, 'Only Pending Review logs can be approved.');
            abort_if((int) $log->created_by === (int) auth()->id(), 422, 'You cannot approve a daily production log that you created.');
            $log->update([
                'status' => JmvDailyProductionLog::STATUS_COMPLETED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_remarks' => $data['review_remarks'] ?? null,
                'completed_at' => now(),
                'updated_by' => auth()->id(),
            ]);
            $this->audit($log, 'log_approved', ['to' => JmvDailyProductionLog::STATUS_COMPLETED, 'remarks' => $data['review_remarks'] ?? null]);
        });

        $this->notifyCreator($productionLog->fresh(), 'Daily production log approved', "{$productionLog->log_no} was approved and is now Completed / Logged.");
        return back()->with('success', 'Daily production log approved and marked Completed / Logged.');
    }

    public function returnForCorrection(Request $request, JmvDailyProductionLog $productionLog)
    {
        $this->authorizePermission('jmv.operations.daily_production.approve');
        $this->ensureJmvLog($productionLog);
        $data = $request->validate(['review_remarks' => 'required|string|max:2000']);
        abort_if((int) $productionLog->created_by === (int) auth()->id(), 422, 'You cannot review a daily production log that you created.');

        DB::transaction(function () use ($productionLog, $data): void {
            $log = JmvDailyProductionLog::whereKey($productionLog->id)->lockForUpdate()->firstOrFail();
            abort_unless($log->status === JmvDailyProductionLog::STATUS_PENDING_REVIEW, 422, 'Only Pending Review logs can be returned for correction.');
            abort_if((int) $log->created_by === (int) auth()->id(), 422, 'You cannot review a daily production log that you created.');
            $log->update([
                'status' => JmvDailyProductionLog::STATUS_FOR_CORRECTION,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_remarks' => $data['review_remarks'],
                'revision_count' => $log->revision_count + 1,
                'updated_by' => auth()->id(),
            ]);
            $this->audit($log, 'log_returned_for_correction', ['to' => JmvDailyProductionLog::STATUS_FOR_CORRECTION, 'remarks' => $data['review_remarks']]);
        });

        $this->notifyCreator($productionLog->fresh(), 'Daily production log returned for correction', "{$productionLog->log_no} was returned: {$data['review_remarks']}");
        return back()->with('success', 'Daily production log returned to the Admin Staff for correction.');
    }

    public function attachment(JmvDailyProductionLog $productionLog, JmvDailyProductionAttachment $attachment)
    {
        $this->authorizePermission('jmv.operations.daily_production.view');
        $this->ensureJmvLog($productionLog);
        $this->ensureVisibleLog($productionLog);
        abort_unless((int) $attachment->jmv_daily_production_log_id === (int) $productionLog->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        return response()->download(Storage::disk('local')->path($attachment->path), $attachment->original_name);
    }

    public function export(Request $request, SimpleXlsxWriter $xlsx)
    {
        $this->authorizePermission('jmv.operations.daily_production.reports.view');
        $logs = $this->filteredQuery($request)->where('status', JmvDailyProductionLog::STATUS_COMPLETED)->orderBy('log_date')->orderBy('location')->get();
        $headers = ['Log No.','Date','Location','Shift','Leadman','Foreman','Total Workers','Timber Used','Timber Unit','Nails Used','Nails Unit','Fuel','Fuel Unit','Soil Output (Bagon)','Coal Output (Bagon)','Excess / Deficit','Remarks'];
        $rows = $logs->map(fn (JmvDailyProductionLog $log) => [
            $log->log_no,
            $log->log_date->format('Y-m-d'),
            $log->location,
            $log->shift,
            $log->leadman,
            $log->foreman,
            (int) $log->total_workers,
            (float) $log->timber_used,
            $log->timber_unit,
            (float) $log->nails_used,
            $log->nails_unit,
            (float) $log->fuel_consumed,
            $log->fuel_unit,
            (int) $log->soil_output,
            (int) $log->coal_output,
            $log->excess_deficit === null ? null : (int) $log->excess_deficit,
            $log->remarks,
        ])->all();
        $contents = $xlsx->make(
            'JMV MINING & DEVELOPMENT - DAILY PRODUCTION MONITORING',
            [
                ['Records', (string) $logs->count()],
                ['Generated', now()->format('M d, Y h:i A')],
            ],
            $headers,
            $rows,
            [20, 14, 22, 22, 20, 20, 15, 14, 13, 14, 13, 13, 12, 20, 20, 18, 36],
            'Daily Production',
        );
        $filename = 'jmv-daily-production-'.now()->format('Y-m-d-His').'.xlsx';

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->visibleQuery();
        $search = trim((string) $request->input('search'));
        return $query
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $nested) => $nested
                ->where('log_no', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%")
                ->orWhere('shift', 'like', "%{$search}%")->orWhere('leadman', 'like', "%{$search}%")->orWhere('foreman', 'like', "%{$search}%")))
            ->when($request->filled('month'), fn (Builder $q) => $q->whereRaw("DATE_FORMAT(log_date, '%Y-%m') = ?", [$request->input('month')]))
            ->when($request->filled('location'), fn (Builder $q) => $q->where('location', $request->input('location')))
            ->when($request->filled('shift'), fn (Builder $q) => $q->where('shift', $request->input('shift')))
            ->when(array_key_exists((string) $request->input('status'), JmvDailyProductionLog::STATUSES), fn (Builder $q) => $q->where('status', $request->input('status')));
    }

    private function validated(Request $request, int $divisionId, ?JmvDailyProductionLog $log = null): array
    {
        $request->merge([
            'location' => trim((string) $request->input('location')),
            'shift' => trim((string) $request->input('shift')),
            'timber_unit' => strtoupper(trim((string) $request->input('timber_unit'))),
            'nails_unit' => strtoupper(trim((string) $request->input('nails_unit'))),
            'fuel_unit' => 'LTRS',
        ]);
        $data = $request->validate([
            'log_date' => ['required', 'date', 'before_or_equal:today'],
            'location' => 'required|string|max:255', 'shift' => 'required|string|max:100',
            'leadman' => 'required|string|max:255', 'foreman' => 'required|string|max:255',
            'total_workers' => 'required|integer|min:1|max:10000',
            'timber_used' => 'required|numeric|min:0|max:9999999999', 'timber_unit' => ['required', Rule::in(['PCS','KG'])],
            'nails_used' => 'required|numeric|min:0|max:9999999999', 'nails_unit' => ['required', Rule::in(['PCS','KG'])],
            'fuel_consumed' => 'required|numeric|min:0|max:9999999999', 'fuel_unit' => ['required', Rule::in(['LTRS'])],
            'soil_output' => 'required|integer|min:0|max:999999999', 'coal_output' => 'required|integer|min:0|max:999999999',
            'excess_deficit' => 'nullable|integer|min:-999999999|max:999999999', 'remarks' => 'nullable|string|max:3000',
            'attachments' => 'nullable|array|max:5', 'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:10240',
        ]);

        $duplicate = JmvDailyProductionLog::query()
            ->where('division_id', $divisionId)
            ->whereDate('log_date', $data['log_date'])
            ->where('location', $data['location'])
            ->where('shift', $data['shift'])
            ->when($log, fn (Builder $query) => $query->whereKeyNot($log->getKey()))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'log_date' => 'A production log already exists for this date, location, and shift.',
            ]);
        }

        if ((int) $data['soil_output'] === 0 && (int) $data['coal_output'] === 0) {
            throw ValidationException::withMessages(['soil_output' => 'Record at least one soil or coal production output.']);
        }
        unset($data['attachments']);
        return $data;
    }

    private function storeAttachments(Request $request, JmvDailyProductionLog $log, array &$storedPaths): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store("jmv/daily-production/{$log->id}", 'local');
            $storedPaths[] = $path;
            $log->attachments()->create(['path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'uploaded_by' => auth()->id()]);
        }
    }

    private function audit(JmvDailyProductionLog $log, string $action, array $changes = []): void
    {
        JmvDailyProductionAudit::create(['jmv_daily_production_log_id' => $log->id, 'user_id' => auth()->id(), 'action' => $action, 'changes' => $changes ?: null, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent() ? mb_substr(request()->userAgent(), 0, 1000) : null, 'created_at' => now()]);
    }

    private function generateLogNo(): string
    {
        do { $number = 'JMV-DPM-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(2))); }
        while (JmvDailyProductionLog::where('log_no', $number)->exists());
        return $number;
    }

    private function visibleQuery(): Builder
    {
        $query = JmvDailyProductionLog::where('division_id', $this->jmvDivisionId());
        if (auth()->user()->isSystemAdministrator() || $this->can('jmv.operations.daily_production.approve')) return $query;

        if ($this->can('jmv.operations.daily_production.create')) {
            return $query->where(fn (Builder $scope) => $scope
                ->where('status', JmvDailyProductionLog::STATUS_COMPLETED)
                ->orWhere('created_by', auth()->id()));
        }

        return $query->where('status', JmvDailyProductionLog::STATUS_COMPLETED);
    }

    private function canEditLog(JmvDailyProductionLog $log): bool
    {
        return $this->can('jmv.operations.daily_production.update')
            && (auth()->user()->isSystemAdministrator() || (int) $log->created_by === (int) auth()->id())
            && in_array($log->status, [JmvDailyProductionLog::STATUS_DRAFT, JmvDailyProductionLog::STATUS_FOR_CORRECTION], true);
    }

    private function canSubmitLog(JmvDailyProductionLog $log): bool
    {
        return $this->can('jmv.operations.daily_production.submit')
            && (auth()->user()->isSystemAdministrator() || (int) $log->created_by === (int) auth()->id())
            && in_array($log->status, [JmvDailyProductionLog::STATUS_DRAFT, JmvDailyProductionLog::STATUS_FOR_CORRECTION], true);
    }

    private function canReviewLog(JmvDailyProductionLog $log): bool
    {
        return $this->can('jmv.operations.daily_production.approve')
            && $log->status === JmvDailyProductionLog::STATUS_PENDING_REVIEW
            && (int) $log->created_by !== (int) auth()->id();
    }

    private function ensureVisibleLog(JmvDailyProductionLog $log): void
    {
        abort_unless($this->visibleQuery()->whereKey($log->id)->exists(), 404);
    }

    private function notifyApprovers(JmvDailyProductionLog $log): void
    {
        $users = User::where('division_id', $this->jmvDivisionId())
            ->where('status', true)
            ->whereKeyNot($log->created_by)
            ->whereHas('position.permissions', fn ($query) => $query->where('slug', 'jmv.operations.daily_production.approve'))
            ->get();
        try {
            Notification::send($users, new JmvDailyProductionNotification(
                'Daily production log pending review',
                "{$log->log_no} is waiting for your review and approval.",
                route('jmv.operations.production.show', $log),
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function notifyCreator(JmvDailyProductionLog $log, string $subject, string $message): void
    {
        $creator = $log->creator()->first();
        if (! $creator || ! $creator->status) return;
        try {
            $creator->notify(new JmvDailyProductionNotification($subject, $message, route('jmv.operations.production.show', $log)));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function can(string $permission): bool { return auth()->user()->isSystemAdministrator() || auth()->user()->hasPermission($permission); }
    private function authorizePermission(string $permission): void { abort_unless($this->can($permission), 403, 'Your position is not authorized for this JMV Operations action.'); }
    private function jmvDivisionId(): int { return (int) Division::whereRaw('LOWER(TRIM(name)) = ?', ['jmv'])->value('id'); }
    private function ensureJmvLog(JmvDailyProductionLog $log): void { abort_unless((int) $log->division_id === $this->jmvDivisionId(), 404); }
}
