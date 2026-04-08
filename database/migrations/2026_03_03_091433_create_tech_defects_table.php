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
        Schema::create('tech_defects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vessel_id')->constrained()->onDelete('cascade');

            $table->string('status')->default('Open');

            $table->date('date_completed')->nullable();
            $table->date('date_identified');

            $table->string('port_location')->nullable();
            $table->string('reported_by')->nullable();
            $table->string('system_affected')->nullable();

            $table->text('defect_description');
            $table->text('initial_cause')->nullable();

            $table->string('severity_level')->nullable(); // Minor, Major, Critical
            $table->string('operational_impact')->nullable(); // Stopped, Limited, None

            $table->string('temporary_repair')->nullable(); // Yes/No
            $table->string('third_party_required')->nullable(); // Yes/No
            $table->text('third_party_reason')->nullable();

            $table->string('spares_required')->nullable(); // Yes/No
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tech_defects');
    }
};
