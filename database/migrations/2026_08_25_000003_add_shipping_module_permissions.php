<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'shipping.operations.access' => 'Access Villa Shipping operations',
        'shipping.vessel_management.access' => 'Access vessel management',
        'shipping.voyages.access' => 'Access voyage and fuel records',
        'shipping.technical_defects.access' => 'Access technical defect monitoring',
        'shipping.certificates.access' => 'Access vessel certificates',
        'shipping.dry_docking.access' => 'Access dry docking monitoring',
        'shipping.procurement.access' => 'Access Villa Shipping procurement',
        'shipping.inventory.access' => 'Access Villa Shipping inventory',
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $name,
                'module' => 'shipping',
                'description' => $name.' based on the employee department and position.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id', 'slug');
        foreach (['system-administrator', 'company-administrator'] as $roleSlug) {
            $roleId = DB::table('access_roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) continue;
            foreach ($permissionIds as $permissionId) {
                DB::table('access_role_permission')->insertOrIgnore(['access_role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }

        $shippingId = DB::table('divisions')->whereRaw('LOWER(name) = ?', ['villa shipping lines'])->value('id');
        if (! $shippingId) return;

        foreach (DB::table('positions')->join('departments', 'departments.id', '=', 'positions.department_id')
            ->where('positions.division_id', $shippingId)
            ->select(['positions.id', 'departments.name as department_name'])->get() as $position) {
            $slugs = $this->permissionsForDepartment((string) $position->department_name);
            foreach ($slugs as $slug) {
                if (isset($permissionIds[$slug])) {
                    DB::table('permission_position')->insertOrIgnore(['position_id' => $position->id, 'permission_id' => $permissionIds[$slug]]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('access_role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permission_position')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    private function permissionsForDepartment(string $department): array
    {
        return match (strtolower(trim($department))) {
            'marine operations', 'technical department' => [
                'shipping.operations.access', 'shipping.vessel_management.access', 'shipping.voyages.access',
                'shipping.technical_defects.access', 'shipping.certificates.access', 'shipping.dry_docking.access',
            ],
            'safety and compliance' => [
                'shipping.operations.access', 'shipping.technical_defects.access', 'shipping.certificates.access',
            ],
            'procurement' => ['shipping.procurement.access'],
            'inventory and warehouse' => ['shipping.inventory.access'],
            default => [],
        };
    }
};
