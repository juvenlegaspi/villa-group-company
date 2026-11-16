<?php

namespace App\Console\Commands;

use App\Services\HaruhayTrackingSmsService;
use Illuminate\Console\Command;

class SendHaruhayTrackingCodes extends Command
{
    protected $signature = 'haruhay:send-tracking-codes {--watch : Process new tracking code texts every five seconds}';

    protected $description = 'Send reservation references and private tracking codes once by SMS';

    public function handle(HaruhayTrackingSmsService $service): int
    {
        if ($this->option('watch')) {
            $this->info('Watching new Haruhay reservation tracking texts.');
        }
        do {
            $count = $service->sendPending();
            if ($count || ! $this->option('watch')) {
                $this->info($count.' tracking text(s) processed.');
            }
            if ($this->option('watch')) {
                sleep(5);
            }
        } while ($this->option('watch'));

        return self::SUCCESS;
    }
}
