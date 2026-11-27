<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayCollection extends Model
{
    use \App\Models\Concerns\ScopesHaruhayProperties;

    protected $guarded = ['id'];
    protected static function booted(): void { static::saving(fn ($receipt) => $receipt->active_reference = $receipt->voided_at ? null : $receipt->external_reference); }
    protected function casts(): array { return ['amount' => 'decimal:2', 'amount_due' => 'decimal:2', 'paid_on' => 'date', 'voided_at' => 'datetime']; }
    public function scopeActive($query) { return $query->whereNull('voided_at'); }
    public function tenant() { return $this->belongsTo(HaruhayTenant::class); }
    public function property() { return $this->belongsTo(HaruhayProperty::class); }
    public function invoice() { return $this->belongsTo(HaruhayBilling::class, 'initial_billing_id'); }
    public function receiver() { return $this->belongsTo(User::class, 'created_by'); }
    public function deliveries() { return $this->hasMany(HaruhayCollectionDelivery::class, 'collection_id'); }
    public function reverser() { return $this->belongsTo(User::class, 'voided_by'); }
    public function getReceiptTypeAttribute(): string { return $this->initial_billing_id ? 'Bill Payment' : 'Advance Payment'; }
    public function allocations() { return $this->hasMany(HaruhayBillingPayment::class, 'collection_id')->whereNull('voided_at'); }
    public function allocationHistory() { return $this->hasMany(HaruhayBillingPayment::class, 'collection_id'); }
    public function getAppliedCentsAttribute(): int { return (int) round((float) (array_key_exists('allocations_sum_amount', $this->attributes) ? ($this->allocations_sum_amount ?? 0) : $this->allocations()->sum('amount')) * 100); }
    public function getUnappliedCentsAttribute(): int { return $this->voided_at ? 0 : max(0, (int) round((float) $this->amount * 100) - $this->applied_cents); }
    public function getStatusAttribute(): string { return $this->voided_at ? 'Reversed' : ($this->applied_cents === 0 ? 'Unapplied' : ($this->unapplied_cents > 0 ? 'Partly Applied' : 'Fully Applied')); }
}
