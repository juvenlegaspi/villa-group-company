<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ([
            ['vessels.view_all', 'View all company vessels', 'View every vessel within the assigned shipping company.'],
            ['vessels.view_assigned', 'View assigned vessels', 'View only vessels explicitly assigned to the user.'],
        ] as [$slug, $name, $description]) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $name,
                'module' => 'vessels',
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $shipping = DB::table('divisions')->whereRaw('LOWER(name) = ?', ['villa shipping lines'])->first();
        if (! $shipping) return;

        $marineId = DB::table('departments')->where('division_id', $shipping->id)
            ->whereRaw('LOWER(name) = ?', ['marine operations'])->value('id');
        if ($marineId) {
            DB::table('positions')->updateOrInsert(
                ['division_id' => $shipping->id, 'department_id' => $marineId, 'code' => 'operations-manager'],
                ['name' => 'Operations Manager', 'legacy_role' => 'manager', 'description' => 'Oversees all Villa Shipping vessel operations.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $allPermission = DB::table('permissions')->where('slug', 'vessels.view_all')->value('id');
        $assignedPermission = DB::table('permissions')->where('slug', 'vessels.view_assigned')->value('id');
        $managerCodes = ['operations-manager', 'operation-manager', 'marine-operations-manager', 'vessel-manager', 'technical-manager'];
        foreach (DB::table('positions')->where('division_id', $shipping->id)->get() as $position) {
            $permissionId = in_array($position->code, $managerCodes, true) ? $allPermission : $assignedPermission;
            DB::table('permission_position')->insertOrIgnore(['position_id' => $position->id, 'permission_id' => $permissionId]);

            $technicalSlugs = match (true) {
                in_array($position->code, ['operations-manager', 'operation-manager', 'marine-operations-manager', 'vessel-manager'], true) => ['tech_defects.view_company', 'tech_defects.review', 'tech_defects.verify'],
                $position->code === 'technical-manager' => ['tech_defects.view_company', 'tech_defects.assess', 'tech_defects.assign_action', 'tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit', 'tech_defects.verify'],
                default => [],
            };
            foreach (DB::table('permissions')->whereIn('slug', $technicalSlugs)->pluck('id') as $technicalPermissionId) {
                DB::table('permission_position')->insertOrIgnore(['position_id' => $position->id, 'permission_id' => $technicalPermissionId]);
            }
        }

        foreach (['system-administrator', 'company-administrator'] as $roleSlug) {
            $roleId = DB::table('access_roles')->where('slug', $roleSlug)->value('id');
            if ($roleId) DB::table('access_role_permission')->insertOrIgnore(['access_role_id' => $roleId, 'permission_id' => $allPermission]);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['vessels.view_all', 'vessels.view_assigned'])->pluck('id');
        DB::table('access_role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_position')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
