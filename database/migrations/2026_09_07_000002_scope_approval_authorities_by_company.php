<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_authorities')) {
            return;
        }

        $shippingId = DB::table('divisions')
            ->whereRaw('LOWER(name) = ?', ['villa shipping lines'])
            ->value('id');

        DB::table('approval_authorities')
            ->where('is_active', true)
            ->where(function ($query) use ($shippingId): void {
                if ($shippingId) {
                    $query->where('division_id', '!=', $shippingId)
                        ->orWhere('module', '!=', 'technical_defects');
                } else {
                    $query->whereRaw('1 = 1');
                }
            })
            ->update([
                'is_active' => false,
                'effective_until' => today(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally does not reactivate historical authority records.
    }
};
