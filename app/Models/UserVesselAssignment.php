<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVesselAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'vessel_id',
        'assigned_by',
        'is_primary',
        'is_active',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function vessel() { return $this->belongsTo(Vessel::class); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
}
