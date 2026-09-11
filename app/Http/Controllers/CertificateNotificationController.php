<?php

namespace App\Http\Controllers;

use App\Services\CertificateAlertService;
use App\Services\VesselAccessService;

class CertificateNotificationController extends Controller
{
    public function __construct(
        protected CertificateAlertService $certificateAlertService,
        protected VesselAccessService $vesselAccess,
    ) {}

    public function sendAlerts(): string
    {
        abort_unless(auth()->user()->isSystemAdministrator(), 403, 'System administrator access is required.');

        $summary = $this->certificateAlertService->sendExpiringCertificateAlerts();

        return "Notifications sent: {$summary['email_sent']} email(s), {$summary['sms_sent']} SMS.";
    }
}
