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
        Schema::create('voyage_logs', function (Blueprint $table) {
            $table->id();

            // CONNECT TO VESSEL
            $table->foreignId('vessel_id')
                ->constrained()
                ->onDelete('cascade');

            $table->date('date_started')->nullable();
            $table->date('date_completed')->nullable();
            $table->string('port_location')->nullable();
            $table->string('voyage_number')->nullable();
            $table->string('fuel_rob')->nullable();
            $table->string('cargo_type')->nullable();
            $table->string('cargo_volume')->nullable();
            $table->integer('crew_on_board')->nullable();
            $table->string('voyage_status')->nullable();
            $table->string('activity')->nullable();
            $table->time('time_started')->nullable();
            $table->time('time_finished')->nullable();
            $table->decimal('total_hrs', 8, 2)->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voyage_logs');
    }
};
