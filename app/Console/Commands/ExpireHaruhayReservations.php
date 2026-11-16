<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExpireHaruhayReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'haruhay:expire-reservations {--watch : Check unpaid holds every 30 seconds for local development}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release expired unpaid Haruhay reservation holds; preserve paid and under-review reservations';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\HaruhayReservationService $service): int
    {
        $first = true;
        do {
            $released = $service->expire();
            if ($released || $first || ! $this->option('watch')) {
                $this->info($released.' expired unpaid holds released.');
            }
            $first = false;
            if (! $this->option('watch')) {
                break;
            }
            sleep(30);
        } while (true);

        return self::SUCCESS;
    }
}
