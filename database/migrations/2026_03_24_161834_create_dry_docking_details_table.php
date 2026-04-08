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
        Schema::create('dry_docking_details', function (Blueprint $table) {
            $table->id();
            // relations
            $table->unsignedBigInteger('vessel_id');
            $table->unsignedBigInteger('dry_dock_id');
            // main fields
            $table->text('scope_of_work');
            $table->integer('plan_duration')->nullable();
            $table->integer('actual_duration')->nullable();
            // status (overall)
            $table->string('status')->nullable(); 
            // values: completed, near completion, not started, ongoing
            // daily status
            $table->string('daily_status')->nullable(); 
            // values: ahead of schedule, not started, delayed
            // progress
            $table->decimal('weight', 5, 2)->nullable(); // %
            $table->decimal('actual_progress', 5, 2)->nullable(); // %
            // extra
            $table->string('activity')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            // foreign keys
            $table->foreign('vessel_id')->references('id')->on('vessels')->cascadeOnDelete();
            $table->foreign('dry_dock_id')->references('id')->on('dry_docking_headers')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dry_docking_details');
    }
};
