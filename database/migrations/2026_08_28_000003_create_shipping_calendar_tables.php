<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('vessel_id')->nullable()->constrained('vessels')->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('all_day')->default(false);
            $table->string('visibility', 20)->default('personal');
            $table->string('color', 20)->default('#1d4ed8');
            $table->string('recurrence_frequency', 20)->nullable();
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->date('recurrence_ends_on')->nullable();
            $table->unsignedInteger('reminder_minutes')->nullable();
            $table->boolean('email_reminder')->default(false);
            $table->string('status', 20)->default('scheduled');
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['division_id', 'starts_at'], 'ship_cal_division_start_idx');
            $table->index(['visibility', 'department_id', 'vessel_id'], 'ship_cal_scope_idx');
        });

        Schema::create('shipping_calendar_attendees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('shipping_calendar_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('response_status', 20)->default('pending');
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id'], 'ship_cal_event_user_unique');
        });

        Schema::create('shipping_calendar_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('shipping_calendar_events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->json('changes')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['event_id', 'created_at'], 'ship_cal_audit_event_date_idx');
        });

        Schema::create('shipping_calendar_reminder_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('shipping_calendar_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('occurrence_starts_at');
            $table->string('channel', 20);
            $table->dateTime('sent_at');
            $table->timestamps();
            $table->unique(['event_id', 'user_id', 'occurrence_starts_at', 'channel'], 'ship_cal_reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_calendar_reminder_logs');
        Schema::dropIfExists('shipping_calendar_audits');
        Schema::dropIfExists('shipping_calendar_attendees');
        Schema::dropIfExists('shipping_calendar_events');
    }
};
