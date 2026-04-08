<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoyageLog extends Model
{
    //
    protected $fillable = [
        'vessel_id',
        'date_started',
        'date_completed',
        'port_location',
        'voyage_number',
        'fuel_rob',
        'cargo_type',
        'cargo_volume',
        'crew_on_board',
        'voyage_status',
        'activity',
        'time_started',
        'time_finished',
        'total_hrs',
        'remarks',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }
}
