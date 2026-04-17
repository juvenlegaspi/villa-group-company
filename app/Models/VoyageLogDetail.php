<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoyageLogDetail extends Model
{
    protected $table = 'voyage_logs_details';
    protected $primaryKey = 'dtl_id';

    protected $fillable = [
        'voyage_id',
        'voyage_status',
        'activity',
        'remarks',
        'date_time_started',
        'date_time_ended',
        'total_hours',
        'date_complete',
        'status',
        'is_paused',
        'pause_at',
        'total_pause',
    ];

    protected $casts = [
        'date_time_started' => 'datetime',
        'date_time_ended' => 'datetime',
        'pause_at' => 'datetime',
        'is_paused' => 'boolean',
    ];

    public function header()
    {
        return $this->belongsTo(VoyageLogHeader::class, 'voyage_id', 'voyage_id');
    }
}
