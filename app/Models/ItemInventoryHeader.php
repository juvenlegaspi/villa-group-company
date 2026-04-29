<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemInventoryHeader extends Model
{
    protected $table = 'item_inventory_header';

    protected $fillable = [
        'item_name',
        'unit',
        'maximum_quantity',
        'minimum_quantity',
        'stock_on_hand',
        'date_added',
        'created_by',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
