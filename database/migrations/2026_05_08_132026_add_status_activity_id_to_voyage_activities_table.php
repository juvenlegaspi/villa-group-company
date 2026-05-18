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
        Schema::table('voyage_activities', function (Blueprint $table) {
    $table->bigInteger('status_activity_id')->nullable()->after('status_id');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voyage_activities', function (Blueprint $table) {
            //
        });
    }
};
