<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jmv_production_targets', fn (Blueprint $table) => $table->unsignedBigInteger('weekly_coal_target')->nullable());
        DB::table('jmv_production_targets')->update(['weekly_coal_target' => DB::raw('coal_target * 7')]);
    }

    public function down(): void
    {
        Schema::table('jmv_production_targets', fn (Blueprint $table) => $table->dropColumn('weekly_coal_target'));
    }
};
