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
        Schema::create('item_inventory_header', function (Blueprint $table) {
            $table->id(); // bigint auto increment

            $table->string('item_name');
            $table->string('unit');

            $table->integer('maximum_quantity');
            $table->integer('minimum_quantity');
            $table->integer('stock_on_hand');

            $table->timestamp('date_added')->useCurrent();

            $table->unsignedBigInteger('created_by'); // user id

            $table->integer('status')->default(1); // 1 active, 0 inactive
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_inventory_header');
    }
};
