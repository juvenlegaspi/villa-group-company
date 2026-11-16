<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('haruhay_lease_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('tenant_id')->constrained('haruhay_tenants')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('haruhay_units')->restrictOnDelete();
            $table->foreignId('previous_contract_id')->nullable()->unique()->constrained('haruhay_lease_contracts')->restrictOnDelete();
            $table->string('tenant_name');
            $table->string('property_name');
            $table->string('room_no');
            $table->unsignedSmallInteger('floor');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('monthly_rent', 12, 2);
            $table->date('billing_starts_on');
            $table->date('first_due_on');
            $table->unsignedTinyInteger('due_day');
            $table->string('billing_cycle', 30);
            $table->string('first_month_policy', 20);
            $table->decimal('first_month_charge', 12, 2);
            $table->decimal('deposit_required', 12, 2)->default(0);
            $table->decimal('advance_required', 12, 2)->default(0);
            $table->text('advance_application')->nullable();
            $table->text('remarks')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->date('terminated_on')->nullable();
            $table->text('termination_reason')->nullable();
            $table->foreignId('terminated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'unit_id', 'starts_on', 'ends_on'], 'haruhay_contract_period_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('haruhay_lease_contracts'); }
};
