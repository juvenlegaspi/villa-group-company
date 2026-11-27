<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayDepositEntry extends Model
{
    use \App\Models\Concerns\ScopesHaruhayProperties;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(fn ($entry) => $entry->active_reference = $entry->voided_at || $entry->type === 'deduction' ? null : $entry->reference);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_on' => 'date', 'voided_at' => 'datetime'];
    }

    public function tenant()
    {
        return $this->belongsTo(HaruhayTenant::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('voided_at');
    }

    public static function heldCents(int $tenantId): int
    {
        return (int) static::where('tenant_id', $tenantId)->active()->get()->sum(fn ($e) => ($e->type === 'received' ? 1 : -1) * (int) round((float) $e->amount * 100));
    }

    public static function hasNonNegativeHistory(int $tenantId): bool
    {
        $held = 0;
        foreach (static::where('tenant_id', $tenantId)->active()->orderBy('occurred_on')->orderBy('id')->get() as $entry) {
            $held += ($entry->type === 'received' ? 1 : -1) * (int) round((float) $entry->amount * 100);
            if ($held < 0) {
                return false;
            }
        }

        return true;
    }
}
