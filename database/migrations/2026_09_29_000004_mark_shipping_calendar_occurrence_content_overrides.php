<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shipping_calendar_occurrence_overrides', 'has_changes')) {
            Schema::table('shipping_calendar_occurrence_overrides', function (Blueprint $table): void {
                $table->boolean('has_changes')->default(false)->after('occurrence_starts_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shipping_calendar_occurrence_overrides', fn (Blueprint $table) => $table->dropColumn('has_changes'));
    }
};
