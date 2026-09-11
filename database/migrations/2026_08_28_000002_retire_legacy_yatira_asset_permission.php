<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'yatira.assets.manage')->value('id');
        if (! $permissionId) {
            return;
        }

        DB::transaction(function () use ($permissionId): void {
            DB::table('permission_position')->where('permission_id', $permissionId)->delete();
            DB::table('access_role_permission')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        });
    }

    public function down(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'yatira.assets.manage'],
            [
                'name' => 'Create and update Yatira fixed assets',
                'module' => 'yatira',
                'description' => 'Legacy permission retained only for rollback compatibility.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
