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
        Schema::create('voyage_logs_header', function (Blueprint $table) {
            $table->id('voyage_id');
            $table->date('date_created')->nullable();
            $table->string('cargo_type')->nullable();
            $table->string('cargo_volume')->nullable();
            $table->string('port_location')->nullable();
            $table->string('voyage_no')->nullable();
            $table->integer('crew_on_board')->nullable();
            $table->string('fuel_rob')->nullable();
            $table->string('status')->nullable();
            $table->integer('created_by')->nullable();
            $table->unsignedBigInteger('vessel_id');
            $table->date('arrival_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voyage_logs_header');
    }
};
