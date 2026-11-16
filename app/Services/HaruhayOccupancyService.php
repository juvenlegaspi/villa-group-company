<?php

namespace App\Services;

use App\Models\HaruhayOccupancyEvent;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use Illuminate\Support\Facades\Schema;

class HaruhayOccupancyService
{
    public function record(HaruhayUnit $unit): void
    {
        if (! Schema::hasTable('haruhay_occupancy_events')) {
            return;
        }
        $unit->load('property');
        $data = ['unit_id' => $unit->id, 'property_id' => $unit->property_id, 'property_name' => $unit->property->name, 'room_no' => $unit->unit_no, 'status' => $unit->status, 'room_condition' => $unit->room_condition, 'capacity' => $unit->capacity,
            'occupied_tenant_ids' => $unit->tenants()->whereIn('status', HaruhayTenant::LIVE_STATUSES)->orderBy('id')->pluck('id')->all(),
            'reserved_tenant_ids' => $unit->tenants()->where('status', 'Pending Move-in')->orderBy('id')->pluck('id')->all(),
            'occupied_slots' => (int) $unit->tenants()->whereIn('status', HaruhayTenant::LIVE_STATUSES)->sum('capacity_slots'),
            'reserved_slots' => (int) $unit->tenants()->where('status', 'Pending Move-in')->sum('capacity_slots')];
        $last = HaruhayOccupancyEvent::where('unit_id', $unit->id)->orderByDesc('recorded_at')->orderByDesc('id')->first();
        if ($last && collect($data)->every(fn ($value, $key) => $value == $last->$key)) {
            return;
        }
        HaruhayOccupancyEvent::create($data + ['recorded_at' => now(), 'source' => 'change', 'created_by' => auth()->id()]);
    }
}
