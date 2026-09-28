<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_calendar_occurrence_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('shipping_calendar_events')->cascadeOnDelete();
            $table->dateTime('occurrence_starts_at');
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at');
            $table->timestamps();

            $table->unique(['event_id', 'occurrence_starts_at'], 'ship_cal_occurrence_complete_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_calendar_occurrence_completions');
    }
};
