<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JmvStockRequestItem extends Model { protected $fillable=['jmv_stock_request_id','item_inventory_header_id','location_id','quantity','approved_quantity','item_inventory_transaction_id'];public function item(){return $this->belongsTo(ItemInventoryHeader::class,'item_inventory_header_id');}public function location(){return $this->belongsTo(JmvInventoryLocation::class,'location_id');} }
