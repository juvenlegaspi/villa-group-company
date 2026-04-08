<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VesselCertificate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class SendCertificateAlerts extends Command
{
    protected $signature = 'certificates:alert';
    protected $description = 'Send alerts for expiring certificates';

    public function handle()
    {
        $today = now();
        $limit = now()->addDays(30); // 30 days before expiry

        // kuha certificates nga hapit ma expire
        $certificates = VesselCertificate::with('vessel')
            ->whereBetween('expiry_date', [$today, $limit])
            ->get();
dd($certificates);
        foreach ($certificates as $cert) {

            $vessel = $cert->vessel;

            // ✅ KUHA CAPTAIN
            $captain = User::find($vessel->captain_id);

            // ✅ KUHA MANAGER (department 1)
            $managers = User::where('role', 'manager')
                ->where('department_id', 1)
                ->get();

            // combine tanan recipients
            $recipients = collect();

            if ($captain) {
                $recipients->push($captain);
            }

            foreach ($managers as $m) {
                $recipients->push($m);
            }

            // send email
            foreach ($recipients as $user) {

                if (!$user->email) continue;

                Mail::raw("
⚠️ Certificate Expiry Alert

Vessel: {$vessel->vessel_name}
Certificate: {$cert->certificate_name}
Expiry Date: {$cert->expiry_date}

Please take action.
", function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Certificate Expiring Soon');
                });
            }
        }

        $this->info('Alerts sent successfully!');
    }
}