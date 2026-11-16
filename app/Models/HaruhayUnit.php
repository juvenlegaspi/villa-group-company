<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayUnit extends Model
{
    use \App\Models\Concerns\InvalidatesHaruhayReservationDirectory;
    protected $guarded = ['id'];
    protected $attributes = ['status' => 'Vacant', 'room_condition' => 'Ready'];
    protected static function booted(): void
    {
        static::saved(fn (self $unit) => app(\App\Services\HaruhayOccupancyService::class)->record($unit));
        static::creating(function (self $unit) {
            $unit->unit_no = 'pending-'.\Illuminate\Support\Str::uuid();
        });
        static::created(function (self $unit) {
            $unit->forceFill(['unit_no' => 'DR-'.str_pad((string) $unit->id, 2, '0', STR_PAD_LEFT)])->saveQuietly();
        });
        static::updating(function (self $unit) {
            $unit->unit_no = $unit->getOriginal('unit_no');
        });
    }
    protected function casts(): array
    {
        return ['floor' => 'integer', 'capacity' => 'integer', 'area' => 'decimal:2', 'monthly_rent' => 'decimal:2', 'shared_rate' => 'decimal:2', 'move_in_date' => 'date'];
    }
    public function property() { return $this->belongsTo(HaruhayProperty::class); }
    public function tenants() { return $this->hasMany(HaruhayTenant::class, 'unit_id'); }
    public function getPropertyStatusAttribute(): ?string
    {
        return $this->property?->status;
    }
    public function getIsAvailableAttribute(): bool
    {
        return $this->status === 'Vacant' && $this->room_condition === 'Ready';
    }
    public function getAvailableSlotsAttribute(): int
    {
        if ($this->room_condition !== 'Ready') return 0;
        $attributes = $this->getAttributes();
        $occupied = array_key_exists('occupants_count', $attributes) ? (int) $attributes['occupants_count'] : (int) $this->tenants()->whereIn('status', HaruhayTenant::LIVE_STATUSES)->sum('capacity_slots');
        $reserved = array_key_exists('reservations_count', $attributes) ? (int) $attributes['reservations_count'] : (int) $this->tenants()->where('status', 'Pending Move-in')->sum('capacity_slots');
        if (! $occupied && ! $reserved && $this->status !== 'Vacant') return 0;
        return max(0, ($this->capacity ?? 1) - $occupied - $reserved);
    }
}
