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
        Schema::create('activity_status_voyage', function (Blueprint $table) {
            $table->id(); // auto increment

            $table->string('name');
            $table->string('description')->nullable();

            $table->boolean('status')->default(1); // active/inactive

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_status_voyage');
    }
};
