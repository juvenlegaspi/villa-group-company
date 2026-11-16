<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayDueReminder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'due_on' => 'date', 'started_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function billing()
    {
        return $this->belongsTo(HaruhayBilling::class, 'billing_id');
    }
}
