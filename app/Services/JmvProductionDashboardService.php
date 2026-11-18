<?php

namespace App\Services;

use App\Models\JmvDailyProductionLog;
use App\Models\JmvProductionTarget;
use Carbon\CarbonPeriod;

class JmvProductionDashboardService
{
    public function build(int $divisionId, array $filters): array
    {
        $logs = JmvDailyProductionLog::where('division_id', $divisionId)
            ->whereDate('log_date', '>=', $filters['from'])->whereDate('log_date', '<=', $filters['to'])
            ->orderByDesc('log_date')->orderByDesc('id')->get();
        $official = $logs->where('status', JmvDailyProductionLog::STATUS_COMPLETED);
        $measured = $official->whereNotNull('daily_target_bagons');
        $target = (int) $measured->sum('daily_target_bagons');
        $actualMeasured = (int) $measured->sum('coal_output');
        $daily = $official->groupBy(fn ($log) => $log->log_date->toDateString());
        $trend = [];
        foreach (CarbonPeriod::create($filters['from'], $filters['to']) as $date) {
            $rows = $daily->get($date->toDateString(), collect());
            $known = $rows->whereNotNull('daily_target_bagons');
            $trend[] = ['date' => $date->format('M d, Y'), 'coal' => (int) $rows->sum('coal_output'),
                'soil' => (int) $rows->sum('soil_output'),
                'target' => $rows->isNotEmpty() && $known->count() === $rows->count() ? (int) $known->sum('daily_target_bagons') : null];
        }
        $mines = $official->groupBy('location')->map(fn ($rows, $name) => [
            'name' => $name, 'coal' => (int) $rows->sum('coal_output'), 'soil' => (int) $rows->sum('soil_output'),
            'logs' => $rows->count(), 'fuel' => (float) $rows->sum('fuel_consumed'),
        ])->sortByDesc('coal')->values();

        return [
            'coal' => (int) $official->sum('coal_output'), 'soil' => (int) $official->sum('soil_output'),
            'coalMt' => round($official->sum('coal_output') * config('jmv.production_mt_per_bagon', 0.435), 3),
            'fuel' => (float) $official->sum('fuel_consumed'), 'workerShifts' => (int) $official->sum('total_workers'),
            'officialCount' => $official->count(), 'target' => $target,
            'achievement' => $target > 0 ? round($actualMeasured / $target * 100, 1) : null,
            'variance' => $measured->isNotEmpty() ? $actualMeasured - $target : null,
            'missingTargets' => $official->count() - $measured->count(),
            'states' => collect(JmvDailyProductionLog::STATUSES)->map(fn ($label, $status) => $logs->where('status', $status)->count())->all(),
            'trend' => $trend, 'mines' => $mines,
            'shifts' => $official->groupBy('shift')->map(fn ($rows) => (int) $rows->sum('coal_output'))->all(),
            'recent' => $official->take(6),
            'currentTarget' => JmvProductionTarget::where('division_id', $divisionId)->where('is_general', true)->first(),
        ];
    }
}
