<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayBillingDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'started_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function billing()
    {
        return $this->belongsTo(HaruhayBilling::class, 'billing_id');
    }
}
