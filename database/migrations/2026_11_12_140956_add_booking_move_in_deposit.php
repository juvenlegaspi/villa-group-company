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
        Schema::table('haruhay_online_reservations', fn (Blueprint $table) => $table->decimal('deposit_required', 12, 2)->nullable());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('haruhay_online_reservations', fn (Blueprint $table) => $table->dropColumn('deposit_required'));
    }
};
