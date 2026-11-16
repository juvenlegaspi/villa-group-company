<?php

namespace App\Services;

use App\Mail\HaruhayPaymentReceived;
use App\Models\HaruhayCollection;
use App\Models\HaruhayCollectionDelivery;

class HaruhayCollectionNotificationService
{
    public function enqueue(HaruhayCollection $receipt): void
    {
        if ($receipt->voided_at) {
            return;
        }
        $receipt->load(['tenant', 'receiver', 'allocations.billing' => fn ($q) => $q->withSum('payments', 'amount')]);
        $receipt->loadSum('allocations', 'amount');
        $payload = [
            'code' => $receipt->code, 'name' => $receipt->tenant_name, 'property' => $receipt->property_name,
            'room' => $receipt->room_no, 'paid_on' => $receipt->paid_on->format('M d, Y'),
            'recorded_at' => $receipt->created_at->format('M d, Y g:i A'), 'method' => $receipt->method,
            'reference' => $receipt->external_reference, 'received_by' => $receipt->receiver?->name ?? 'Haruhay Staff',
            'type' => $receipt->receipt_type, 'amount' => (float) $receipt->amount,
            'applied' => $receipt->applied_cents / 100, 'credit' => $receipt->unapplied_cents / 100,
            'allocations' => $receipt->allocations->map(fn ($allocation) => [
                'bill' => $allocation->billing->code,
                'period' => $allocation->billing->period_start->format('M d, Y').' - '.$allocation->billing->period_end->format('M d, Y'),
                'amount' => (float) $allocation->amount, 'balance' => $allocation->billing->balance_cents / 100,
                'status' => $allocation->billing->payment_status,
            ])->all(),
        ];
        foreach (['email' => $receipt->tenant->email, 'sms' => $receipt->tenant->contact_number] as $channel => $recipient) {
            HaruhayCollectionDelivery::firstOrCreate(['collection_id' => $receipt->id, 'channel' => $channel], ['recipient' => $recipient, 'payload' => $payload]);
        }
    }

    public function retryBlocked(HaruhayCollection $receipt): void
    {
        if ($receipt->voided_at) {
            return;
        }
        $receipt->loadMissing('tenant');
        foreach (['email' => $receipt->tenant->email, 'sms' => $receipt->tenant->contact_number] as $channel => $recipient) {
            HaruhayCollectionDelivery::where('collection_id', $receipt->id)->where('channel', $channel)->where('status', 'Blocked')
                ->update(['recipient' => $recipient, 'status' => 'Pending', 'status_note' => null]);
        }
    }

    public function sendPending(int $limit = 50): int
    {
        return app(HaruhayNotificationSender::class)->sendPending(
            HaruhayCollectionDelivery::class,
            fn ($row) => $row->collection && ! $row->collection->voided_at ? null : 'This receipt has been reversed. Payment confirmation was not sent.',
            fn ($payload) => new HaruhayPaymentReceived($payload),
            fn ($payload) => $this->smsMessage($payload),
            ['email' => config('haruhay_notifications.receipt_email_enabled'), 'sms' => config('haruhay_notifications.receipt_sms_enabled')],
            $limit,
        );
    }

    public function smsMessage(array $receipt): string
    {
        $balance = $receipt['allocations']
            ? 'Bill balance PHP '.number_format($receipt['allocations'][0]['balance'], 2).'.'
            : 'Held as advance credit.';

        return 'Haruhay Space: Payment received PHP '.number_format($receipt['amount'], 2).' via '.$receipt['method'].' on '.$receipt['paid_on'].'. Receipt '.$receipt['code'].'. '.$balance.' Keep this receipt number. Balances as of recording.';
    }
}
