<?php

namespace App\Services;

use App\Models\Division;
use App\Models\ShippingCalendarEvent;
use App\Models\ShippingPmsDetail;
use App\Models\ShippingPmsHeader;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PmsCalendarService
{
    public function createSchedule(ShippingPmsHeader $header, ShippingPmsDetail $detail): ShippingCalendarEvent
    {
        [$frequency, $interval] = match ($detail->frequency) {
            'Daily' => ['daily', 1],
            'Weekly' => ['weekly', 1],
            'Bi-Weekly' => ['weekly', 2],
            'Monthly' => ['monthly', 1],
            'Quarterly' => ['monthly', 3],
            'Semi-annual' => ['monthly', 6],
            'Annually' => ['monthly', 12],
            'Five-year' => ['monthly', 60],
            default => [null, 1],
        };

        $event = ShippingCalendarEvent::firstOrCreate(['pms_detail_id' => $detail->id], [
            'division_id' => Division::whereRaw('LOWER(name) = ?', ['villa shipping lines'])->sole()->id,
            'vessel_id' => $header->vessel_id,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'title' => Str::limit($header->name.' - '.$detail->name, 180, ''),
            'checklist_type' => 'preventive_maintenance',
            'description' => implode("\n", array_filter([
                'Equipment group: '.$header->name,
                'Item: '.$detail->name,
                $detail->activity ? 'Activity: '.$detail->activity : null,
                'Frequency: '.$detail->frequency,
                $detail->in_charge ? 'In charge: '.$detail->in_charge : null,
            ])),
            'starts_at' => Carbon::parse($detail->start_date->toDateString().' '.$detail->start_time),
            'ends_at' => $detail->expected_end_at,
            'all_day' => false,
            'visibility' => 'vessel',
            'color' => '#7c3aed',
            'recurrence_frequency' => $frequency,
            'recurrence_interval' => $interval,
            'reminder_minutes' => $detail->reminder_minutes,
            'email_reminder' => true,
            'status' => 'scheduled',
        ]);
        if ($event->wasRecentlyCreated) {
            $event->audits()->create([
                'user_id' => auth()->id(), 'action' => 'created_from_pms',
                'changes' => ['pms_detail_id' => $detail->id], 'created_at' => now(),
            ]);
        }

        return $event;
    }
}
