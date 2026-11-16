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
        Schema::table('haruhay_tenants', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacity_slots')->default(1);
        });
        \Illuminate\Support\Facades\DB::table('haruhay_tenants')->update(['capacity_slots' => \Illuminate\Support\Facades\DB::raw('occupant_count')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('haruhay_tenants', fn (Blueprint $table) => $table->dropColumn('capacity_slots'));
    }
};
