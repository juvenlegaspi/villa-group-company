<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_sms_alert_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vessel_certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_number', 20);
            $table->date('alert_date');
            $table->integer('days_remaining');
            $table->string('status', 20)->default('reserved');
            $table->string('provider_message_id')->nullable();
            $table->string('provider_status', 40)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['vessel_certificate_id', 'recipient_number', 'alert_date'], 'certificate_sms_recipient_day_unique');
            $table->index(['status', 'alert_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_sms_alert_logs');
    }
};
