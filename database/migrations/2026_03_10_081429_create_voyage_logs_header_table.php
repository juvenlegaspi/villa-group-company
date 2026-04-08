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
        Schema::create('voyage_logs_details', function (Blueprint $table) {
            $table->id('dtl_id');
            $table->unsignedBigInteger('voyage_id');
            $table->string('voyage_status')->nullable();
            $table->string('activity')->nullable();
            $table->text('remarks')->nullable();
            $table->dateTime('date_time_started')->nullable();
            $table->dateTime('date_time_ended')->nullable();
            $table->decimal('total_hours',8,2)->nullable();
            $table->date('date_complete')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->foreign('voyage_id')->references('voyage_id')->on('voyage_logs_header')->onDelete('cascade');
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
