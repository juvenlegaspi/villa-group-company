<?php

namespace App\Services;

use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HaruhayTenantService
{
    public function save(array $data, int $divisionId, ?HaruhayTenant $tenant = null, string $action = 'save', bool $contractTransfer = false, bool $reservationTransition = false): HaruhayTenant
    {
        return DB::transaction(function () use ($data, $divisionId, $tenant, $action, $contractTransfer, $reservationTransition) {
            $record = $tenant ? HaruhayTenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail() : new HaruhayTenant;
            $booking = $record->exists ? \App\Models\HaruhayReservation::where('tenant_id', $record->id)->first() : null;
            if ($booking && ! $reservationTransition) {
                if ($action === 'confirm' && $booking->status !== 'Confirmed') $this->invalid('status', 'Verify the online reservation advance before confirming move-in.');
                if ($action === 'confirm' && ! in_array($record->unit->property->status, ['Active', 'Operational'], true)) $this->invalid('status', 'The property must be operational before actual move-in.');
                if ($action === 'cancel' || ($data['status'] ?? null) === 'Cancelled') $this->invalid('status', 'Manage cancellation from Online Reservations so the hold and payment history remain consistent.');
                if (isset($data['unit_id']) && (int) $data['unit_id'] !== (int) $record->unit_id && $record->status === 'Pending Move-in') $this->invalid('unit_id', 'Resolve or cancel the online reservation before changing its assigned room.');
            }
            if (in_array($action, ['confirm', 'cancel'], true)) {
                if (! $record->exists || $record->status !== 'Pending Move-in') $this->invalid('status', 'This reservation is no longer pending. Refresh the page.');
                $data = $record->only(['unit_id', 'name', 'contact_number', 'email', 'emergency_contact_name', 'emergency_contact_number', 'remarks']);
                $data += ['status' => $action === 'confirm' ? 'Active' : 'Cancelled', 'move_in_date' => $action === 'confirm' ? today()->toDateString() : null, 'move_out_date' => null];
            } elseif ($record->exists) {
                if ($record->status === 'Cancelled' && $data['status'] !== 'Cancelled') $this->invalid('status', 'Create a new reservation for a cancelled tenant record.');
                if ($record->status === 'Pending Move-in' && ! in_array($data['status'], ['Pending Move-in', 'Cancelled'], true)) $this->invalid('status', 'Use Confirm Move-in to activate this reservation.');
                if ($record->status !== 'Pending Move-in' && $data['status'] === 'Pending Move-in') $this->invalid('status', 'Create a new reservation instead of changing an existing stay to pending.');
                if ($data['status'] === 'Cancelled' && ! in_array($record->status, ['Pending Move-in', 'Cancelled'], true)) $this->invalid('status', 'Only a pending reservation can be cancelled. Use Moved Out for an existing stay.');
            }
            if ($data['status'] === 'Pending Move-in') {
                $data['scheduled_move_in_date'] = $data['move_in_date'];
                $data['move_in_date'] = null;
            }
            $data['occupant_count'] = (int) ($data['occupant_count'] ?? $record->occupant_count ?? 1);
            $data['capacity_slots'] = (int) ($data['capacity_slots'] ?? ($record->exists && $booking ? $record->capacity_slots : $data['occupant_count']));
            if ($data['occupant_count'] < 1 || $data['occupant_count'] > 999) $this->invalid('occupant_count', 'Enter a valid number of occupants.');
            if ($data['capacity_slots'] < $data['occupant_count'] || $data['capacity_slots'] > 999) $this->invalid('occupant_count', 'Held capacity must cover every occupant.');
            if ($data['status'] === 'Cancelled') {
                if (! $record->exists) $this->invalid('status', 'Create a pending reservation before cancelling it.');
                $data['move_in_date'] = null;
            }
            $before = $record->exists ? $record->attributesToArray() + [
                'room_no' => $record->unit->unit_no, 'property_name' => $record->unit->property->name,
            ] : null;
            $oldUnitId = $record->unit_id;
            if ($record->exists && (int)$oldUnitId !== (int)$data['unit_id'] && !$contractTransfer && $record->contracts()->whereNull('terminated_on')->whereDate('ends_on','>=',today())->exists()) $this->invalid('unit_id','This tenant has a lease contract. Use Room Transfer / Amendment to preserve the agreed room and rent history.');
            $unitIds = array_values(array_unique(array_filter([$oldUnitId, (int) $data['unit_id']])));
            sort($unitIds);
            $rooms = HaruhayUnit::whereIn('id', $unitIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($rooms as $room) abort_unless((int) $room->property->division_id === $divisionId, 404);
            $room = $rooms->get($data['unit_id']);
            if (! $room) $this->invalid('unit_id', 'The selected room is no longer available.');
            if ($booking && $contractTransfer && (int) $oldUnitId !== (int) $room->id) $data['capacity_slots'] = $data['occupant_count'];
            $holding = in_array($data['status'], HaruhayTenant::HELD_STATUSES, true);
            $sameRoom = $record->exists && (int) $oldUnitId === (int) $room->id;
            $existingResident = $sameRoom && in_array($record->status, HaruhayTenant::LIVE_STATUSES, true);
            if ($holding) {
                if (! $existingResident && $room->room_condition !== 'Ready') $this->invalid('unit_id', 'Select a Ready room for a new reservation or move-in.');
                $holds = HaruhayTenant::where('unit_id', $room->id)->whereIn('status', HaruhayTenant::HELD_STATUSES)->orderBy('id')->lockForUpdate()->get();
                $heldCount = $holds->count();
                if (in_array($room->status, ['Occupied', 'Reserved'], true) && $heldCount === 0) $this->invalid('unit_id', 'This room has an existing occupant or reservation record. Reconcile it before assigning a tenant.');
                $others = $holds->where('id', '!=', $record->id ?? 0)->sum('capacity_slots');
                if ($data['occupant_count'] > ($room->capacity ?? 1) || $others + $data['capacity_slots'] > ($room->capacity ?? 1)) $this->invalid('unit_id', 'All room slots are occupied or reserved.');
            }
            $record->fill($data);
            $record->updated_by = auth()->id();
            if (! $record->exists) $record->created_by = auth()->id();
            $record->save();
            foreach ($rooms as $affected) {
                $holds = HaruhayTenant::where('unit_id', $affected->id)->whereIn('status', HaruhayTenant::HELD_STATUSES)->orderBy('id')->lockForUpdate()->get();
                $occupants = $holds->whereIn('status', HaruhayTenant::LIVE_STATUSES)->sortBy('move_in_date')->values();
                $reservations = $holds->where('status', 'Pending Move-in')->count();
                $removedHold = $before && (int) $oldUnitId === (int) $affected->id && in_array($before['status'], HaruhayTenant::HELD_STATUSES, true);
                if ($occupants->isEmpty() && ! $reservations && in_array($affected->status, ['Occupied', 'Reserved'], true) && ! $removedHold) continue;
                $affected->update([
                    'status' => $occupants->isNotEmpty() ? 'Occupied' : ($reservations ? 'Reserved' : 'Vacant'),
                    'occupied_by' => $occupants->isNotEmpty() ? $occupants->map(fn ($tenant) => $tenant->name.($tenant->occupant_count > 1 ? ' ('.$tenant->occupant_count.' occupants)' : ''))->implode("\n") : null,
                    'move_in_date' => $occupants->first()?->move_in_date,
                ]);
            }
            $record->audits()->create([
                'user_id' => auth()->id(), 'action' => $action === 'save' ? ($before ? 'updated' : 'created') : ($action === 'confirm' ? 'move_in_confirmed' : 'reservation_cancelled'), 'before' => $before,
                'after' => $record->fresh()->attributesToArray() + ['room_no' => $room->unit_no, 'property_name' => $room->property->name],
                'created_at' => now(),
            ]);
            if ($booking && $action === 'confirm') {
                $booking->update(['status' => 'MovedIn']);
                $booking->events()->create(['actor_id' => auth()->id(), 'action' => 'move_in_confirmed', 'status' => 'MovedIn', 'message' => 'Actual move-in was confirmed by staff.', 'created_at' => now()]);
            }
            return $record;
        }, 3);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message])->errorBag('tenantForm');
    }
}
