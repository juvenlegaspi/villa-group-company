<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'tech_defects.request_support')->value('id');
        if (! $permissionId) return;

        $captainPositionIds = DB::table('positions')
            ->where(fn ($query) => $query->where('legacy_role', 'captain')->orWhere('code', 'vessel-captain'))
            ->pluck('id');

        DB::table('permission_position')
            ->where('permission_id', $permissionId)
            ->whereIn('position_id', $captainPositionIds)
            ->delete();
    }

    public function down(): void
    {
        // Intentionally not restored: vessel captains must not manage third-party support.
    }
};
