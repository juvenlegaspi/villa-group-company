<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayBilling extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array
    {
        $casts = ['period_start' => 'date', 'period_end' => 'date', 'issued_on' => 'date', 'due_on' => 'date', 'finalized_at' => 'datetime', 'utilities_ready' => 'boolean'];
        foreach (['rent', 'water', 'electric', 'utilities_other', 'parking', 'addons_other', 'penalty', 'discount', 'total'] as $field) $casts[$field] = 'decimal:2';
        return $casts;
    }
    public function tenant() { return $this->belongsTo(HaruhayTenant::class); }
    public function contract() { return $this->belongsTo(HaruhayLeaseContract::class, 'contract_id'); }
    public function unit() { return $this->belongsTo(HaruhayUnit::class); }
    public function payments() { return $this->hasMany(HaruhayBillingPayment::class, 'billing_id')->whereNull('voided_at'); }
    public function paymentHistory() { return $this->hasMany(HaruhayBillingPayment::class, 'billing_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function deliveries() { return $this->hasMany(HaruhayBillingDelivery::class, 'billing_id'); }
    public function dueReminders() { return $this->hasMany(HaruhayDueReminder::class, 'billing_id'); }
    public function getPaidCentsAttribute(): int { return (int) round((float) ($this->payments_sum_amount ?? $this->payments()->sum('amount')) * 100); }
    public function getBalanceCentsAttribute(): int { return max(0, (int) round((float) $this->total * 100) - $this->paid_cents); }
    public function getStatusAttribute(): string
    {
        if (!$this->finalized_at) return 'Draft / Pending Computation';
        if ($this->balance_cents === 0) return 'Paid';
        if ($this->due_on->lt(today())) return 'Overdue';
        return $this->payment_status;
    }
    public function getPaymentStatusAttribute(): string
    {
        if (!$this->finalized_at) return 'Draft / Pending Computation';
        if ($this->balance_cents === 0) return 'Paid';
        return $this->paid_cents > 0 ? 'Partially Paid' : 'Unpaid';
    }
}
