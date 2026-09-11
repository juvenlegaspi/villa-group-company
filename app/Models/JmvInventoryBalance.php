<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JmvInventoryBalance extends Model { protected $fillable = ['item_inventory_header_id','location_id','quantity']; protected function casts(): array { return ['quantity'=>'integer']; } public function location(){return $this->belongsTo(JmvInventoryLocation::class,'location_id');} }
