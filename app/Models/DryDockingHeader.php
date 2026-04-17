<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'status',
    ];

    protected $casts = [
        'arrival_date' => 'date',
        'docking_date' => 'date',
        'undocking_date' => 'date',
        'create_date' => 'datetime',
        'is_shipyard' => 'boolean',
        'is_inhouse' => 'boolean',
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
