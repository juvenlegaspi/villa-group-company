<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CompanyFixedAssetPermissions
{
    public function sync(): void
    {
        foreach (['jmv' => 'JMV', 'shipping' => 'Villa shipping Lines'] as $key => $company) {
            foreach (['view', 'create', 'update', 'dispose'] as $action) {
                DB::table('permissions')->updateOrInsert(['slug' => $key.'.assets.'.$action], ['name' => ucfirst($action).' '.$company.' fixed assets', 'module' => $key, 'updated_at' => now(), 'created_at' => now()]);
            }
            $divisionId = DB::table('divisions')->whereRaw('LOWER(name) = ?', [strtolower($company)])->value('id');
            if (! $divisionId) {
                continue;
            }
            foreach (DB::table('positions')->where('division_id', $divisionId)->get() as $position) {
                $actions = match ($position->code) {
                    'inventory-manager' => ['view', 'create', 'update', 'dispose'],
                    'warehouse-staff' => ['view', 'create', 'update'],
                    'technical-manager', 'maintenance-technician', 'maintenance-manager', 'equipment-technician' => ['view', 'update'],
                    'procurement-manager', 'purchaser', 'finance-manager', 'accountant' => ['view'],
                    default => [],
                };
                $slugs = array_map(fn ($action) => $key.'.assets.'.$action, $actions);
                if ($key === 'shipping' && $actions) {
                    $slugs[] = 'shipping.inventory.access';
                }
                foreach (DB::table('permissions')->whereIn('slug', $slugs)->pluck('id') as $permissionId) {
                    DB::table('permission_position')->insertOrIgnore(['position_id' => $position->id, 'permission_id' => $permissionId]);
                }
            }
        }
    }
}
