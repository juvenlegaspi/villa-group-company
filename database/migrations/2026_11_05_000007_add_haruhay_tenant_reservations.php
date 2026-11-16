<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('haruhay_tenants', function (Blueprint $table) {
            $table->date('move_in_date')->nullable()->change();
            $table->date('scheduled_move_in_date')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('haruhay_tenants', function (Blueprint $table) { $table->dropColumn('scheduled_move_in_date'); });
    }
};
