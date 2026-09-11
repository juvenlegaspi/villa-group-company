<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemInventoryHeader extends Model
{
    protected $table = 'item_inventory_header';

    protected $fillable = [
        'item_name',
        'division_id', 'item_code', 'normalized_name', 'description', 'category_id', 'default_location_id',
        'unit',
        'maximum_quantity',
        'minimum_quantity',
        'stock_on_hand',
        'date_added',
        'created_by',
        'status',
        'unit_cost', 'preferred_supplier', 'updated_by',
    ];

    protected function casts(): array { return ['stock_on_hand'=>'integer','minimum_quantity'=>'integer','maximum_quantity'=>'integer','unit_cost'=>'decimal:2','status'=>'boolean','date_added'=>'datetime']; }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions()
    {
        return $this->hasMany(ItemInventoryTransaction::class);
    }
    public function category() { return $this->belongsTo(JmvInventoryCategory::class, 'category_id'); }
    public function defaultLocation() { return $this->belongsTo(JmvInventoryLocation::class, 'default_location_id'); }
    public function balances() { return $this->hasMany(JmvInventoryBalance::class); }
    public function audits() { return $this->hasMany(JmvInventoryAudit::class)->latest('created_at'); }
}
