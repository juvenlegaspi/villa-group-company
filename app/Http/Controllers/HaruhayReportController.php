<?php

namespace App\Http\Controllers;

use App\Models\{Division, HaruhayProperty, HaruhayTenant};
use App\Services\HaruhayReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class HaruhayReportController extends Controller
{
    public const TYPES = ['overview' => 'Overall Reports', 'income' => 'Income Report', 'collections' => 'Collection Report', 'outstanding' => 'Outstanding Report', 'ledger' => 'Tenant Ledger', 'occupancy' => 'Occupancy Report', 'leases' => 'Lease Expiry Report', 'deposits'=>'Security Deposit Report'];

    public function index(Request $request, HaruhayReportService $service)
    {
        [$divisionId, $filters] = $this->filters($request);
        $report = $service->build($divisionId, $filters);
        $table = $report['tables'][$filters['type']];
        $page = $request->integer('page', 1);
        $details = new LengthAwarePaginator(array_slice($table['rows'], ($page - 1) * 10, 10), count($table['rows']), 10, $page, ['path' => $request->url(), 'query' => $request->except('page')]);
        $properties = HaruhayProperty::where('division_id', $divisionId)->orderBy('name')->get();
        $tenants = HaruhayTenant::whereHas('unit.property', fn ($q) => $q->where('division_id', $divisionId))->orderBy('name')->get();
        return view('haruhay.reports.index', compact('report', 'filters', 'details', 'table', 'properties', 'tenants') + ['types' => self::TYPES]);
    }

    public function export(Request $request, HaruhayReportService $service, \App\Services\HaruhayReportWorkbook $workbook)
    {
        [$divisionId, $filters] = $this->filters($request);
        $report = $service->build($divisionId, $filters);
        if ($request->input('format', 'xlsx') === 'xlsx') {
            $property = !empty($filters['property_id']) ? HaruhayProperty::findOrFail($filters['property_id'])->name : 'All properties';
            $tenant = !empty($filters['tenant_id']) ? HaruhayTenant::findOrFail($filters['tenant_id'])->name : 'All tenants';
            $path = $workbook->create($report, $filters, 'Property: '.$property.' | Tenant: '.$tenant);
            return response()->download($path, 'haruhay-report-'.$filters['from'].'-to-'.$filters['to'].'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend(true);
        }
        $table = $report['tables'][$filters['type']];
        return response()->streamDownload(function () use ($table) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $table['headers'], ',', '"', '');
            foreach ($table['rows'] as $row) {
                $safe = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value, $row);
                fputcsv($stream, $safe, ',', '"', '');
            }
            fclose($stream);
        }, 'haruhay-'.$filters['type'].'-'.$filters['from'].'-to-'.$filters['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        $divisionId = (int) Division::whereRaw('LOWER(name) = ?', ['haruhay'])->sole()->id;
        $filters = $request->validate([
            'format' => 'nullable|in:xlsx,csv',
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))], 'group_by' => 'nullable|in:month,day', 'page' => 'nullable|integer|min:1',
            'occupancy_on'=>'nullable|date_format:Y-m-d|before_or_equal:today',
            'from' => 'nullable|date_format:Y-m-d'.($request->input('type') === 'leases' ? '' : '|before_or_equal:today'), 'to' => 'nullable|date_format:Y-m-d'.($request->input('type') === 'leases' ? '' : '|before_or_equal:today'),
            'property_id' => ['nullable', 'integer', Rule::exists('haruhay_properties', 'id')->where('division_id', $divisionId)],
            'tenant_id' => ['nullable', 'integer', Rule::exists('haruhay_tenants', 'id')->where(fn ($q) => $q->whereIn('unit_id', \App\Models\HaruhayUnit::whereHas('property', fn ($p) => $p->where('division_id', $divisionId))->select('id')))],
        ]);
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '') + ['type' => 'overview', 'group_by' => 'month', 'from' => today()->subMonthsNoOverflow(5)->startOfMonth()->toDateString(), 'to' => today()->toDateString()];
        if ($filters['from'] > $filters['to']) throw \Illuminate\Validation\ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        if (\Carbon\Carbon::parse($filters['from'])->diffInDays(\Carbon\Carbon::parse($filters['to'])) > 731) throw \Illuminate\Validation\ValidationException::withMessages(['to' => 'Choose a range of two years or less.']);
        return [$divisionId, $filters];
    }
}
