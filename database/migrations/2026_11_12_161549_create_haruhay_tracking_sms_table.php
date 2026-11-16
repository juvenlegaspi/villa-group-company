<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('haruhay_tracking_sms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained('haruhay_online_reservations')->restrictOnDelete();
            $table->string('channel', 10)->default('sms');
            $table->string('recipient');
            $table->string('status', 20)->default('Pending');
            $table->longText('payload')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('status_note')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haruhay_tracking_sms');
    }
};
