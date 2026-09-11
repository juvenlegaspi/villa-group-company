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
        $summary = $this->certificateAlertService->sendExpiringCertificateAlerts();
        $this->info("Certificate alerts: {$summary['email_sent']} email(s), {$summary['sms_sent']} SMS sent.");
        if ($summary['email_failed'] || $summary['sms_failed']) {
            $this->warn("Failures: {$summary['email_failed']} email(s), {$summary['sms_failed']} SMS.");
        }
        if ($summary['sms_skipped_unconfigured']) {
            $this->line("SMS skipped (Semaphore not configured): {$summary['sms_skipped_unconfigured']}");
        }

        return ($summary['email_failed'] || $summary['sms_failed']) ? self::FAILURE : self::SUCCESS;
    }
}
