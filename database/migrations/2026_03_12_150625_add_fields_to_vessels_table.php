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
        Schema::table('vessels', function (Blueprint $table) {
        $table->string('vessel_type')->nullable();
        $table->string('dwt')->nullable();
        $table->string('fuel_type')->nullable();
        $table->string('service_speed')->nullable();
        $table->string('charter_type')->nullable();
        $table->string('vessel_status')->nullable();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
        $table->dropColumn([
            'vessel_type',
            'dwt',
            'fuel_type',
            'service_speed',
            'charter_type',
            'vessel_status'
        ]);
    });
    }
};
