<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingPmsHeader extends Model
{
    protected $fillable = ['vessel_id', 'name', 'description'];

    public function details(): HasMany
    {
        return $this->hasMany(ShippingPmsDetail::class, 'header_id');
    }
}
