<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shipping_calendar_events', 'pms_detail_id')) {
            if (DB::table('shipping_calendar_events')->whereNotNull('pms_detail_id')->groupBy('pms_detail_id')->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Multiple calendar events reference the same PMS detail. Review these links before adding the unique index. No records were deleted.');
            }
            if (DB::table('shipping_calendar_events')->whereNotNull('pms_detail_id')->whereNotIn('pms_detail_id', DB::table('shipping_pms_details')->select('id'))->exists()) {
                throw new RuntimeException('Calendar events reference missing PMS details. Review these links before adding the foreign key. No records were deleted.');
            }
        }
        if (! Schema::hasColumn('shipping_pms_details', 'start_time')) {
            Schema::table('shipping_pms_details', fn (Blueprint $table) => $table->time('start_time')->nullable());
        }
        if (! Schema::hasColumn('shipping_pms_details', 'expected_end_at')) {
            Schema::table('shipping_pms_details', fn (Blueprint $table) => $table->dateTime('expected_end_at')->nullable());
        }
        if (! Schema::hasColumn('shipping_pms_details', 'reminder_minutes')) {
            Schema::table('shipping_pms_details', fn (Blueprint $table) => $table->unsignedInteger('reminder_minutes')->default(60));
        }
        if (! Schema::hasColumn('shipping_calendar_events', 'pms_detail_id')) {
            Schema::table('shipping_calendar_events', fn (Blueprint $table) => $table->unsignedBigInteger('pms_detail_id')->nullable());
        }
        $uniqueExists = collect(Schema::getIndexes('shipping_calendar_events'))->contains(fn ($index) => $index['unique'] && $index['columns'] === ['pms_detail_id']);
        if (! $uniqueExists) {
            Schema::table('shipping_calendar_events', fn (Blueprint $table) => $table->unique('pms_detail_id'));
        }
        $foreignKeyExists = collect(Schema::getForeignKeys('shipping_calendar_events'))->contains(fn ($key) => $key['columns'] === ['pms_detail_id'] && $key['foreign_table'] === 'shipping_pms_details' && $key['foreign_columns'] === ['id']);
        if (! $foreignKeyExists) {
            Schema::table('shipping_calendar_events', fn (Blueprint $table) => $table->foreign('pms_detail_id')->references('id')->on('shipping_pms_details')->restrictOnDelete());
        }
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
