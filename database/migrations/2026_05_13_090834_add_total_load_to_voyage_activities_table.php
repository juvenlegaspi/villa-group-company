<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('voyage_activities', 'total_load')) {
            Schema::table('voyage_activities', function (Blueprint $table) {
                $table->decimal('total_load', 12, 2)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('voyage_activities', 'total_load')) {
            Schema::table('voyage_activities', fn (Blueprint $table) => $table->dropColumn('total_load'));
        }
    }
};
