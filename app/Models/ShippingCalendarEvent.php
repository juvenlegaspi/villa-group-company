<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingCalendarEvent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'division_id', 'created_by', 'updated_by', 'department_id', 'vessel_id', 'title', 'checklist_type', 'description',
        'location', 'starts_at', 'ends_at', 'all_day', 'visibility', 'color', 'recurrence_frequency',
        'recurrence_interval', 'recurrence_ends_on', 'reminder_minutes', 'email_reminder', 'status',
        'cancelled_at', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'all_day' => 'boolean',
            'recurrence_ends_on' => 'date', 'email_reminder' => 'boolean', 'cancelled_at' => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function attendees()
    {
        return $this->belongsToMany(User::class, 'shipping_calendar_attendees', 'event_id', 'user_id')->withPivot(['response_status', 'responded_at'])->withTimestamps();
    }

    public function audits()
    {
        return $this->hasMany(ShippingCalendarAudit::class, 'event_id')->latest('created_at');
    }

    public function attachments()
    {
        return $this->hasMany(ShippingCalendarAttachment::class, 'event_id')->latest('created_at');
    }

    public function occurrenceCompletions()
    {
        return $this->hasMany(ShippingCalendarOccurrenceCompletion::class, 'event_id');
    }

    public function occurrenceOverrides()
    {
        return $this->hasMany(ShippingCalendarOccurrenceOverride::class, 'event_id');
    }
}
