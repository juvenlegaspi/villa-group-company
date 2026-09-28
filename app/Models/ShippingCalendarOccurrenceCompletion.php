<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingCalendarOccurrenceCompletion extends Model
{
    protected $fillable = ['event_id', 'occurrence_starts_at', 'completed_by', 'completed_at'];

    protected function casts(): array
    {
        return ['occurrence_starts_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function event()
    {
        return $this->belongsTo(ShippingCalendarEvent::class, 'event_id');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
