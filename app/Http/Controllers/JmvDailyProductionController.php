<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\JmvDailyProductionAttachment;
use App\Models\JmvDailyProductionAudit;
use App\Models\JmvDailyProductionLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JmvDailyProductionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('jmv.operations.daily_production.view');
        $query = $this->filteredQuery($request);
        $summaryQuery = clone $query;
        $logs = $query->with(['creator:id,name,lastname'])->latest('log_date')->latest('id')->paginate(15)->withQueryString();
        $summary = [
            'logs' => (clone $summaryQuery)->count(),
            'workers' => (int) (clone $summaryQuery)->sum('total_workers'),
            'soil' => (int) (clone $summaryQuery)->sum('soil_output'),
            'coal' => (int) (clone $summaryQuery)->sum('coal_output'),
            'fuel' => (float) (clone $summaryQuery)->sum('fuel_consumed'),
        ];
        $divisionId = $this->jmvDivisionId();
        $locations = JmvDailyProductionLog::where('division_id', $divisionId)->distinct()->orderBy('location')->pluck('location');
        $shifts = JmvDailyProductionLog::where('division_id', $divisionId)->distinct()->orderBy('shift')->pluck('shift');

        return view('jmv.operations.production.index', compact('logs', 'summary', 'locations', 'shifts') + [
            'canManage' => $this->can('jmv.operations.daily_production.manage'),
            'canExport' => $this->can('jmv.operations.daily_production.reports.view'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('jmv.operations.daily_production.manage');
        $divisionId = $this->jmvDivisionId();
        $data = $this->validated($request, $divisionId);
        $storedPaths = [];
        try {
            $log = DB::transaction(function () use ($request, $data, $divisionId, &$storedPaths): JmvDailyProductionLog {
                $log = JmvDailyProductionLog::create([...$data, 'division_id' => $divisionId, 'log_no' => $this->generateLogNo(), 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
                $this->storeAttachments($request, $log, $storedPaths);
                $this->audit($log, 'log_created', ['log_no' => $log->log_no]);
                return $log;
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route('jmv.operations.production.show', $log)->with('success', 'Daily production log saved successfully.');
    }

    public function show(JmvDailyProductionLog $productionLog)
    {
        $this->authorizePermission('jmv.operations.daily_production.view');
        $this->ensureJmvLog($productionLog);
        $productionLog->load(['creator:id,name,lastname', 'updater:id,name,lastname', 'attachments.uploader:id,name,lastname', 'audits.user:id,name,lastname']);
        return view('jmv.operations.production.show', ['log' => $productionLog, 'canManage' => $this->can('jmv.operations.daily_production.manage')]);
    }

    public function edit(JmvDailyProductionLog $productionLog)
    {
        $this->authorizePermission('jmv.operations.daily_production.manage');
        $this->ensureJmvLog($productionLog);
        return view('jmv.operations.production.edit', ['log' => $productionLog]);
    }

    public function update(Request $request, JmvDailyProductionLog $productionLog)
    {
        $this->authorizePermission('jmv.operations.daily_production.manage');
        $this->ensureJmvLog($productionLog);
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
        return redirect()->route('jmv.operations.production.show', $productionLog)->with('success', 'Daily production log updated successfully.');
    }

    public function attachment(JmvDailyProductionLog $productionLog, JmvDailyProductionAttachment $attachment)
    {
        $this->authorizePermission('jmv.operations.daily_production.view');
        $this->ensureJmvLog($productionLog);
        abort_unless((int) $attachment->jmv_daily_production_log_id === (int) $productionLog->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        return response()->download(Storage::disk('local')->path($attachment->path), $attachment->original_name);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission('jmv.operations.daily_production.reports.view');
        $logs = $this->filteredQuery($request)->orderBy('log_date')->orderBy('location')->get();
        $filename = 'jmv-daily-production-'.now()->format('Y-m-d-His').'.xml';
        return response()->streamDownload(function () use ($logs): void {
            $escape = fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Styles><Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#17345F" ss:Pattern="Solid"/></Style><Style ss:ID="Title"><Font ss:Bold="1" ss:Size="16" ss:Color="#17345F"/></Style><Style ss:ID="Number"><NumberFormat ss:Format="0.00"/></Style></Styles>';
            echo '<Worksheet ss:Name="Daily Production"><Table>';
            echo '<Row><Cell ss:MergeAcross="16" ss:StyleID="Title"><Data ss:Type="String">JMV Mining &amp; Development - Daily Production Monitoring</Data></Cell></Row>';
            echo '<Row><Cell ss:MergeAcross="16"><Data ss:Type="String">Generated '.now()->format('F d, Y h:i A').'</Data></Cell></Row><Row></Row>';
            $headers = ['Log No.','Date','Location','Shift','Leadman','Foreman','Total Workers','Timber Used','Timber Unit','Nails Used','Nails Unit','Fuel','Fuel Unit','Soil Output (Bagon)','Coal Output (Bagon)','Excess / Deficit','Remarks'];
            echo '<Row>'; foreach ($headers as $header) echo '<Cell ss:StyleID="Header"><Data ss:Type="String">'.$escape($header).'</Data></Cell>'; echo '</Row>';
            foreach ($logs as $log) {
                $values = [$log->log_no,$log->log_date->format('Y-m-d'),$log->location,$log->shift,$log->leadman,$log->foreman,$log->total_workers,$log->timber_used,$log->timber_unit,$log->nails_used,$log->nails_unit,$log->fuel_consumed,$log->fuel_unit,$log->soil_output,$log->coal_output,$log->excess_deficit,$log->remarks];
                echo '<Row>'; foreach ($values as $index => $value) { $numeric = in_array($index, [6,7,9,11,13,14,15], true) && $value !== null; echo '<Cell'.($numeric ? ' ss:StyleID="Number"' : '').'><Data ss:Type="'.($numeric ? 'Number' : 'String').'">'.$escape($value).'</Data></Cell>'; } echo '</Row>';
            }
            echo '</Table><AutoFilter x:Range="R4C1:R'.($logs->count() + 4).'C17" xmlns:x="urn:schemas-microsoft-com:office:excel"/></Worksheet></Workbook>';
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = JmvDailyProductionLog::where('division_id', $this->jmvDivisionId());
        $search = trim((string) $request->input('search'));
        return $query
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $nested) => $nested
                ->where('log_no', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%")
                ->orWhere('shift', 'like', "%{$search}%")->orWhere('leadman', 'like', "%{$search}%")->orWhere('foreman', 'like', "%{$search}%")))
            ->when($request->filled('month'), fn (Builder $q) => $q->whereRaw("DATE_FORMAT(log_date, '%Y-%m') = ?", [$request->input('month')]))
            ->when($request->filled('location'), fn (Builder $q) => $q->where('location', $request->input('location')))
            ->when($request->filled('shift'), fn (Builder $q) => $q->where('shift', $request->input('shift')));
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

    private function can(string $permission): bool { return auth()->user()->isSystemAdministrator() || auth()->user()->hasPermission($permission); }
    private function authorizePermission(string $permission): void { abort_unless($this->can($permission), 403, 'Your position is not authorized for this JMV Operations action.'); }
    private function jmvDivisionId(): int { return (int) Division::whereRaw('LOWER(TRIM(name)) = ?', ['jmv'])->value('id'); }
    private function ensureJmvLog(JmvDailyProductionLog $log): void { abort_unless((int) $log->division_id === $this->jmvDivisionId(), 404); }
}
