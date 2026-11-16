<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayOccupancyEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'occupied_tenant_ids' => 'array', 'reserved_tenant_ids' => 'array', 'capacity' => 'integer'];
    }

    public function property()
    {
        return $this->belongsTo(HaruhayProperty::class);
    }
}
