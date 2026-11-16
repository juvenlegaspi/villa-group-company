<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('haruhay_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->string('owner');
            $table->string('type', 50);
            $table->string('street_barangay');
            $table->string('city');
            $table->string('province');
            $table->decimal('lot_area', 14, 2)->nullable();
            $table->decimal('floor_area', 14, 2)->nullable();
            $table->unsignedSmallInteger('floors')->nullable();
            $table->string('status', 50);
            $table->string('person_in_charge')->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['division_id', 'status']);
            $table->index(['division_id', 'type']);
        });
        Schema::create('haruhay_property_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('haruhay_properties')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haruhay_property_attachments');
        Schema::dropIfExists('haruhay_properties');
    }
};
