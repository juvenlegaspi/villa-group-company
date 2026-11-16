<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HaruhayPublicRoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ready = $this->room_condition === 'Ready' && in_array($this->property->status, ['Active', 'Operational'], true);
        $slots = $ready ? $this->available_slots : 0;
        $availability = ! $ready ? 'unavailable' : ($slots === 0 ? 'full' : ($this->occupants_count + $this->reservations_count > 0 ? 'limited' : 'available'));
        $shared = ($this->capacity ?? 1) > 1 || str_contains(strtolower($this->unit_type), 'bunk') || str_contains(strtolower($this->unit_type), 'shared');

        return [
            'slug' => strtolower($this->unit_no),
            'unit_no' => $this->unit_no,
            'property_name' => $this->property->name,
            'unit_type' => $this->unit_type,
            'category' => $shared ? 'shared' : 'private',
            'floor' => $this->floor,
            'area' => $this->area,
            'capacity' => $this->capacity,
            'rent_basis' => $this->rent_basis,
            'monthly_rent' => $this->monthly_rent,
            'shared_rate' => $this->shared_rate,
            'room_condition' => $this->room_condition,
            'available_slots' => $slots,
            'availability' => $availability,
            'reservation_scope' => 'slots',
            'can_reserve' => $ready && $slots > 0,
        ];
    }
}
