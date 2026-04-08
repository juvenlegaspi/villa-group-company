<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechDefect extends Model
{
    //
    protected $fillable = [
        'vessel_id',
        'status',
        'date_completed',
        'date_identified',
        'port_location',
        'reported_by',
        'system_affected',
        'defect_description',
        'initial_cause',
        'severity_level',
        'operational_impact',
        'temporary_repair',
        'third_party_required',
        'third_party_reason',
        'spares_required',
        'remarks',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }
    public function thirdParty()
    {
        return $this->hasOne(ThirdPartySupport::class);
    }
    public function supports()
    {
        return $this->hasMany(\App\Models\ThirdPartySupport::class,'tech_defect_id');
    }
    public function techDefect()
    {
        return $this->belongsTo(\App\Models\TechDefect::class, 'tech_defect_id');
    }
}
