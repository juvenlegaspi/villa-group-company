<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('haruhay_collection_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('haruhay_collections')->restrictOnDelete();
            $table->string('channel', 10);
            $table->string('recipient')->nullable();
            $table->string('status', 20)->default('Pending');
            $table->json('payload');
            $table->string('provider_reference')->nullable();
            $table->string('status_note')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['collection_id', 'channel']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haruhay_collection_deliveries');
    }
};
