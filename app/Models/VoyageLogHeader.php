<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoyageLogHeader extends Model
{
    protected $table = 'voyage_logs_header';
    protected $primaryKey = 'voyage_id';

    protected $fillable = [
        'date_created',
        'cargo_type',
        'cargo_volume',
        'port_location',
        'voyage_no',
        'crew_on_board',
        'fuel_rob',
        'status',
        'created_by',
        'vessel_id',
        'arrival_date',
        'date_completed',
    ];

    protected $casts = [
        'date_completed' => 'date',
        'date_created' => 'date',
    ];

    public function details()
    {
        return $this->hasMany(VoyageLogDetail::class, 'voyage_id', 'voyage_id');
    }

    public function vessel()
    {
        return $this->belongsTo(Vessel::class, 'vessel_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getVoyageCodeAttribute()
    {
        return 'VL-' . str_pad($this->voyage_id, 5, '0', STR_PAD_LEFT);
    }
}
