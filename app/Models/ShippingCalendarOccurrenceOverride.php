<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingCalendarOccurrenceOverride extends Model
{
    protected $fillable = [
        'event_id', 'occurrence_starts_at', 'has_changes', 'starts_at', 'ends_at', 'title', 'checklist_type',
        'description', 'location', 'color', 'reminder_minutes', 'status', 'updated_by',
        'cancelled_at', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'occurrence_starts_at' => 'datetime', 'has_changes' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'reminder_minutes' => 'integer', 'cancelled_at' => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(ShippingCalendarEvent::class, 'event_id');
    }
}
