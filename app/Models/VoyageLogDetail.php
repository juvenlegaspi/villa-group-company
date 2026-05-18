<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoyageLogDetail extends Model
{
    protected $table = 'voyage_logs_details';
    protected $primaryKey = 'dtl_id';

    protected $fillable = [
        'voyage_id',
        'vessel_id',
        'status',
        'total_hours',
        'remarks',
        'date_complete',
        'main_status',
    ];

    protected $casts = [
        'date_complete' => 'date',
    ];

    public function header()
    {
        return $this->belongsTo(VoyageLogHeader::class, 'voyage_id', 'voyage_id');
    }
    public function vessel()
    {
        return $this->belongsTo(Vessel::class, 'vessel_id');
    }
    public function activities()
    {
        return $this->hasMany(VoyageActivity::class, 'voyage_detail_id', 'dtl_id');
    }
    public function statusRelation()
{
    return $this->belongsTo(ActivityStatusVoyage::class, 'status', 'id');
}
}
