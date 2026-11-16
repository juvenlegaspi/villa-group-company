<?php

namespace App\Services;

use App\Mail\HaruhayBillIssued;
use App\Models\HaruhayBilling;
use App\Models\HaruhayBillingDelivery;

class HaruhayBillingNotificationService
{
    public function enqueue(HaruhayBilling $bill): void
    {
        if (! $bill->finalized_at) {
            return;
        }
        $bill->loadMissing('tenant');
        $bill->loadSum('payments', 'amount');
        $charges = [];
        foreach (['rent' => 'Rent'] + \App\Http\Controllers\HaruhayBillingController::CHARGES as $field => $label) {
            $charges[$label] = (float) $bill->$field;
        }
        $payload = [
            'code' => $bill->code, 'name' => $bill->tenant_name, 'property' => $bill->property_name, 'room' => $bill->room_no,
            'period' => $bill->period_start->format('M d, Y').' - '.$bill->period_end->format('M d, Y'),
            'due' => $bill->due_on->format('M d, Y'), 'charges' => $charges, 'total' => (float) $bill->total,
            'paid' => $bill->paid_cents / 100, 'balance' => $bill->balance_cents / 100,
            'as_of' => now()->format('M d, Y g:i A'),
        ];
        foreach (['email' => $bill->tenant->email, 'sms' => $bill->tenant->contact_number] as $channel => $recipient) {
            HaruhayBillingDelivery::firstOrCreate(['billing_id' => $bill->id, 'channel' => $channel], ['recipient' => $recipient, 'payload' => $payload]);
        }
    }

    public function retryBlocked(HaruhayBilling $bill): void
    {
        $bill->loadMissing('tenant');
        foreach (['email' => $bill->tenant->email, 'sms' => $bill->tenant->contact_number] as $channel => $recipient) {
            HaruhayBillingDelivery::where('billing_id', $bill->id)->where('channel', $channel)->where('status', 'Blocked')
                ->update(['recipient' => $recipient, 'status' => 'Pending', 'status_note' => null]);
        }
    }

    public function sendPending(int $limit = 50): int
    {
        return app(HaruhayNotificationSender::class)->sendPending(
            HaruhayBillingDelivery::class,
            fn ($row) => $row->billing?->finalized_at ? null : 'Only issued bills can be sent.',
            fn ($payload) => new HaruhayBillIssued($payload),
            fn ($payload) => $this->smsMessage($payload),
            ['email' => config('haruhay_notifications.email_enabled'), 'sms' => config('haruhay_notifications.sms_enabled')],
            $limit,
        );
    }

    public function smsMessage(array $bill): string
    {
        return 'Haruhay Space: '.$bill['code'].' ('.$bill['room'].'). Period '.$bill['period'].'. Total PHP '.number_format($bill['total'], 2).'; applied PHP '.number_format($bill['paid'], 2).'; balance PHP '.number_format($bill['balance'], 2).'. Due '.$bill['due'].'. Balance as of issue; disregard if since paid. Contact Haruhay staff for details.';
    }
}
