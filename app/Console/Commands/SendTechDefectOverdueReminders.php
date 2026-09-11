<?php

namespace App\Console\Commands;

use App\Http\Controllers\TechDefectController;
use App\Models\TechDefect;
use App\Models\User;
use App\Notifications\TechDefectNotification;
use Illuminate\Console\Command;

class SendTechDefectOverdueReminders extends Command
{
    protected $signature = 'tech-defects:overdue-alerts';
    protected $description = 'Send daily reminders for overdue defect repairs and third-party support';

    public function handle(): int
    {
        $count = 0;
        TechDefect::with(['vessel', 'assignee', 'supports'])->whereNotIn('status', [TechDefectController::STATUS_CLOSED, TechDefectController::STATUS_FOR_VERIFICATION])
            ->whereDate('target_completion_date', '<', today())->where(fn ($q) => $q->whereNull('overdue_notified_at')->orWhereDate('overdue_notified_at', '<', today()))
            ->chunkById(100, function ($reports) use (&$count): void {
                foreach ($reports as $report) {
                    $recipients = $this->managers()->merge($report->assignee ? collect([$report->assignee]) : collect())->unique('id');
                    foreach ($recipients as $user) $user->notify(new TechDefectNotification($this->payload($report, 'Repair target is overdue', "{$report->report_code} passed its target date {$report->target_completion_date->format('M d, Y')}.")));
                    $report->update(['overdue_notified_at' => now()]); $count++;
                }
            });
        TechDefect::with(['vessel', 'assignee', 'supports' => fn ($q) => $q->whereRaw('LOWER(status) != ?', ['done'])->whereDate('expected_completion_date', '<', today())->where(fn ($inner) => $inner->whereNull('overdue_notified_at')->orWhereDate('overdue_notified_at', '<', today()))])
            ->whereHas('supports', fn ($q) => $q->whereRaw('LOWER(status) != ?', ['done'])->whereDate('expected_completion_date', '<', today()))->chunkById(100, function ($reports) use (&$count): void {
                foreach ($reports as $report) foreach ($report->supports as $support) {
                    foreach ($this->managers()->merge($report->assignee ? collect([$report->assignee]) : collect())->unique('id') as $user) $user->notify(new TechDefectNotification($this->payload($report, 'External support is overdue', "{$support->vendor_name} support for {$report->report_code} is overdue.")));
                    $support->update(['overdue_notified_at' => now()]); $count++;
                }
            });
        $this->info("Sent {$count} overdue alert batch(es).");
        return self::SUCCESS;
    }

    private function managers()
    {
        return User::where('status', true)->where('role', '!=', 'owner')
            ->whereHas('division', fn ($q) => $q->whereRaw('LOWER(name) = ?', ['villa shipping lines']))
            ->get()->filter(fn (User $user) => $user->is_admin || $user->role === 'admin'
                || (($user->hasPermission('tech_defects.review') || $user->hasPermission('tech_defects.verify'))
                    && $user->hasApprovalAuthority('technical_defects', $user->division_id)));
    }

    private function payload(TechDefect $report, string $title, string $message): array
    {
        return ['title' => $title, 'message' => $message, 'type' => 'overdue', 'tech_defect_id' => $report->id, 'report_code' => $report->report_code, 'url' => route('tech-defects.show', $report, false)];
    }
}
