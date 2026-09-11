<?php

namespace App\Services;

use App\Mail\CertificateExpiryNotification;
use App\Models\CertificateAlertLog;
use App\Models\CertificateSmsAlertLog;
use App\Models\User;
use App\Models\VesselCertificate;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CertificateAlertService
{
    public function __construct(
        private readonly VesselAccessService $vesselAccess,
        private readonly SemaphoreSmsService $sms,
    ) {}

    public function getExpiringCertificates(int $days = 30): EloquentCollection
    {
        return VesselCertificate::query()->with('vessel.captain')->effective()
            ->whereDate('expiry_date', '>=', today()->subDays(90))
            ->whereDate('expiry_date', '<=', today()->addDays($days))->get();
    }

    public function getRecipientsForCertificate(VesselCertificate $certificate): Collection
    {
        $vessel = $certificate->vessel;
        if (! $vessel) return collect();

        return User::query()->where('status', true)
            ->whereHas('division', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['villa shipping lines']))
            ->whereHas('department', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['marine operations']))
            ->whereHas('position', fn ($query) => $query->whereIn('code', [
                'operations-manager', 'operation-manager', 'marine-operations-manager', 'liaison-officer',
            ]))
            ->with(['division', 'department', 'position.permissions', 'accessRole.permissions'])->get()
            ->filter(fn (User $user): bool => $user->hasPermission('certificates.manage')
                && $this->vesselAccess->canAccess($user, $vessel))
            ->filter(fn ($user) => filled($user->email) || filled($user->cell_number))
            ->unique('id')->values();
    }

    public function sendExpiringCertificateAlerts(int $days = 30): array
    {
        $summary = ['email_sent' => 0, 'email_failed' => 0, 'sms_sent' => 0, 'sms_failed' => 0, 'sms_skipped_unconfigured' => 0];

        foreach ($this->getExpiringCertificates($days) as $certificate) {
            $daysRemaining = (int) today()->diffInDays($certificate->expiry_date, false);
            if (! $this->shouldSendAt($daysRemaining) || ! $certificate->vessel) continue;

            foreach ($this->getRecipientsForCertificate($certificate) as $recipient) {
                if (filled($recipient->email)) $this->sendEmail($certificate, $recipient, $summary);
                if (filled($recipient->cell_number)) {
                    if ($this->sms->isConfigured()) $this->sendSms($certificate, $recipient, $daysRemaining, $summary);
                    else $summary['sms_skipped_unconfigured']++;
                }
            }
        }

        return $summary;
    }

    private function sendEmail(VesselCertificate $certificate, User $recipient, array &$summary): void
    {
        $reserved = CertificateAlertLog::query()->firstOrCreate([
            'vessel_certificate_id' => $certificate->id,
            'recipient_email' => strtolower(trim($recipient->email)),
            'alert_date' => today(),
        ]);
        if (! $reserved->wasRecentlyCreated && $reserved->sent_at) return;

        try {
            Mail::to($recipient->email)->send(new CertificateExpiryNotification($certificate, $certificate->vessel, $recipient));
            $reserved->update(['sent_at' => now()]);
            $summary['email_sent']++;
        } catch (\Throwable $exception) {
            $reserved->delete();
            $summary['email_failed']++;
            report($exception);
        }
    }

    private function sendSms(VesselCertificate $certificate, User $recipient, int $daysRemaining, array &$summary): void
    {
        $number = $this->sms->normalizePhilippineNumber($recipient->cell_number);
        if (! $number) {
            $summary['sms_failed']++;
            return;
        }
        $log = CertificateSmsAlertLog::query()->firstOrCreate([
            'vessel_certificate_id' => $certificate->id,
            'recipient_number' => $number,
            'alert_date' => today(),
        ], ['user_id' => $recipient->id, 'days_remaining' => $daysRemaining, 'status' => 'reserved']);
        if (! $log->wasRecentlyCreated && $log->sent_at) return;

        try {
            $result = $this->sms->send($number, $this->smsMessage($certificate, $daysRemaining));
            $log->update([
                'user_id' => $recipient->id, 'days_remaining' => $daysRemaining, 'status' => 'sent',
                'provider_message_id' => (string) $result['message_id'], 'provider_status' => $result['status'] ?? null,
                'error_message' => null, 'sent_at' => now(),
            ]);
            $summary['sms_sent']++;
        } catch (\Throwable $exception) {
            $log->update(['status' => 'failed', 'error_message' => Str::limit($exception->getMessage(), 1000), 'sent_at' => null]);
            $summary['sms_failed']++;
            report($exception);
        }
    }

    private function smsMessage(VesselCertificate $certificate, int $daysRemaining): string
    {
        $timing = $daysRemaining < 0 ? abs($daysRemaining).' day(s) overdue' : ($daysRemaining === 0 ? 'expires today' : 'expires in '.$daysRemaining.' day(s)');
        return Str::limit('Villa Shipping certificate alert: '.$certificate->certificate_name.' for '.$certificate->vessel->vessel_name.' '.$timing.' ('.$certificate->expiry_date->format('M d, Y').'). Please process renewal.', 160, '');
    }

    private function shouldSendAt(int $daysRemaining): bool
    {
        return in_array($daysRemaining, [30, 14, 7, 3, 1, 0], true)
            || ($daysRemaining < 0 && abs($daysRemaining) % 7 === 0);
    }
}
