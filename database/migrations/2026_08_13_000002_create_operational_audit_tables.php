<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_alert_logs')) {
            Schema::create('certificate_alert_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('vessel_certificate_id')->constrained()->cascadeOnDelete();
                $table->string('recipient_email');
                $table->date('alert_date');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(
                    ['vessel_certificate_id', 'recipient_email', 'alert_date'],
                    'certificate_alert_recipient_day_unique'
                );
            });
        }

        if (! Schema::hasTable('item_inventory_transactions')) {
            Schema::create('item_inventory_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('item_inventory_header_id')
                    ->constrained('item_inventory_header')
                    ->restrictOnDelete();
                $table->enum('type', ['IN', 'OUT']);
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('balance_before');
                $table->unsignedInteger('balance_after');
                $table->string('reference_no', 100)->nullable();
                $table->text('remarks')->nullable();
                $table->dateTime('transacted_at');
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->index(['type', 'transacted_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_inventory_transactions');
        Schema::dropIfExists('certificate_alert_logs');
    }
};
