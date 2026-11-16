<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('haruhay_tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('unit_id')->constrained('haruhay_units')->restrictOnDelete();
            $table->string('name');
            $table->string('contact_number', 50);
            $table->string('email')->nullable();
            $table->date('move_in_date');
            $table->date('move_out_date')->nullable();
            $table->string('status', 20)->default('Active');
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'status']);
        });
        Schema::create('haruhay_tenant_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('haruhay_tenants')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haruhay_tenant_audits');
        Schema::dropIfExists('haruhay_tenants');
    }
};
