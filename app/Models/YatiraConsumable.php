<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class YatiraConsumable extends Model
{
    use SoftDeletes;
    protected $fillable = ['division_id', 'item_code', 'item_name', 'normalized_name', 'unit', 'stock_on_hand', 'reorder_level', 'status', 'created_by', 'updated_by'];
    protected function casts(): array { return ['stock_on_hand' => 'integer', 'reorder_level' => 'integer', 'status' => 'boolean']; }
    public function transactions() { return $this->hasMany(YatiraConsumableTransaction::class)->latest('transacted_at'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}
