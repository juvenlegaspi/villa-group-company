<?php

namespace App\Services;

use App\Mail\CertificateExpiryNotification;
use App\Models\User;
use App\Models\VesselCertificate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

class CertificateAlertService
{
    public function getExpiringCertificates(int $days = 30): Collection
    {
        return VesselCertificate::query()
            ->with('vessel.captain')
            ->expiringWithinDays($days)
            ->get();
    }

    public function getRecipientsForCertificate(VesselCertificate $certificate): Collection
    {
        $vessel = $certificate->vessel;

        if (! $vessel) {
            return collect();
        }

        $managers = User::query()
            ->where('role', 'manager')
            ->where('department_id', 1)
            ->get();

        return collect([$vessel->captain])
            ->filter(fn ($user) => $user && ! empty($user->email))
            ->merge($managers)
            ->filter(fn ($user) => ! empty($user->email))
            ->unique('email')
            ->values();
    }

    public function sendExpiringCertificateAlerts(int $days = 30): int
    {
        $sentCount = 0;

        foreach ($this->getExpiringCertificates($days) as $certificate) {
            $vessel = $certificate->vessel;

            if (! $vessel) {
                continue;
            }

            foreach ($this->getRecipientsForCertificate($certificate) as $recipient) {
                Mail::to($recipient->email)->send(
                    new CertificateExpiryNotification($certificate, $vessel, $recipient)
                );

                $sentCount++;
            }
        }

        return $sentCount;
    }
}
