<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingPmsDetail extends Model
{
    protected $fillable = ['name', 'activity', 'frequency', 'in_charge', 'start_date', 'status'];

    protected $casts = ['start_date' => 'date'];
}
