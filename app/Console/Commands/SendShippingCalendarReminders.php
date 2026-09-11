<?php

namespace App\Console\Commands;

use App\Services\ShippingChecklistReminderService;
use Illuminate\Console\Command;

class SendShippingCalendarReminders extends Command
{
    protected $signature = 'shipping-calendar:reminders';

    protected $description = 'Send due Villa Shipping vessel checklist reminders';

    public function handle(ShippingChecklistReminderService $reminders): int
    {
        $summary = $reminders->sendDueReminders();
        $this->info("Checklist reminders: {$summary['system_sent']} system, {$summary['email_sent']} email, {$summary['sms_sent']} SMS sent.");
        if ($summary['system_failed'] || $summary['email_failed'] || $summary['sms_failed']) {
            $this->warn("Failures: {$summary['system_failed']} system, {$summary['email_failed']} email, {$summary['sms_failed']} SMS.");
        }
        if ($summary['sms_skipped_unconfigured']) {
            $this->line("SMS skipped (Semaphore not configured): {$summary['sms_skipped_unconfigured']}");
        }
        if ($summary['delivery_exhausted']) {
            $this->error("Delivery attempts exhausted: {$summary['delivery_exhausted']}. Review shipping_calendar_reminder_logs.");
        }

        return ($summary['system_failed'] || $summary['email_failed'] || $summary['sms_failed'] || $summary['delivery_exhausted']) ? self::FAILURE : self::SUCCESS;
    }
}
