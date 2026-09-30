<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $yatiraId = DB::table('divisions')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['yatira'])
            ->value('id');

        if ($yatiraId) {
            DB::table('suppliers')
                ->whereNull('division_id')
                ->update([
                    'division_id' => $yatiraId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // This data repair is intentionally not reversed. Existing suppliers
        // must remain linked to their organization after a rollback.
    }
};
