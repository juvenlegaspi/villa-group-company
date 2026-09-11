<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingCalendarAudit extends Model
{
    public $timestamps = false;
    protected $fillable = ['event_id', 'user_id', 'action', 'changes', 'ip_address', 'user_agent', 'created_at'];
    protected function casts(): array { return ['changes' => 'array', 'created_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
}
