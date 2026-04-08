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
        Schema::table('voyage_logs_details', function (Blueprint $table) {
            $table->timestamp('pause_at')->nullable();
            $table->integer('total_pause')->default(0);
            $table->boolean('is_paused')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voyage_details', function (Blueprint $table) {
            //
        });
    }
};
