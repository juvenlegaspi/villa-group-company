<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'division_id',
        'name',
        'business_type',
        'tin',
        'normalized_tin',
        'address',
        'products',
        'tax_type',
        'lead_time',
        'credit_term',
        'limit_advances',
        'contact_person',
        'telephone',
        'mobile',
        'email',
        'status',
        'added_by',
        'updated_by',
    ];
    protected function casts(): array { return ['status' => 'boolean', 'limit_advances' => 'decimal:2']; }
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'added_by');
    }

    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function audits() { return $this->hasMany(SupplierAudit::class)->latest('created_at'); }
}
