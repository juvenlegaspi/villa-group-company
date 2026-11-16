<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayProperty extends Model
{
    use \App\Models\Concerns\InvalidatesHaruhayReservationDirectory;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['lot_area' => 'decimal:2', 'floor_area' => 'decimal:2', 'floors' => 'integer'];
    }

    public function attachments() { return $this->hasMany(HaruhayPropertyAttachment::class, 'property_id'); }
    public function units() { return $this->hasMany(HaruhayUnit::class, 'property_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function getLocationAttribute(?string $value): string
    {
        return $value ?? implode(', ', array_filter([$this->street_barangay, $this->city, $this->province], fn ($part) => filled($part)));
    }
}
