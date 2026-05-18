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
        Schema::create('activity_voyage', function (Blueprint $table) {
            $table->id();

            // FK padulong sa status table
            $table->foreignId('activity_status_voyage_id')
                ->constrained('activity_status_voyage')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('description')->nullable();

            $table->boolean('status')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_voyage');
    }
};
