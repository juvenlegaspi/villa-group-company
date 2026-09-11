<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_calendar_events', function (Blueprint $table): void {
            $table->string('checklist_type', 30)->default('routine')->after('title');
        });

        Schema::table('shipping_calendar_reminder_logs', function (Blueprint $table): void {
            $table->string('status', 20)->default('reserved')->after('channel');
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->string('provider_message_id')->nullable()->after('attempts');
            $table->string('provider_status', 40)->nullable()->after('provider_message_id');
            $table->text('error_message')->nullable()->after('provider_status');
            $table->dateTime('sent_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_calendar_reminder_logs', function (Blueprint $table): void {
            $table->dropColumn(['status', 'attempts', 'provider_message_id', 'provider_status', 'error_message']);
            $table->dateTime('sent_at')->nullable(false)->change();
        });
        Schema::table('shipping_calendar_events', fn (Blueprint $table) => $table->dropColumn('checklist_type'));
    }
};
