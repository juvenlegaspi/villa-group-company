<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayBillingPayment extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'paid_on' => 'date', 'voided_at' => 'datetime']; }
    public function scopeActive($query) { return $query->whereNull('voided_at'); }
    public function billing() { return $this->belongsTo(HaruhayBilling::class, 'billing_id'); }
    public function collection() { return $this->belongsTo(HaruhayCollection::class, 'collection_id'); }
}
