<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoyageActivity extends Model
{
    protected $table = 'voyage_activities';

    protected $primaryKey = 'activity_id';

    protected $fillable = [
        'voyage_id',
        'voyage_detail_id',
        'vessel_id',
        'status_id',
        'status_activity_id',
        'port_location',
        'remarks',
        'start_date_time',
        'edited_end_date_time',
        'edit_reason',
        'edit_attachment',
        'edited_at',
        'end_date_time',
        'total_hours',
        'total_load',
        'end_date_time',
        'total_hours',
        'cargo_load',
        'fuel_rob',
        'main_status',
    ];
    protected $casts = [
        'start_date_time' => 'datetime',
        'end_date_time'   => 'datetime',
    ];
    // relation to voyage header
    public function voyage()
    {
        return $this->belongsTo(VoyageLogHeader::class, 'voyage_id', 'voyage_id');
    }
    // relation to vessel
    public function vessel()
    {
        return $this->belongsTo(Vessel::class, 'vessel_id');
    }
    // relation to status
    public function status()
    {
        return $this->belongsTo(ActivityStatusVoyage::class, 'status_id', 'id');
    }
    public function detail()
    {
        return $this->belongsTo(VoyageLogDetail::class, 'voyage_detail_id', 'dtl_id');
    }
    public function activity()
    {
        return $this->belongsTo(ActivityVoyage::class, 'status_activity_id');
    }
}
