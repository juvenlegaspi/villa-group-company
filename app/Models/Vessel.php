<?php

namespace App\Models;
use App\Models\VoyageLog;

use Illuminate\Database\Eloquent\Model;

class Vessel extends Model
{
    
    protected $fillable = [
        'vessel_name',
        'captain_id',
        'imo_number',
        'call_sign',
        'vessel_type',
        'dwt',
        'fuel_type',
        'service_speed',
        'charter_type',
        'vessel_status'
    ];
    public function voyageLogs()
    {
        return $this->hasMany(VoyageLog::class);
    }
    public function certificates()
    {
        return $this->hasMany(VesselCertificate::class);
    }
    public function captain()
    {
        return $this->belongsTo(User::class, 'captain_id');
    }
}


