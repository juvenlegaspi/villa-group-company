<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayTenant extends Model
{
    use \App\Models\Concerns\ScopesHaruhayProperties;

    public function billings() { return $this->hasMany(HaruhayBilling::class, 'tenant_id'); }
    public function collections() { return $this->hasMany(HaruhayCollection::class, 'tenant_id'); }
    public function depositEntries() { return $this->hasMany(HaruhayDepositEntry::class, 'tenant_id'); }
    protected $guarded = ['id'];
    protected $attributes = ['occupant_count' => 1, 'capacity_slots' => 1];
    public const LIVE_STATUSES = ['Active', 'On Notice'];
    public const HELD_STATUSES = ['Active', 'On Notice', 'Pending Move-in'];
    protected function casts(): array { return ['unit_id' => 'integer', 'occupant_count' => 'integer', 'capacity_slots' => 'integer', 'move_in_date' => 'date', 'scheduled_move_in_date' => 'date', 'move_out_date' => 'date']; }
    protected static function booted(): void
    {
        static::saving(function (self $tenant) { $tenant->capacity_slots = $tenant->occupant_count; });
        static::creating(function (self $tenant) { $tenant->code = 'pending-'.\Illuminate\Support\Str::uuid(); });
        static::created(function (self $tenant) {
            $tenant->forceFill(['code' => 'TEN-'.str_pad((string) $tenant->id, 4, '0', STR_PAD_LEFT)])->saveQuietly();
        });
        static::updating(function (self $tenant) { $tenant->code = $tenant->getOriginal('code'); });
    }
    public function unit() { return $this->belongsTo(HaruhayUnit::class); }
    public function contracts() { return $this->hasMany(HaruhayLeaseContract::class, 'tenant_id'); }
    public function currentContract() { return $this->hasOne(HaruhayLeaseContract::class, 'tenant_id')->whereNull('terminated_on')->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())->whereDoesntHave('amendment', fn ($q) => $q->whereDate('starts_on', '<=', today()))->orderByDesc('starts_on')->orderByDesc('id'); }
    public function latestContract() { return $this->hasOne(HaruhayLeaseContract::class, 'tenant_id')->latestOfMany(); }
    public function audits() { return $this->hasMany(HaruhayTenantAudit::class, 'tenant_id'); }
}
