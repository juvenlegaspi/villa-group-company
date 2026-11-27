<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayReservationEvent extends Model
{
    use \App\Models\Concerns\ScopesHaruhayProperties;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
