<?php

namespace App\Services;

use App\Models\Division;
use App\Models\HaruhayBilling;
use App\Models\HaruhayDueReminder;

class HaruhayDueReminderService
{
    public function run(): int
    {
        $hour = now('Asia/Manila')->hour;
        if (! config('haruhay_notifications.due_reminder_sms_enabled') || $hour < 9 || $hour >= 18) {
            return 0;
        }
        $this->enqueue();

        return app(HaruhayNotificationSender::class)->sendPending(
            HaruhayDueReminder::class,
            fn ($row) => $this->refreshBeforeSending($row),
            fn () => throw new \LogicException('Due reminders are SMS only.'),
            fn ($payload) => $this->smsMessage($payload),
            ['sms' => true],
        );
    }

    public function enqueue(): void
    {
        $today = today('Asia/Manila');
        HaruhayBilling::whereNotNull('finalized_at')
            ->whereDate('due_on', '>', $today)->whereDate('due_on', '<=', $today->copy()->addDays($this->leadDays()))
            ->whereHas('unit.property', fn ($q) => $q->whereIn('division_id', Division::whereRaw('LOWER(name) = ?', ['haruhay'])->select('id')))
            ->with('tenant')->withSum('payments', 'amount')->chunkById(100, function ($bills) {
                foreach ($bills as $bill) {
                    if ($bill->balance_cents <= 0) {
                        continue;
                    }
                    HaruhayDueReminder::whereDate('due_on', $bill->due_on)->firstOrCreate(
                        ['billing_id' => $bill->id],
                        ['due_on' => $bill->due_on->toDateString(), 'recipient' => $bill->tenant->contact_number, 'payload' => $this->payload($bill)],
                    );
                }
            });
    }

    private function refreshBeforeSending(HaruhayDueReminder $reminder): ?string
    {
        $bill = $reminder->billing()->with('tenant')->withSum('payments', 'amount')->first();
        if ($bill && ! $bill->unit()->whereHas('property', fn ($q) => $q->whereIn('division_id', Division::whereRaw('LOWER(name) = ?', ['haruhay'])->select('id')))->exists()) {
            return 'This bill is no longer within Haruhay. No reminder sent.';
        }
        if (! $bill?->finalized_at || $bill->balance_cents <= 0) {
            return 'Bill is already fully paid or not issued. No reminder sent.';
        }
        $today = today('Asia/Manila');
        if (! $bill->due_on->equalTo($reminder->due_on) || $bill->due_on->lte($today) || $bill->due_on->gt($today->copy()->addDays($this->leadDays()))) {
            return 'The due date changed or this bill is outside the upcoming reminder window. No reminder sent.';
        }
        $reminder->update(['recipient' => $bill->tenant->contact_number, 'payload' => $this->payload($bill)]);

        return null;
    }

    private function payload(HaruhayBilling $bill): array
    {
        return ['code' => $bill->code, 'room' => $bill->room_no, 'due' => $bill->due_on->format('M d, Y'), 'balance' => $bill->balance_cents / 100];
    }

    public function leadDays(): int
    {
        return max(1, min(14, (int) config('haruhay_notifications.due_reminder_days', 3)));
    }

    public function smsMessage(array $payload): string
    {
        return 'Haruhay Space reminder: '.$payload['code'].' ('.$payload['room'].') has PHP '.number_format($payload['balance'], 2).' remaining, due '.$payload['due'].'. Please settle on or before the due date. Disregard if already paid. Thank you.';
    }
}
