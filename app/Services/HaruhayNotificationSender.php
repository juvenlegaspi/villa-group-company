<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HaruhayNotificationSender
{
    public function __construct(private SemaphoreSmsService $sms) {}

    public function sendPending(string $modelClass, Closure $sourceCheck, Closure $mailable, Closure $smsMessage, array $enabled, int $limit = 50): int
    {
        $modelClass::where('status', 'Processing')->where('started_at', '<', now()->subMinutes(10))
            ->update(['status' => 'NeedsReview', 'status_note' => 'Delivery interrupted. Check provider records before sending again.']);
        $count = 0;
        foreach ($modelClass::where('status', 'Pending')->orderBy('id')->limit($limit)->pluck('id') as $id) {
            $delivery = DB::transaction(function () use ($modelClass, $id) {
                $row = $modelClass::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($row->status !== 'Pending') {
                    return null;
                }
                $row->update(['status' => 'Processing', 'started_at' => now()]);

                return $row;
            });
            if (! $delivery) {
                continue;
            }
            $count++;
            $problem = $sourceCheck($delivery) ?: $this->preflight($delivery, $enabled);
            if ($problem) {
                $delivery->update(['status' => 'Blocked', 'status_note' => $problem]);

                continue;
            }
            try {
                if ($delivery->channel === 'email') {
                    $sent = Mail::to($delivery->recipient)->send($mailable($delivery->payload));
                    $reference = $sent?->getMessageId();
                } else {
                    // A timeout may occur after acceptance. Do not automatically send twice.
                    $result = $this->sms->send($delivery->recipient, $smsMessage($delivery->payload), false);
                    $reference = (string) $result['message_id'];
                }
                $delivery->update(['status' => 'Submitted', 'provider_reference' => $reference, 'submitted_at' => now(), 'status_note' => 'Accepted by the sending provider. Inbox or handset delivery is not confirmed.']);
            } catch (\Throwable $exception) {
                $delivery->update(['status' => 'NeedsReview', 'status_note' => 'Provider acceptance could not be confirmed. Check provider records before sending again.']);
                Log::warning('Haruhay notification needs review.', ['delivery_type' => class_basename($delivery), 'delivery_id' => $delivery->id, 'exception_type' => get_class($exception)]);
            }
        }

        return $count;
    }

    private function preflight(Model $delivery, array $enabled): ?string
    {
        if (! ($enabled[$delivery->channel] ?? false)) {
            return 'This notification channel is disabled.';
        }
        if ($delivery->channel === 'sms') {
            if (! $this->sms->normalizePhilippineNumber($delivery->recipient)) {
                return 'Update the tenant Philippine mobile number, then retry.';
            }

            return $this->sms->isConfigured() ? null : 'SMS provider is not configured.';
        }
        if (! filter_var($delivery->recipient, FILTER_VALIDATE_EMAIL)) {
            return 'Update the tenant email address, then retry.';
        }
        $transport = config('mail.mailers.'.config('mail.default').'.transport');

        return in_array($transport, ['smtp', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun', 'sendmail'], true)
            ? null : 'A real email transport must be configured; log and test mailers do not deliver email.';
    }
}
