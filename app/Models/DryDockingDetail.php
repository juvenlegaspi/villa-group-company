<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DryDockingDetail extends Model
{
    protected $table = 'dry_docking_details';

    protected $fillable = [
        'vessel_id',
        'dry_dock_id',
        'scope_of_work',
        'plan_duration',
        'actual_duration',
        'status',
        'daily_status',
        'weight',
        'actual_progress',
        'activity',
        'remarks',
    ];

    public function header()
    {
        return $this->belongsTo(DryDockingHeader::class, 'dry_dock_id');
    }

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }
}
