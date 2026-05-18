<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityStatusVoyage extends Model
{
    protected $table = 'activity_status_voyage';

    protected $fillable = [
        'name',
        'description',
        'status',
    ];
    public function status()
    {
        return $this->belongsTo(ActivityStatusVoyage::class, 'activity_status_voyage_id');
    }
    public function activities()
    {
        return $this->hasMany(ActivityVoyage::class, 'activity_status_voyage_id');
    }
}
