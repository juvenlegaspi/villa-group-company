<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VesselCertificate extends Model
{
    protected $fillable = [
        'vessel_id',
        'certificate_name',
        'issue_date',
        'expiry_date',
        'remarks',
        'document',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expiry_date', '<', now());
    }

    public function scopeExpiringWithinDays(Builder $query, int $days = 30): Builder
    {
        return $query->whereBetween('expiry_date', [now(), now()->copy()->addDays($days)]);
    }
}
