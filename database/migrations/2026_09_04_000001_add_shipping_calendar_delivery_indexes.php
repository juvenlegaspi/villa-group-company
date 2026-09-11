<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_calendar_events', function (Blueprint $table): void {
            $table->index(['status', 'starts_at'], 'ship_cal_status_start_idx');
            $table->index(['status', 'recurrence_frequency', 'recurrence_ends_on'], 'ship_cal_recurrence_due_idx');
        });
        Schema::table('shipping_calendar_reminder_logs', function (Blueprint $table): void {
            $table->index(['status', 'attempts', 'updated_at'], 'ship_cal_delivery_retry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_calendar_reminder_logs', fn (Blueprint $table) => $table->dropIndex('ship_cal_delivery_retry_idx'));
        Schema::table('shipping_calendar_events', function (Blueprint $table): void {
            $table->dropIndex('ship_cal_status_start_idx');
            $table->dropIndex('ship_cal_recurrence_due_idx');
        });
    }
};
