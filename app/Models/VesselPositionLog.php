<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VesselPositionLog extends Model
{
    protected $fillable = [
        'vessel_id',
        'voyage_id',
        'voyage_activity_id',
        'latitude',
        'longitude',
        'accuracy_meters',
        'speed_over_ground',
        'course_over_ground',
        'true_heading',
        'ais_message_type',
        'location_name',
        'source',
        'recorded_by',
        'reporter_name',
        'ip_address',
        'user_agent',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_meters' => 'float',
        'speed_over_ground' => 'float',
        'course_over_ground' => 'float',
        'true_heading' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function voyage()
    {
        return $this->belongsTo(VoyageLogHeader::class, 'voyage_id', 'voyage_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
