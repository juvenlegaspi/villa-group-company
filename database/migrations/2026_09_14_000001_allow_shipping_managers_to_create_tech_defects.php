<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['tech_defects.create', 'tech_defects.submit_review'])
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        $positionIds = DB::table('positions as p')
            ->join('divisions as dv', 'dv.id', '=', 'p.division_id')
            ->join('departments as d', 'd.id', '=', 'p.department_id')
            ->whereRaw("LOWER(dv.name) LIKE '%villa%shipping%'")
            ->where(function ($query): void {
                $query->whereIn('p.code', [
                    'operations-manager',
                    'operation-manager',
                    'marine-operations-manager',
                    'vessel-manager',
                    'technical-manager',
                ])->orWhere(function ($managers): void {
                    $managers->whereRaw("LOWER(TRIM(p.legacy_role)) = 'manager'")
                        ->whereIn(DB::raw('LOWER(TRIM(d.name))'), [
                            'marine operation',
                            'marine operations',
                            'technical department',
                        ]);
                });
            })
            ->pluck('p.id');

        foreach ($positionIds as $positionId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_position')->insertOrIgnore([
                    'position_id' => $positionId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally retained: these may become active production grants.
    }
};
