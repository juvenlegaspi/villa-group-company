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
            $table->id('activity_id');

            $table->unsignedBigInteger('voyage_id');
            $table->unsignedBigInteger('vessel_id');
            $table->unsignedBigInteger('status_id')->nullable();

            // FK sa voyage_logs_details
            $table->unsignedBigInteger('voyage_detail_id');

            // FK sa activity_voyage
            $table->unsignedBigInteger('status_activity_id')->nullable();

            $table->string('port_location')->nullable();
            $table->dateTime('start_date_time')->nullable();
            $table->dateTime('end_date_time')->nullable();
            $table->decimal('fuel_rob', 10, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('total_hours', 10, 2)->nullable();
            $table->decimal('cargo_load', 12, 2)->nullable();
            $table->decimal('cargo_unload', 12, 2)->nullable();
            $table->decimal('total_unload', 12, 2)->nullable();
            $table->string('load_unit', 20)->nullable();
            $table->string('main_status')->default('ONGOING');
            $table->dateTime('edited_end_date_time')->nullable();
            $table->text('edit_reason')->nullable();
            $table->string('edit_attachment')->nullable();
            $table->dateTime('edited_at')->nullable();

            $table->timestamps();

            // Foreign Keys (optional pero recommended)
            $table->foreign('voyage_detail_id')
                ->references('dtl_id')
                ->on('voyage_logs_details')
                ->onDelete('cascade');

            $table->foreign('status_activity_id')
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
