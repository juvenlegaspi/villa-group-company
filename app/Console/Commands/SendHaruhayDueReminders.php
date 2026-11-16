<?php

namespace App\Console\Commands;

use App\Services\HaruhayDueReminderService;
use Illuminate\Console\Command;

class SendHaruhayDueReminders extends Command
{
    protected $signature = 'haruhay:due-reminders {--watch : Check due-soon bills every minute during daytime}';

    protected $description = 'Send one SMS per unpaid Haruhay bill before its due date';

    public function handle(HaruhayDueReminderService $service): int
    {
        if ($this->option('watch')) {
            $this->info('Watching due-soon Haruhay bills, 9 AM to 6 PM Manila time.');
        }
        do {
            $count = $service->run();
            if ($count || ! $this->option('watch')) {
                $this->info($count.' due-soon SMS reminder(s) processed.');
            }
            if ($this->option('watch')) {
                sleep(30);
            }
        } while ($this->option('watch'));

        return self::SUCCESS;
    }
}
