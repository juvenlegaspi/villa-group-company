<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_calendar_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('shipping_calendar_events')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index(['event_id', 'created_at'], 'ship_cal_attachment_event_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_calendar_attachments');
    }
};
