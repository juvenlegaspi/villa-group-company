<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('haruhay_billings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('tenant_id')->constrained('haruhay_tenants')->restrictOnDelete();
            $table->foreignId('contract_id')->constrained('haruhay_lease_contracts')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('haruhay_units')->restrictOnDelete();
            $table->string('tenant_name');
            $table->string('property_name');
            $table->string('room_no');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('issued_on');
            $table->date('due_on');
            foreach (['rent', 'water', 'electric', 'utilities_other', 'parking', 'addons_other', 'penalty', 'discount', 'total'] as $field) $table->decimal($field, 12, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['contract_id', 'period_start'], 'haruhay_invoice_period_unique');
            $table->index(['issued_on', 'due_on']);
        });
        Schema::create('haruhay_billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('haruhay_billings')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('reference')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('haruhay_billing_payments');
        Schema::dropIfExists('haruhay_billings');
    }
};
