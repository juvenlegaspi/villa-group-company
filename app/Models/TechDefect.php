<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechDefect extends Model
{
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

    protected $casts = [
        'date_completed' => 'date',
        'date_identified' => 'date',
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
        return $this->hasMany(ThirdPartySupport::class, 'tech_defect_id');
    }
}
