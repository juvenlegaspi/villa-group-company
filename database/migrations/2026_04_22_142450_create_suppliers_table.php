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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('business_type');
            $table->string('tin')->unique();

            $table->text('address')->nullable();
            $table->text('products')->nullable();

            $table->string('tax_type')->nullable();

            $table->integer('lead_time')->nullable();
            $table->integer('credit_term')->nullable();
            $table->decimal('limit_advances', 10, 2)->nullable();

            $table->string('contact_person')->nullable();
            $table->string('telephone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();

            $table->string('status')->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
