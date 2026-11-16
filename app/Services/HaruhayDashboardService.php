<?php

namespace App\Services;

use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;

class HaruhayDashboardService
{
    public function __construct(private HaruhayReportService $reports) {}

    public function build(int $divisionId, array $filters): array
    {
        $report = $this->reports->build($divisionId, $filters + ['type' => 'overview', 'group_by' => 'month']);
        $propertyId = $filters['property_id'] ?? null;
        $scope = fn ($query) => $query->where('division_id', $divisionId)->when($propertyId, fn ($query) => $query->whereKey($propertyId));
        $units = HaruhayUnit::whereHas('property', $scope)->with('property')->withSum([
            'tenants as occupants_count' => fn ($query) => $query->whereIn('status', HaruhayTenant::LIVE_STATUSES),
            'tenants as reservations_count' => fn ($query) => $query->where('status', 'Pending Move-in'),
        ], 'capacity_slots')->get();
        $available = $units->filter(fn ($unit) => in_array($unit->property->status, ['Active', 'Operational'], true) && $unit->room_condition === 'Ready');
        $operations = [
            'availableSlots' => $available->sum(fn ($unit) => $unit->available_slots),
            'readyVacant' => $available->where('status', 'Vacant')->filter(fn ($unit) => $unit->available_slots > 0)->count(),
            'pendingMoveIns' => HaruhayTenant::whereHas('unit.property', $scope)->where('status', 'Pending Move-in')->count(),
            'maintenance' => $units->where('room_condition', 'Under Maintenance')->count(),
            'unavailable' => $units->whereIn('room_condition', ['Under Construction', 'Out of Service'])->count(),
            'onlineRequests' => \App\Models\HaruhayReservation::whereHas('unit.property', $scope)->where('status', 'PendingReview')->count(),
            'proofReviews' => \App\Models\HaruhayReservation::whereHas('unit.property', $scope)->where('status', 'PaymentReview')->count(),
            'partialReservations' => \App\Models\HaruhayReservation::whereHas('unit.property', $scope)->where('status', 'PartiallyPaid')->count(),
            'activeOccupants' => (int) HaruhayTenant::whereHas('unit.property', $scope)->whereIn('status', HaruhayTenant::LIVE_STATUSES)->sum('occupant_count'),
            'pendingOccupants' => (int) HaruhayTenant::whereHas('unit.property', $scope)->where('status', 'Pending Move-in')->sum('occupant_count'),
        ];
        $overdue = array_sum(array_slice(array_values($report['aging']), 1));
        $properties = HaruhayProperty::where('division_id', $divisionId)->orderBy('name')->get(['id', 'name']);
        $chartData = [
            'trend' => array_values($report['trend']),
            'units' => $report['unitStatus'],
            'aging' => $report['aging'],
            'totalUnits' => $report['totalUnits'],
            'occupancyRate' => $report['occupancyRate'],
        ];

        return compact('report', 'operations', 'overdue', 'properties', 'chartData');
    }
}
