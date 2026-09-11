<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('access_roles')->where('slug', 'standard-user')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'tech_defects.perform_action')->value('id');
        if ($roleId && $permissionId) DB::table('access_role_permission')->where(['access_role_id' => $roleId, 'permission_id' => $permissionId])->delete();
    }

    public function down(): void
    {
        $roleId = DB::table('access_roles')->where('slug', 'standard-user')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'tech_defects.perform_action')->value('id');
        if ($roleId && $permissionId) DB::table('access_role_permission')->insertOrIgnore(['access_role_id' => $roleId, 'permission_id' => $permissionId]);
    }
};
