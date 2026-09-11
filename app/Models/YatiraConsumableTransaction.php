<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YatiraConsumableTransaction extends Model
{
    protected $fillable = ['yatira_consumable_id', 'type', 'quantity', 'balance_before', 'balance_after', 'reference_no', 'remarks', 'created_by', 'transacted_at'];
    protected function casts(): array { return ['quantity' => 'integer', 'balance_before' => 'integer', 'balance_after' => 'integer', 'transacted_at' => 'datetime']; }
    public function item() { return $this->belongsTo(YatiraConsumable::class, 'yatira_consumable_id'); }
    public function user() { return $this->belongsTo(User::class, 'created_by'); }
}
