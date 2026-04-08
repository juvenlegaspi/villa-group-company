<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\DryDockingDetail;

class DryDockingHeader extends Model
{
    protected $fillable = [
        'vessel_id',
        'arrival_date',
        'docking_date',
        'laydays',
        'undocking_date',
        'vessel_manager',
        'is_shipyard',
        'is_inhouse',
        'create_date',
        'status'
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }
    public function details()
    {
        return $this->hasMany(DryDockingDetail::class, 'dry_dock_id');
    }
}
