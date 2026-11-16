<?php

namespace App\Services;

use App\Models\User;

class VillaGroupAccessService
{
    public function canAccess(User $user, string $module): bool
    {
        if ($user->isSystemAdministrator()) return true;
        if ($module === 'properties' && $user->isExecutiveViewer()) return true;
        if ($module === 'it' || $user->isExecutiveViewer()) return false;
        $user->loadMissing(['division', 'department', 'position']);
        if (strtolower(trim((string) $user->division?->name)) !== 'villa group') return false;
        return match ($module) {
            'properties' => $user->position?->is_active && (int) $user->position?->division_id === (int) $user->division_id && $user->position?->code === 'property-specialist',
            'hr' => $user->role === 'hr' || in_array(strtolower(trim((string) $user->department?->name)), ['hr', 'human resources'], true),
            default => false,
        };
    }

    public function canManageProperties(User $user): bool
    {
        return ! $user->isExecutiveViewer() && $this->canAccess($user, 'properties');
    }
}
