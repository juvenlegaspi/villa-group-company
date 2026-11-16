<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $division = DB::table('divisions')->whereRaw('LOWER(name) = ?', ['haruhay'])->value('id');
        if (! $division) {
            return;
        }
        $department = DB::table('departments')->where('division_id', $division)->where('name', 'Property Management')->value('id');
        if (! $department) {
            $department = DB::table('departments')->insertGetId(['division_id' => $division, 'name' => 'Property Management', 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (\App\Services\HaruhayAccessService::PRESETS as $code => [$name,$permissions]) {
            DB::table('positions')->insertOrIgnore(['division_id' => $division, 'department_id' => $department, 'code' => $code, 'name' => $name, 'legacy_role' => 'staff', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $position = DB::table('positions')->where('division_id', $division)->where('department_id', $department)->where('code', $code)->value('id');
            foreach ($permissions as $suffix) {
                $slug = 'haruhay.'.$suffix;
                DB::table('permissions')->insertOrIgnore(['slug' => $slug, 'name' => 'Haruhay '.str_replace('.', ' ', $suffix), 'module' => 'haruhay', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('permission_position')->insertOrIgnore(['position_id' => $position, 'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id')]);
            }
        }
    }

    public function down(): void
    { /* Keep assigned positions and permissions to avoid silently changing user access. */
    }
};
