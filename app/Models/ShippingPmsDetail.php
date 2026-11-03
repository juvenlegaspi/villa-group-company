<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingPmsDetail extends Model
{
    protected $fillable = ['name', 'activity', 'frequency', 'in_charge', 'start_date', 'status', 'start_time', 'expected_end_at', 'reminder_minutes'];

    protected $casts = ['start_date' => 'date', 'expected_end_at' => 'datetime'];

    public function calendarEvent(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ShippingCalendarEvent::class, 'pms_detail_id');
    }
}
