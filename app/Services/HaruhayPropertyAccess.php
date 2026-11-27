<?php

namespace App\Services;

use App\Models\HaruhayProperty;
use App\Models\HaruhayReservation;
use App\Models\User;
use App\Notifications\HaruhayReservationRequested;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class HaruhayPropertyAccess
{
    private const MODEL_FILTERS = [
        'HaruhayProperty' => ['id', 'properties'],
        'HaruhayUnit' => ['property_id', 'properties'],
        'HaruhayPropertyAttachment' => ['property_id', 'properties'],
        'HaruhayCollection' => ['property_id', 'properties'],
        'HaruhayOccupancyEvent' => ['property_id', 'properties'],
        'HaruhayTenant' => ['unit_id', 'units'],
        'HaruhayLeaseContract' => ['unit_id', 'units'],
        'HaruhayBilling' => ['unit_id', 'units'],
        'HaruhayReservation' => ['unit_id', 'units'],
        'HaruhayDepositEntry' => ['tenant_id', 'tenants'],
        'HaruhayTenantAudit' => ['tenant_id', 'tenants'],
        'HaruhayBillingPayment' => ['billing_id', 'billings'],
        'HaruhayBillingDelivery' => ['billing_id', 'billings'],
        'HaruhayDueReminder' => ['billing_id', 'billings'],
        'HaruhayCollectionDelivery' => ['collection_id', 'collections'],
        'HaruhayReservationPayment' => ['reservation_id', 'reservations'],
        'HaruhayReservationEvent' => ['reservation_id', 'reservations'],
        'HaruhayTrackingSms' => ['reservation_id', 'reservations'],
    ];

    public function restricted(?User $user): bool
    {
        if (! $user || $user->isSystemAdministrator() || $user->isExecutiveViewer()) {
            return false;
        }
        $user->loadMissing(['division', 'position']);
        if (strcasecmp((string) $user->division?->name, 'Haruhay') !== 0) {
            return false;
        }
        $position = $user->position;

        return ! ($position?->is_active && $position->code === 'haruhay-property-manager'
            && (int) $position->division_id === (int) $user->division_id
            && (int) $position->department_id === (int) $user->department_id);
    }

    public function propertyIds(User $user): QueryBuilder
    {
        return DB::table('haruhay_property_user as assignment')
            ->join('haruhay_properties as property', 'property.id', '=', 'assignment.property_id')
            ->join('divisions as company', 'company.id', '=', 'property.division_id')
            ->where('assignment.user_id', $user->id)->where('assignment.is_active', true)
            ->whereRaw('LOWER(company.name) = ?', ['haruhay'])->select('assignment.property_id');
    }

    public function canView(User $user, int $propertyId): bool
    {
        return ! $this->restricted($user) || $this->propertyIds($user)->where('assignment.property_id', $propertyId)->exists();
    }

    public function scope(Builder $query): void
    {
        $user = auth()->user();
        if (! $this->restricted($user)) {
            return;
        }
        [$column, $records] = self::MODEL_FILTERS[class_basename($query->getModel())];
        $query->whereIn($query->getModel()->qualifyColumn($column), $this->visibleIds($records, $user));
        if (in_array(class_basename($query->getModel()), ['HaruhayBilling', 'HaruhayLeaseContract', 'HaruhayCollection'], true)) {
            $query->whereIn($query->getModel()->qualifyColumn('tenant_id'), $this->visibleIds('tenants', $user));
        }
    }

    /**
     * Build independent ID sets without recursively applying related model scopes.
     */
    private function visibleIds(string $records, User $user): QueryBuilder
    {
        if ($records === 'properties') {
            return $this->propertyIds($user);
        }
        if ($records === 'units') {
            return DB::table('haruhay_units')->whereIn('property_id', $this->propertyIds($user))->select('id');
        }
        if ($records === 'tenants') {
            return DB::table('haruhay_tenants')->whereIn('unit_id', $this->visibleIds('units', $user))->select('id');
        }
        if ($records === 'collections') {
            return DB::table('haruhay_collections')->whereIn('property_id', $this->propertyIds($user))
                ->whereIn('tenant_id', $this->visibleIds('tenants', $user))->select('id');
        }
        if ($records === 'billings') {
            return DB::table('haruhay_billings')->whereIn('unit_id', $this->visibleIds('units', $user))
                ->whereIn('tenant_id', $this->visibleIds('tenants', $user))->select('id');
        }

        return DB::table('haruhay_online_reservations')->whereIn('unit_id', $this->visibleIds('units', $user))->select('id');
    }

    public function authorizeSave(Model $record): void
    {
        $user = auth()->user();
        if (! $this->restricted($user)) {
            return;
        }
        if ($record->exists) {
            abort_unless($record->newQuery()->whereKey($record->getKey())->exists(), 404);
        }
        if ($record instanceof HaruhayProperty) {
            abort_unless($record->exists && $this->canView($user, (int) $record->id), 403);
        } elseif ($record->getAttribute('property_id') !== null) {
            abort_unless($this->canView($user, (int) $record->getAttribute('property_id')), 404);
        }
        foreach (['unit_id' => 'HaruhayUnit', 'tenant_id' => 'HaruhayTenant', 'billing_id' => 'HaruhayBilling',
            'contract_id' => 'HaruhayLeaseContract', 'collection_id' => 'HaruhayCollection', 'reservation_id' => 'HaruhayReservation'] as $field => $parent) {
            if ($record->getAttribute($field) !== null) {
                $model = 'App\\Models\\'.$parent;
                abort_unless($model::whereKey($record->getAttribute($field))->exists(), 404);
            }
        }
    }

    public function filterNotifications(mixed $query, User $user): mixed
    {
        if ($this->restricted($user)) {
            $reservations = DB::table((new HaruhayReservation)->getTable().' as reservation')
                ->join('haruhay_units as unit', 'unit.id', '=', 'reservation.unit_id')
                ->whereIn('unit.property_id', $this->propertyIds($user))->select('reservation.id');
            $query->where(fn ($notification) => $notification->where('type', '!=', HaruhayReservationRequested::class)
                ->orWhereIn('data->reservation_id', $reservations));
        }

        return $query;
    }
}
