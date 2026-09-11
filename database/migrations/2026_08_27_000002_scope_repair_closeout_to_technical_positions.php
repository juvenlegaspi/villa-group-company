<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TECHNICAL_ACTION_PERMISSIONS = [
        'tech_defects.update_closeout',
        'tech_defects.upload_evidence',
        'tech_defects.submit',
    ];

    public function up(): void
    {
        $standardUserRoleId = DB::table('access_roles')->where('slug', 'standard-user')->value('id');
        if (! $standardUserRoleId) return;

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', self::TECHNICAL_ACTION_PERMISSIONS)
            ->pluck('id');

        DB::table('access_role_permission')
            ->where('access_role_id', $standardUserRoleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }

    public function down(): void
    {
        $standardUserRoleId = DB::table('access_roles')->where('slug', 'standard-user')->value('id');
        if (! $standardUserRoleId) return;

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', self::TECHNICAL_ACTION_PERMISSIONS)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('access_role_permission')->insertOrIgnore([
                'access_role_id' => $standardUserRoleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
};
