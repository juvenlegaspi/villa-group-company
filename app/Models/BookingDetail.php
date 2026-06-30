<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDetail extends Model
{
    protected $table = 'booking_details';

    protected $fillable = [
        'booking_header_id',
        'space_id',
        'hyve_room_id',
        'booking_date',
        'start_time',
        'end_time',
        'charge_period',
        'duration_hours',
        'billed_hours',
        'guests',
        'rate_name',
        'rate_amount',
        'subtotal',
        'status',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'duration_hours' => 'decimal:2',
        'billed_hours' => 'decimal:2',
        'guests' => 'integer',
        'rate_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function bookingHeader(): BelongsTo
    {
        return $this->belongsTo(BookingHeader::class, 'booking_header_id');
    }

    public function hyveRoom(): BelongsTo
    {
        return $this->belongsTo(HyveRoom::class, 'hyve_room_id');
    }
}
