<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('voyage_activities', function (Blueprint $table) {
            $table->id();

            // FK sa voyage_logs_details
            $table->unsignedBigInteger('voyage_detail_id');

            // FK sa activity_voyage
            $table->unsignedBigInteger('activity_id');

            $table->string('port_location')->nullable();
            $table->dateTime('start_date_time')->nullable();
            $table->dateTime('end_date_time')->nullable();
            $table->decimal('fuel_rob', 10, 2)->nullable();

            $table->timestamps();

            // Foreign Keys (optional pero recommended)
            $table->foreign('voyage_detail_id')
                ->references('dtl_id')
                ->on('voyage_logs_details')
                ->onDelete('cascade');

            $table->foreign('activity_id')
                ->references('id')
                ->on('activity_voyage')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voyage_activities');
    }
};
