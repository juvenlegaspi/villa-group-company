<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Older versions completed the whole recurring series. Recurring
        // schedules now keep their master event active and complete dates
        // individually in shipping_calendar_occurrence_completions.
        DB::table('shipping_calendar_events')
            ->whereNotNull('recurrence_frequency')
            ->where('status', 'completed')
            ->update([
                'status' => 'scheduled',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // The previous global-completion intent cannot be reconstructed safely.
    }
};
