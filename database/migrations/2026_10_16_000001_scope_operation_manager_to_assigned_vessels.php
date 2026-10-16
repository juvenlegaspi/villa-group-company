<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $shippingId = DB::table('divisions')
            ->whereRaw('LOWER(name) = ?', ['villa shipping lines'])
            ->value('id');

        if (! $shippingId) {
            return;
        }

        $positionIds = DB::table('positions')
            ->where('division_id', $shippingId)
            ->where('code', 'operation-manager')
            ->pluck('id');

        if ($positionIds->isEmpty()) {
            return;
        }

        $removePermissionIds = DB::table('permissions')
            ->whereIn('slug', ['vessels.view_all', 'tech_defects.view_company'])
            ->pluck('id');

        DB::table('permission_position')
            ->whereIn('position_id', $positionIds)
            ->whereIn('permission_id', $removePermissionIds)
            ->delete();

        $assignedPermissionIds = DB::table('permissions')
            ->whereIn('slug', ['vessels.view_assigned', 'tech_defects.view_assigned'])
            ->pluck('id');

        foreach ($positionIds as $positionId) {
            foreach ($assignedPermissionIds as $permissionId) {
                DB::table('permission_position')->insertOrIgnore([
                    'position_id' => $positionId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $shippingId = DB::table('divisions')
            ->whereRaw('LOWER(name) = ?', ['villa shipping lines'])
            ->value('id');

        if (! $shippingId) {
            return;
        }

        $positionIds = DB::table('positions')
            ->where('division_id', $shippingId)
            ->where('code', 'operation-manager')
            ->pluck('id');

        $assignedPermissionIds = DB::table('permissions')
            ->whereIn('slug', ['vessels.view_assigned', 'tech_defects.view_assigned'])
            ->pluck('id');

        DB::table('permission_position')
            ->whereIn('position_id', $positionIds)
            ->whereIn('permission_id', $assignedPermissionIds)
            ->delete();

        $companyWidePermissionIds = DB::table('permissions')
            ->whereIn('slug', ['vessels.view_all', 'tech_defects.view_company'])
            ->pluck('id');

        foreach ($positionIds as $positionId) {
            foreach ($companyWidePermissionIds as $permissionId) {
                DB::table('permission_position')->insertOrIgnore([
                    'position_id' => $positionId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }
};
