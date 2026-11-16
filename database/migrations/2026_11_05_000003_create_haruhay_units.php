<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('haruhay_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('haruhay_properties')->restrictOnDelete();
            $table->string('unit_no', 50);
            $table->unsignedSmallInteger('floor');
            $table->string('unit_type');
            $table->decimal('area', 12, 2)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('rent_basis', 30)->default('per_room');
            $table->decimal('monthly_rent', 12, 2)->nullable();
            $table->decimal('shared_rate', 12, 2)->nullable();
            $table->text('rate_notes')->nullable();
            $table->string('status', 20)->default('Maintenance');
            $table->text('occupied_by')->nullable();
            $table->date('move_in_date')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'floor', 'unit_no']);
            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('haruhay_units'); }
};
