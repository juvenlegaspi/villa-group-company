<?php

namespace App\Services;

use App\Models\HaruhayReservation;
use App\Models\HaruhayTrackingSms;

class HaruhayTrackingSmsService
{
    public function enqueue(HaruhayReservation $reservation, string $code): void
    {
        HaruhayTrackingSms::firstOrCreate(['reservation_id' => $reservation->id], [
            'recipient' => $reservation->phone,
            'payload' => ['reference' => $reservation->reference, 'code' => $code],
        ]);
    }

    public function sendPending(): int
    {
        HaruhayTrackingSms::where('status', 'Pending')->where('created_at', '<=', now()->subDay())
            ->update(['status' => 'Expired', 'status_note' => 'Unsent tracking text expired. Use the code saved on the website or ask staff for recovery.', 'payload' => null]);
        $count = app(HaruhayNotificationSender::class)->sendPending(
            HaruhayTrackingSms::class,
            function ($row) {
                $code = $row->payload['code'] ?? '';
                if (! $row->reservation || ! $code || ! hash_equals($row->reservation->tracking_token_hash, hash('sha256', $code))) {
                    return 'The access code was reset or is no longer available. The old code was not sent.';
                }

                return null;
            },
            fn () => throw new \LogicException('Tracking codes are sent by SMS only.'),
            fn ($payload) => 'Haruhay Space: Reservation received. Ref: '.$payload['reference'].'. Private tracking code: '.$payload['code'].'. Use Track reservation on our website. Keep this code private. Pending staff review, not yet confirmed.',
            ['sms' => config('haruhay_notifications.tracking_sms_enabled')],
        );
        // Keep only the delivery result after a send attempt, never a recoverable code history.
        HaruhayTrackingSms::whereNotIn('status', ['Pending', 'Processing'])->whereNotNull('payload')->update(['payload' => null]);

        return $count;
    }
}
