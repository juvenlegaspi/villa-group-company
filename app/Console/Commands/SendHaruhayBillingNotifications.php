<?php

namespace App\Console\Commands;

use App\Services\HaruhayBillingNotificationService;
use Illuminate\Console\Command;

class SendHaruhayBillingNotifications extends Command
{
    protected $signature = 'haruhay:send-billing-notifications {--watch : Process new bill notifications every five seconds}';

    protected $description = 'Send pending Haruhay issued-bill emails and SMS once per channel';

    public function handle(HaruhayBillingNotificationService $service): int
    {
        if ($this->option('watch')) {
            $this->info('Watching newly issued Haruhay bill notifications.');
        }
        do {
            $count = $service->sendPending();
            if ($count || ! $this->option('watch')) {
                $this->info($count.' billing notification(s) processed.');
            }
            if ($this->option('watch')) {
                sleep(5);
            }
        } while ($this->option('watch'));

        return self::SUCCESS;
    }
}
