<?php

namespace App\Http\Controllers;

use App\Services\CertificateAlertService;

class CertificateNotificationController extends Controller
{
    public function __construct(
        protected CertificateAlertService $certificateAlertService
    ) {
    }

    public function sendAlerts(): string
    {
        $sentCount = $this->certificateAlertService->sendExpiringCertificateAlerts();

        return "Notifications sent: {$sentCount}";
    }
}
