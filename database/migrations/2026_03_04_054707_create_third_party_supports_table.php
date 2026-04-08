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
        Schema::create('third_party_supports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tech_defect_id');

            $table->string('technician')->nullable();
            $table->string('spares_required')->nullable();
            $table->string('tools_required')->nullable();
            $table->string('status')->default('PENDING');

            $table->timestamps();

            $table->foreign('tech_defect_id')
                ->references('id')
                ->on('tech_defects')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('third_party_supports');
    }
};
