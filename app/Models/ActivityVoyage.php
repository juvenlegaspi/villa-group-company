<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityVoyage extends Model
{
    protected $table = 'activity_voyage';

    protected $fillable = [
        'activity_status_voyage_id',
        'name',
        'description',
        'status',
    ];

    public function status()
    {
        return $this->belongsTo(ActivityStatusVoyage::class, 'activity_status_voyage_id');
    }
    public function voyageActivities()
    {
        return $this->hasMany(VoyageActivity::class, 'activity_id');
    }
}
