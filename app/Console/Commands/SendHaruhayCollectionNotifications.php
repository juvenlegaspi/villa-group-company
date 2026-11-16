<?php

namespace App\Console\Commands;

use App\Services\HaruhayCollectionNotificationService;
use Illuminate\Console\Command;

class SendHaruhayCollectionNotifications extends Command
{
    protected $signature = 'haruhay:send-collection-notifications {--watch : Process new receipt notifications every five seconds}';

    protected $description = 'Send pending Haruhay payment receipts by email and SMS once per channel';

    public function handle(HaruhayCollectionNotificationService $service): int
    {
        if ($this->option('watch')) {
            $this->info('Watching new Haruhay payment receipt notifications.');
        }
        do {
            $count = $service->sendPending();
            if ($count || ! $this->option('watch')) {
                $this->info($count.' receipt notification(s) processed.');
            }
            if ($this->option('watch')) {
                sleep(5);
            }
        } while ($this->option('watch'));

        return self::SUCCESS;
    }
}
