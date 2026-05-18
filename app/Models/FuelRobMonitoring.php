<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelRobMonitoring extends Model
{
    protected $primaryKey = 'fuel_id';

    protected $fillable = [

        'voyage_id',
        'voyage_detail_id',
        'vessel_id',

        'beginning_fuel',
        'received_fuel',

        'status_id',
        'status_activity_id',

        'main_engine',
        'auxiliary_engine',
        'boiler',
        'others',

        'total_consumed',
        'remaining_fuel',

        'remarks',

        'created_by',

    ];
}
