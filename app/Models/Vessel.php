<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'vessel_status',
        'location_update_token',
        'location_update_token_created_at',
    ];

    protected $casts = [
        'location_update_token_created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Vessel $vessel): void {
            if (blank($vessel->location_update_token)) {
                $vessel->location_update_token = Str::random(64);
                $vessel->location_update_token_created_at = now();
            }
        });
    }

    public function voyageLogs()
    {
        return $this->hasMany(VoyageLogHeader::class, 'vessel_id');
    }

    public function certificates()
    {
        return $this->hasMany(VesselCertificate::class);
    }

    public function captain()
    {
        return $this->belongsTo(User::class, 'captain_id');
    }

    public function userAssignments()
    {
        return $this->hasMany(UserVesselAssignment::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'user_vessel_assignments')
            ->withPivot(['assigned_by', 'is_primary', 'is_active', 'effective_from', 'effective_until'])
            ->withTimestamps();
    }

    public function positionLogs()
    {
        return $this->hasMany(VesselPositionLog::class);
    }
}
