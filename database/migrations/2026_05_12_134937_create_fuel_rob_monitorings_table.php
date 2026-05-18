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
        Schema::create('fuel_rob_monitorings', function (Blueprint $table) {

            $table->bigIncrements('fuel_id');

            $table->unsignedBigInteger('voyage_id');
            $table->unsignedBigInteger('voyage_detail_id')->nullable();
            $table->unsignedBigInteger('vessel_id');

            $table->decimal('beginning_fuel', 10, 2)->default(0);
            $table->decimal('received_fuel', 10, 2)->default(0);

            $table->unsignedBigInteger('status_id')->nullable();
            $table->unsignedBigInteger('status_activity_id')->nullable();

            $table->decimal('main_engine', 10, 2)->default(0);
            $table->decimal('auxiliary_engine', 10, 2)->default(0);
            $table->decimal('others', 10, 2)->default(0);

            $table->decimal('total_consumed', 10, 2)->default(0);

            $table->decimal('remaining_fuel', 10, 2)->default(0);

            $table->string('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_rob_monitorings');
    }
};
