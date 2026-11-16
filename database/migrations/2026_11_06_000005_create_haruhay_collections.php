<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('haruhay_collections', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->uuid('submission_token')->unique();
            $table->foreignId('tenant_id')->constrained('haruhay_tenants')->restrictOnDelete();
            $table->foreignId('initial_billing_id')->nullable()->constrained('haruhay_billings')->restrictOnDelete();
            $table->string('tenant_name');
            $table->string('property_name');
            $table->string('room_no');
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_due', 12, 2)->nullable();
            $table->string('method', 30);
            $table->string('external_reference', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['method', 'external_reference'], 'haruhay_receipt_reference_unique');
            $table->index(['tenant_id', 'paid_on']);
        });
        Schema::table('haruhay_billing_payments', function (Blueprint $table) {
            $table->foreignId('collection_id')->nullable()->constrained('haruhay_collections')->restrictOnDelete();
            $table->unique(['collection_id', 'billing_id'], 'haruhay_receipt_bill_unique');
        });
    }
    public function down(): void
    {
        Schema::table('haruhay_billing_payments', function (Blueprint $table) {
            $table->dropUnique('haruhay_receipt_bill_unique');
            $table->dropForeign(['collection_id']);
            $table->dropColumn('collection_id');
        });
        Schema::dropIfExists('haruhay_collections');
    }
};
