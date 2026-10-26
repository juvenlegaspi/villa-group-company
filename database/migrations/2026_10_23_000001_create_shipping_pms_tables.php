<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_pms_headers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vessel_id')->constrained('vessels')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['vessel_id', 'name']);
        });
        Schema::create('shipping_pms_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('header_id')->constrained('shipping_pms_headers')->cascadeOnDelete();
            $table->string('name');
            $table->string('activity')->nullable();
            $table->string('frequency')->nullable();
            $table->string('in_charge')->nullable();
            $table->date('start_date')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_pms_details');
        Schema::dropIfExists('shipping_pms_headers');
    }
};
