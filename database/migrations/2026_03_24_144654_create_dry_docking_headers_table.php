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
        Schema::create('dry_docking_headers', function (Blueprint $table) {
            $table->id();
            // relation to vessels table
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->date('arrival_date')->nullable();
            $table->date('docking_date')->nullable();
            $table->integer('laydays')->nullable();
            $table->date('undocking_date')->nullable();
            $table->string('vessel_manager')->nullable();
            // checkbox
            $table->boolean('is_shipyard')->default(0);
            $table->boolean('is_inhouse')->default(0);
            // additional
            $table->date('create_date')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dry_docking_headers');
    }
};
