<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingCalendarAttachment extends Model
{
    protected $fillable = ['event_id', 'uploaded_by', 'original_name', 'path', 'mime_type', 'size_bytes'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function event()
    {
        return $this->belongsTo(ShippingCalendarEvent::class, 'event_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
