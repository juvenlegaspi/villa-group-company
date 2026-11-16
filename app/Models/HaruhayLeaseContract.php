<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayLeaseContract extends Model
{
    public function billings() { return $this->hasMany(HaruhayBilling::class, 'contract_id'); }
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return [
            'starts_on' => 'date', 'ends_on' => 'date', 'billing_starts_on' => 'date', 'first_due_on' => 'date', 'terminated_on' => 'date', 'billing_coverage_starts_on'=>'date',
            'monthly_rent' => 'decimal:2', 'first_month_charge' => 'decimal:2', 'deposit_required' => 'decimal:2', 'advance_required' => 'decimal:2',
            'floor' => 'integer', 'due_day' => 'integer', 'unit_id' => 'integer', 'tenant_id' => 'integer',
        ];
    }
    public function tenant() { return $this->belongsTo(HaruhayTenant::class); }
    public function unit() { return $this->belongsTo(HaruhayUnit::class); }
    public function previousContract() { return $this->belongsTo(self::class, 'previous_contract_id'); }
    public function renewal() { return $this->hasOne(self::class, 'previous_contract_id'); }
    public function amendment() { return $this->hasOne(self::class, 'amends_contract_id'); }
    public function amendedContract() { return $this->belongsTo(self::class, 'amends_contract_id'); }
    public function getEffectiveEndsOnAttribute()
    {
        $end = $this->ends_on->copy();
        if ($this->terminated_on && $this->terminated_on->lt($end)) $end = $this->terminated_on->copy();
        if ($this->amendment) {
            $last = ($this->amendment->billing_coverage_starts_on ?? $this->amendment->starts_on)->copy()->subDay();
            if ($last->lt($end)) $end = $last;
        }
        return $end;
    }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function getStatusAttribute(): string
    {
        if ($this->terminated_on) return 'Terminated';
        if ($this->amendment && $this->amendment->starts_on->lte(today())) return 'Amended';
        if ($this->ends_on->lt(today())) return $this->renewal && ! $this->renewal->terminated_on ? 'Renewed' : 'Overdue';
        if ($this->starts_on->gt(today())) return 'Scheduled';
        return $this->ends_on->lte(today()->addDays(30)) ? 'Expiring Soon' : 'Active';
    }
    public function getDaysToExpireAttribute(): ?int
    {
        return $this->terminated_on ? null : (int) today()->diffInDays($this->ends_on, false);
    }
    public function getCanRenewAttribute(): bool
    {
        return in_array($this->status, ['Expiring Soon', 'Overdue'], true) && ! $this->renewal && !$this->amendment;
    }
}
