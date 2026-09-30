<?php

namespace App\Services;

use App\Models\ShippingCalendarEvent;
use App\Models\User;
use App\Notifications\CalendarEventNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ShippingChecklistReminderService
{
    private const OPERATIONS_MANAGER_CODES = ['operations-manager', 'operation-manager', 'marine-operations-manager'];

    private const MARINE_DEPARTMENT_NAMES = ['marine operation', 'marine operations'];

    public function __construct(private readonly SemaphoreSmsService $sms) {}

    public function sendDueReminders(?Carbon $now = null): array
    {
        $now ??= now();
        $catchUpMinutes = max(1, (int) config('services.shipping_calendar.catch_up_minutes', 1440));
        $oldestReminder = $now->copy()->subMinutes($catchUpMinutes);
        $latestPossibleOccurrence = $now->copy()->addMinutes(10080)->addMinute();
        $summary = [
            'system_sent' => 0, 'system_failed' => 0, 'email_sent' => 0, 'email_failed' => 0,
            'sms_sent' => 0, 'sms_failed' => 0, 'sms_skipped_unconfigured' => 0,
            'delivery_exhausted' => 0,
        ];

        ShippingCalendarEvent::with(['vessel', 'occurrenceOverrides'])
            ->where('status', 'scheduled')
            ->whereNotNull('vessel_id')
            ->whereNotNull('reminder_minutes')
            ->where('starts_at', '<=', $latestPossibleOccurrence)
            ->where(function (Builder $query) use ($oldestReminder): void {
                $query->where(function (Builder $single) use ($oldestReminder): void {
                    $single->whereNull('recurrence_frequency')->where('starts_at', '>=', $oldestReminder);
                })->orWhere(function (Builder $recurring) use ($oldestReminder): void {
                    $recurring->whereNotNull('recurrence_frequency')
                        ->where(fn (Builder $end) => $end->whereNull('recurrence_ends_on')->orWhereDate('recurrence_ends_on', '>=', $oldestReminder));
                });
            })
            ->chunkById(100, function ($events) use ($now, $catchUpMinutes, &$summary): void {
                foreach ($events as $event) {
                    foreach ($this->dueOccurrences($event, $now, $catchUpMinutes) as $due) {
                        $anchor = $due['anchor'];
                        $occurrence = $due['starts_at'];
                        $effectiveEvent = $this->effectiveEvent($event, $due['override']);
                        foreach ($this->recipients($event) as $recipient) {
                            $payload = $this->payload($effectiveEvent, $occurrence);
                            $this->sendSystem($event, $recipient, $anchor, $payload, $summary);
                            if (filled($recipient->email)) {
                                $this->sendEmail($event, $recipient, $anchor, $payload, $summary);
                            }
                            if (filled($recipient->cell_number)) {
                                $this->sendSms($event, $effectiveEvent, $recipient, $anchor, $occurrence, $summary);
                            }
                        }
                    }
                }
            });

        return $summary;
    }

    public function recipients(ShippingCalendarEvent $event): Collection
    {
        $event->loadMissing('vessel');
        $managerIds = User::where('division_id', $event->division_id)
            ->where('status', true)
            ->whereHas('department', fn (Builder $departments) => $departments
                ->whereIn(DB::raw('LOWER(name)'), self::MARINE_DEPARTMENT_NAMES))
            ->whereHas('position', fn (Builder $positions) => $positions->whereIn('code', self::OPERATIONS_MANAGER_CODES))
            ->pluck('id');
        $captainIds = User::where('division_id', $event->division_id)
            ->where('status', true)
            ->where(fn (Builder $users) => $users->whereKey($event->vessel?->captain_id)
                ->orWhereHas('vesselAssignments', fn (Builder $assignments) => $assignments
                    ->where('vessel_id', $event->vessel_id)->where('is_active', true)
                    ->where(fn (Builder $dates) => $dates->whereNull('effective_from')->orWhereDate('effective_from', '<=', today()))
                    ->where(fn (Builder $dates) => $dates->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))))
            ->where(fn (Builder $users) => $users->where('role', 'captain')
                ->orWhereHas('position', fn (Builder $positions) => $positions->where('code', 'vessel-captain')))
            ->pluck('id');

        return User::whereIn('id', $managerIds->merge($captainIds)->filter()->unique())->where('status', true)->get();
    }

    private function dueOccurrences(ShippingCalendarEvent $event, Carbon $now, int $catchUpMinutes): array
    {
        $oldestReminder = $now->copy()->subMinutes($catchUpMinutes);
        $anchorFrom = $oldestReminder->copy()->subDay()->startOfDay();
        $anchorUntil = $now->copy()->addMinutes(10080)->endOfDay();
        $occurrence = $event->starts_at->copy();

        if (! $event->recurrence_frequency) {
            $override = $this->overrideFor($event, $occurrence);

            return $this->occurrenceIsDue($event, $occurrence, $override, $oldestReminder, $now)
                ? [['anchor' => $occurrence, 'starts_at' => $override?->has_changes ? $override->starts_at->copy() : $occurrence->copy(), 'override' => $override]]
                : [];
        }

        $this->fastForward($occurrence, $event, $anchorFrom);
        $occurrences = [];
        $iterations = 0;
        while ($occurrence <= $anchorUntil && $iterations < 1500) {
            if (! $event->recurrence_ends_on || $occurrence <= $event->recurrence_ends_on->copy()->endOfDay()) {
                $override = $this->overrideFor($event, $occurrence);
                if ($this->occurrenceIsDue($event, $occurrence, $override, $oldestReminder, $now)) {
                    $occurrences[] = [
                        'anchor' => $occurrence->copy(),
                        'starts_at' => $override?->has_changes ? $override->starts_at->copy() : $occurrence->copy(),
                        'override' => $override,
                    ];
                }
            }
            $this->advanceOccurrence($occurrence, $event);
            $iterations++;
        }

        return $occurrences;
    }

    private function occurrenceIsDue(ShippingCalendarEvent $event, Carbon $anchor, $override, Carbon $oldestReminder, Carbon $now): bool
    {
        if (($override?->status ?? $event->status) !== 'scheduled' || $this->occurrenceIsCompleted($event, $anchor)) {
            return false;
        }
        $startsAt = $override?->has_changes ? $override->starts_at->copy() : $anchor->copy();
        $reminderMinutes = (int) ($override?->has_changes ? $override->reminder_minutes : $event->reminder_minutes);
        $reminderAt = $startsAt->subMinutes($reminderMinutes);

        return $reminderAt->betweenIncluded($oldestReminder, $now->copy()->addSeconds(59));
    }

    private function overrideFor(ShippingCalendarEvent $event, Carbon $anchor)
    {
        return $event->occurrenceOverrides->first(fn ($override) => $override->occurrence_starts_at->equalTo($anchor));
    }

    private function effectiveEvent(ShippingCalendarEvent $event, $override): ShippingCalendarEvent
    {
        if (! $override?->has_changes) {
            return $event;
        }
        $effective = clone $event;
        $effective->forceFill([
            'title' => $override->title ?? $event->title,
            'checklist_type' => $override->checklist_type ?? $event->checklist_type,
            'description' => $override->description,
            'location' => $override->location,
            'color' => $override->color ?? $event->color,
            'reminder_minutes' => $override->reminder_minutes ?? $event->reminder_minutes,
        ]);

        return $effective;
    }

    private function occurrenceIsCompleted(ShippingCalendarEvent $event, Carbon $occurrence): bool
    {
        return DB::table('shipping_calendar_occurrence_completions')
            ->where('event_id', $event->id)
            ->where('occurrence_starts_at', $occurrence->format('Y-m-d H:i:s'))
            ->exists();
    }

    private function fastForward(Carbon $occurrence, ShippingCalendarEvent $event, Carbon $target): void
    {
        $interval = max(1, (int) $event->recurrence_interval);
        if ($occurrence >= $target) {
            return;
        }
        $steps = match ($event->recurrence_frequency) {
            'daily' => intdiv((int) $occurrence->diffInDays($target), $interval),
            'weekly' => intdiv((int) floor($occurrence->diffInDays($target) / 7), $interval),
            'monthly' => intdiv((int) $occurrence->diffInMonths($target), $interval),
        };
        match ($event->recurrence_frequency) {
            'daily' => $occurrence->addDays($steps * $interval),
            'weekly' => $occurrence->addWeeks($steps * $interval),
            'monthly' => $occurrence->addMonthsNoOverflow($steps * $interval),
        };
        while ($occurrence < $target) {
            $this->advanceOccurrence($occurrence, $event);
        }
    }

    private function advanceOccurrence(Carbon $occurrence, ShippingCalendarEvent $event): void
    {
        $interval = max(1, (int) $event->recurrence_interval);
        match ($event->recurrence_frequency) {
            'daily' => $occurrence->addDays($interval),
            'weekly' => $occurrence->addWeeks($interval),
            'monthly' => $this->advanceMonthlyOccurrence($occurrence, $event->starts_at, $interval),
        };
    }

    private function advanceMonthlyOccurrence(Carbon $occurrence, Carbon $anchor, int $interval): Carbon
    {
        $month = $occurrence->copy()->startOfMonth()->addMonths($interval);

        return $occurrence->setDate($month->year, $month->month, min($anchor->day, $month->daysInMonth));
    }

    private function sendSystem(ShippingCalendarEvent $event, User $recipient, Carbon $occurrence, array $payload, array &$summary): void
    {
        $log = $this->reserve($event, $recipient, $occurrence, 'database');
        if (! $this->beginAttempt($log, $summary)) {
            return;
        }
        try {
            $recipient->notify(new CalendarEventNotification($payload, ['database']));
            $this->markSent($log);
            $summary['system_sent']++;
        } catch (\Throwable $exception) {
            $this->markFailed($log, $exception);
            $summary['system_failed']++;
            report($exception);
        }
    }

    private function sendEmail(ShippingCalendarEvent $event, User $recipient, Carbon $occurrence, array $payload, array &$summary): void
    {
        $log = $this->reserve($event, $recipient, $occurrence, 'mail');
        if (! $this->beginAttempt($log, $summary)) {
            return;
        }
        try {
            $recipient->notify(new CalendarEventNotification($payload, ['mail']));
            $this->markSent($log);
            $summary['email_sent']++;
        } catch (\Throwable $exception) {
            $this->markFailed($log, $exception);
            $summary['email_failed']++;
            report($exception);
        }
    }

    private function sendSms(ShippingCalendarEvent $event, ShippingCalendarEvent $effectiveEvent, User $recipient, Carbon $anchor, Carbon $occurrence, array &$summary): void
    {
        if (! $this->sms->isConfigured()) {
            $summary['sms_skipped_unconfigured']++;

            return;
        }
        $log = $this->reserve($event, $recipient, $anchor, 'sms');
        if (! $this->beginAttempt($log, $summary)) {
            return;
        }
        try {
            $number = $this->sms->normalizePhilippineNumber($recipient->cell_number);
            if (! $number) {
                throw new RuntimeException('The recipient has an invalid Philippine mobile number.');
            }
            $result = $this->sms->send($number, Str::limit('Villa Shipping reminder: '.$effectiveEvent->title.' for '.$event->vessel?->vessel_name.' is scheduled '.$occurrence->format('M d, Y g:i A').'. Please perform the vessel checklist.', 160, ''));
            DB::table('shipping_calendar_reminder_logs')->where('id', $log->id)->update([
                'status' => 'sent', 'provider_message_id' => (string) $result['message_id'],
                'provider_status' => $result['status'] ?? null, 'error_message' => null,
                'sent_at' => now(), 'updated_at' => now(),
            ]);
            $summary['sms_sent']++;
        } catch (\Throwable $exception) {
            $this->markFailed($log, $exception);
            $summary['sms_failed']++;
            report($exception);
        }
    }

    private function reserve(ShippingCalendarEvent $event, User $recipient, Carbon $occurrence, string $channel): object
    {
        DB::table('shipping_calendar_reminder_logs')->insertOrIgnore([
            'event_id' => $event->id, 'user_id' => $recipient->id,
            'occurrence_starts_at' => $occurrence, 'channel' => $channel,
            'status' => 'reserved', 'attempts' => 0, 'sent_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('shipping_calendar_reminder_logs')->where([
            'event_id' => $event->id, 'user_id' => $recipient->id,
            'occurrence_starts_at' => $occurrence->format('Y-m-d H:i:s'), 'channel' => $channel,
        ])->firstOrFail();
    }

    private function beginAttempt(object $log, array &$summary): bool
    {
        if ($log->status === 'sent') {
            return false;
        }
        $maxAttempts = max(1, (int) config('services.shipping_calendar.max_delivery_attempts', 5));
        if ((int) $log->attempts >= $maxAttempts) {
            $summary['delivery_exhausted']++;

            return false;
        }

        return DB::table('shipping_calendar_reminder_logs')->where('id', $log->id)
            ->where('status', '!=', 'sent')->where('attempts', '<', $maxAttempts)
            ->update(['status' => 'sending', 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]) === 1;
    }

    private function markSent(object $log): void
    {
        DB::table('shipping_calendar_reminder_logs')->where('id', $log->id)->update(['status' => 'sent', 'error_message' => null, 'sent_at' => now(), 'updated_at' => now()]);
    }

    private function markFailed(object $log, \Throwable $exception): void
    {
        DB::table('shipping_calendar_reminder_logs')->where('id', $log->id)->update(['status' => 'failed', 'error_message' => Str::limit($exception->getMessage(), 1000), 'sent_at' => null, 'updated_at' => now()]);
    }

    private function payload(ShippingCalendarEvent $event, Carbon $occurrence): array
    {
        return [
            'title' => 'Vessel checklist reminder: '.$event->title,
            'message' => $event->title.' for '.$event->vessel?->vessel_name.' is scheduled on '.$occurrence->format('M d, Y').' at '.$occurrence->format('g:i A').'.',
            'type' => 'calendar', 'url' => route('shipping.calendar').'?vessel='.$event->vessel_id.'&event='.$event->id,
            'event_id' => $event->id, 'vessel_id' => $event->vessel_id,
        ];
    }
}
