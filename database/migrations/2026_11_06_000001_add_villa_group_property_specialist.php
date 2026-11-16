<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $divisionId = DB::table('divisions')->whereRaw('LOWER(name) = ?', ['villa group'])->value('id');
        if (! $divisionId) return;
        $departmentId = DB::table('departments')->where('division_id', $divisionId)->whereRaw('LOWER(name) = ?', ['property management'])->value('id');
        if (! $departmentId) $departmentId = DB::table('departments')->insertGetId(['division_id' => $divisionId, 'name' => 'Property Management', 'created_at' => now(), 'updated_at' => now()]);
        if (! DB::table('positions')->where('division_id', $divisionId)->where('code', 'property-specialist')->exists()) {
            DB::table('positions')->insert(['division_id' => $divisionId, 'department_id' => $departmentId, 'name' => 'Property Specialist', 'code' => 'property-specialist', 'legacy_role' => 'staff', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
    public function down(): void { /* Preserve organization assignments. */ }
};
