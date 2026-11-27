<?php

namespace App\Http\Controllers;

use App\Http\Resources\HaruhayPublicRoomResource;
use App\Models\Division;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HaruhayPublicRoomController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        $units = HaruhayUnit::query()
            ->whereHas('property', fn ($query) => $query->whereIn('division_id', Division::whereRaw('LOWER(name) = ?', ['haruhay'])->select('id')))
            ->select(['id', 'property_id', 'unit_no', 'floor', 'unit_type', 'area', 'capacity', 'rent_basis', 'monthly_rent', 'shared_rate', 'room_condition', 'status'])
            ->with('property:id,name,status')
            ->withSum([
                'tenants as occupants_count' => fn ($query) => $query->whereIn('status', HaruhayTenant::LIVE_STATUSES),
                'tenants as reservations_count' => fn ($query) => $query->where('status', 'Pending Move-in'),
            ], 'capacity_slots')
            ->orderBy('id')->get();

        $properties = HaruhayProperty::query()
            ->whereIn('division_id', Division::whereRaw('LOWER(name) = ?', ['haruhay'])->select('id'))
            ->orderBy('id')->get(['id', 'name', 'status']);

        return HaruhayPublicRoomResource::collection($units)->additional([
            'meta' => ['count' => $units->count(), 'updated_at' => now()->toIso8601String(), 'properties' => $properties->map(fn ($property) => [
                'id' => $property->id, 'name' => $property->name, 'status' => $property->status,
                'can_reserve' => in_array($property->status, ['Active', 'Operational'], true),
            ])->all()],
        ]);
    }
}
