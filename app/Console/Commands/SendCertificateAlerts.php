<?php

namespace App\Console\Commands;

use App\Services\CertificateAlertService;
use Illuminate\Console\Command;

class SendCertificateAlerts extends Command
{
    protected $signature = 'certificates:alert';
    protected $description = 'Send alerts for expiring certificates';

    public function __construct(
        protected CertificateAlertService $certificateAlertService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sentCount = $this->certificateAlertService->sendExpiringCertificateAlerts();

        $this->info("Certificate alerts sent: {$sentCount}");

        return self::SUCCESS;
    }
}
