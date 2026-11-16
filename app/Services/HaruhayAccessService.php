<?php

namespace App\Services;

use App\Models\User;

class HaruhayAccessService
{
    public const PRESETS = [
        'haruhay-property-manager' => ['Property Manager', ['reservations.manage', 'reservations.verify', 'units.manage', 'tenants.manage', 'contracts.manage', 'billing.manage', 'collections.manage', 'collections.reverse', 'deposits.receive', 'deposits.manage', 'transfers.manage', 'reports.view']],
        'haruhay-property-staff' => ['Property Staff', ['reservations.manage', 'units.manage', 'tenants.manage', 'contracts.manage', 'billing.manage', 'transfers.manage', 'reports.view']],
        'haruhay-cashier' => ['Cashier', ['reservations.verify', 'collections.manage', 'deposits.receive', 'reports.view']],
        'haruhay-finance-reviewer' => ['Finance Reviewer', ['collections.reverse', 'deposits.manage', 'reports.view']],
        'haruhay-read-only' => ['Read Only', ['reports.view']],
    ];

    public function allows(User $user, string $permission): bool
    {
        if ($user->isExecutiveViewer()) {
            return false;
        }
        if ($user->isSystemAdministrator()) {
            return true;
        }
        $user->loadMissing(['division', 'position.permissions', 'accessRole.permissions']);
        if (strtolower((string) $user->division?->name) !== 'haruhay') {
            return false;
        }
        $slug = 'haruhay.'.$permission;
        $position = $user->position;
        $positionAllows = $position?->is_active && (int) $position->division_id === (int) $user->division_id && (int) $position->department_id === (int) $user->department_id && $position->permissions->contains('slug', $slug);
        if ($position && array_key_exists($position->code, self::PRESETS)) {
            return (bool) $positionAllows;
        }

        return $positionAllows
            || ($user->accessRole?->is_active && $user->accessRole->permissions->contains('slug', $slug));
    }
}
