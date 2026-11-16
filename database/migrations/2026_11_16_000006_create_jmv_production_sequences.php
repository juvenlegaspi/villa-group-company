<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jmv_production_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jmv_production_sequences');
    }
};
