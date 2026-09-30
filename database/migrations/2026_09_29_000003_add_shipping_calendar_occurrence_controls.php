<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_calendar_occurrence_overrides')) {
            Schema::create('shipping_calendar_occurrence_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('shipping_calendar_events')->cascadeOnDelete();
            $table->dateTime('occurrence_starts_at');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('title', 180)->nullable();
            $table->string('checklist_type', 30)->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('reminder_minutes')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'occurrence_starts_at'], 'ship_cal_occurrence_override_unique');
            $table->index(['event_id', 'status'], 'ship_cal_occurrence_override_status_idx');
            });
        }

        if (! Schema::hasColumn('shipping_calendar_attachments', 'occurrence_starts_at')) {
            Schema::table('shipping_calendar_attachments', function (Blueprint $table): void {
                $table->dateTime('occurrence_starts_at')->nullable()->after('event_id');
                $table->index(['event_id', 'occurrence_starts_at'], 'ship_cal_attachment_occurrence_idx');
            });
        }

        DB::table('shipping_calendar_attachments')->orderBy('id')->chunkById(200, function ($attachments): void {
            $eventStarts = DB::table('shipping_calendar_events')
                ->whereIn('id', $attachments->pluck('event_id')->unique())
                ->pluck('starts_at', 'id');
            foreach ($attachments as $attachment) {
                DB::table('shipping_calendar_attachments')->where('id', $attachment->id)
                    ->update(['occurrence_starts_at' => $eventStarts[$attachment->event_id] ?? null]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipping_calendar_attachments', function (Blueprint $table): void {
            $table->dropIndex('ship_cal_attachment_occurrence_idx');
            $table->dropColumn('occurrence_starts_at');
        });
        Schema::dropIfExists('shipping_calendar_occurrence_overrides');
    }
};
