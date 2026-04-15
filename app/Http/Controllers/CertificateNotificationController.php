<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VesselCertificate;
use Illuminate\Support\Facades\Mail;
use App\Mail\CertificateExpiryNotification;

class CertificateNotificationController extends Controller
{
    public function sendAlerts()
    {
        // kuha expired + expiring
        $certificates = VesselCertificate::where(function ($query) {
            $query->where('expiry_date', '<', now())
                  ->orWhereBetween('expiry_date', [now(), now()->addDays(30)]);
        })->get();

        foreach ($certificates as $cert) {
            Mail::to('IT.Department@villagroupofcompanies.com') // ilisi ni boss
                ->send(new CertificateExpiryNotification($cert));
        }

        return "Notifications Sent!";
    }
}