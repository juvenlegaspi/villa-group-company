<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vessel;
use Illuminate\Database\Eloquent\Builder;

class VesselAccessService
{
    /** Positions that own company-wide vessel visibility inside Villa Shipping. */
    private const ALL_VESSEL_POSITION_CODES = [
        'operations-manager',
        'operation-manager',
        'marine-operations-manager',
        'vessel-manager',
        'technical-manager',
        'liaison-officer',
    ];

    public function canAccessAllVessels(User $user): bool
    {
        if ($user->isExecutiveViewer()) {
            return true;
        }

        $user->loadMissing(['accessRole', 'position']);

        return $user->role === 'admin'
            || (bool) $user->is_admin
            || in_array($user->accessRole?->slug, ['system-administrator', 'company-administrator'], true)
            || $user->hasPermission('vessels.view_all')
            || in_array($user->position?->code, self::ALL_VESSEL_POSITION_CODES, true);
    }

    public function scopeAccessible(Builder $query, User $user): Builder
    {
        if ($this->canAccessAllVessels($user)) {
            return $query;
        }

        return $query->where(function (Builder $vessels) use ($user): void {
            $vessels->whereHas('userAssignments', function (Builder $assignments) use ($user): void {
                $assignments->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->where(fn (Builder $dates) => $dates->whereNull('effective_from')->orWhereDate('effective_from', '<=', today()))
                    ->where(fn (Builder $dates) => $dates->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()));
            })->orWhere(function (Builder $legacy) use ($user): void {
                // Compatibility for databases that have not yet backfilled a
                // particular legacy captain record.
                $legacy->where('captain_id', $user->id)
                    ->whereDoesntHave('userAssignments', fn (Builder $assignments) => $assignments->where('user_id', $user->id));
            });
        });
    }

    public function canAccess(User $user, Vessel|int $vessel): bool
    {
        $vesselId = $vessel instanceof Vessel ? $vessel->getKey() : $vessel;

        return $this->scopeAccessible(Vessel::query(), $user)->whereKey($vesselId)->exists();
    }

    public function authorize(User $user, Vessel|int $vessel): void
    {
        abort_unless($this->canAccess($user, $vessel), 403, 'You are not assigned to this vessel.');
    }
}
