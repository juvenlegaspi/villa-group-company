<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jmv_daily_production_logs', fn (Blueprint $table) => $table->unsignedInteger('daily_target_bagons')->nullable());
    }

    public function down(): void
    {
        Schema::table('jmv_daily_production_logs', fn (Blueprint $table) => $table->dropColumn('daily_target_bagons'));
    }
};
