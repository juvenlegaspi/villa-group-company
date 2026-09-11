<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemInventoryTransaction extends Model
{
    protected $fillable = [
        'item_inventory_header_id',
        'type',
        'quantity',
        'balance_before',
        'balance_after',
        'reference_no',
        'remarks',
        'transacted_at',
        'created_by',
        'location_id', 'movement_kind', 'supplier_name', 'issued_to', 'department_id',
        'project_equipment', 'purpose', 'unit_cost', 'approved_by', 'approved_at',
        'reversal_of_id', 'attachment_path', 'attachment_original_name',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'transacted_at' => 'datetime',
        'approved_at' => 'datetime', 'unit_cost' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(ItemInventoryHeader::class, 'item_inventory_header_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function location() { return $this->belongsTo(JmvInventoryLocation::class, 'location_id'); }
    public function department() { return $this->belongsTo(Department::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function reversalOf() { return $this->belongsTo(self::class, 'reversal_of_id'); }
}
