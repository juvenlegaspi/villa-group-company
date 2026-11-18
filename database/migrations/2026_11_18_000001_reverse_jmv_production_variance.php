<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the original target for historical logs; do not infer missing targets.
        DB::table('jmv_daily_production_logs')->whereNotNull('daily_target_bagons')->update([
            'excess_deficit' => DB::raw('CAST(coal_output AS SIGNED) - CAST(daily_target_bagons AS SIGNED)'),
        ]);
    }

    public function down(): void
    {
        DB::table('jmv_daily_production_logs')->whereNotNull('daily_target_bagons')->update([
            'excess_deficit' => DB::raw('CAST(daily_target_bagons AS SIGNED) - CAST(coal_output AS SIGNED)'),
        ]);
    }
};
