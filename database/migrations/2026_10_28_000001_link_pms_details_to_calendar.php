<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_pms_details', function (Blueprint $table): void {
            $table->time('start_time')->nullable();
            $table->dateTime('expected_end_at')->nullable();
            $table->unsignedInteger('reminder_minutes')->default(60);
        });
        Schema::table('shipping_calendar_events', function (Blueprint $table): void {
            $table->foreignId('pms_detail_id')->nullable()->unique()->constrained('shipping_pms_details')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_calendar_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pms_detail_id');
        });
        Schema::table('shipping_pms_details', function (Blueprint $table): void {
            $table->dropColumn(['start_time', 'expected_end_at', 'reminder_minutes']);
        });
    }
};
