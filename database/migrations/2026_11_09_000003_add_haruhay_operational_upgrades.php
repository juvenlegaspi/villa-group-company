<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('haruhay_collections', function (Blueprint $t) {
            $t->timestamp('voided_at')->nullable();
            $t->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('void_reason')->nullable();
            $t->string('active_reference', 100)->nullable();
            $t->dropUnique('haruhay_receipt_reference_unique');
            $t->unique(['method', 'active_reference'], 'haruhay_active_receipt_ref_unique');
        });
        DB::table('haruhay_collections')->update(['active_reference' => DB::raw('external_reference')]);
        Schema::table('haruhay_billing_payments', function (Blueprint $t) {
            $t->timestamp('voided_at')->nullable();
            $t->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('void_reason')->nullable();
        });
        Schema::create('haruhay_deposit_entries', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->uuid('submission_token')->unique();
            $t->foreignId('tenant_id')->constrained('haruhay_tenants')->restrictOnDelete();
            $t->foreignId('property_id')->constrained('haruhay_properties')->restrictOnDelete();
            $t->foreignId('contract_id')->nullable()->constrained('haruhay_lease_contracts')->restrictOnDelete();
            $t->string('tenant_name');
            $t->string('property_name');
            $t->string('room_no');
            $t->string('type', 20);
            $t->decimal('amount', 12, 2);
            $t->date('occurred_on');
            $t->string('method', 30)->nullable();
            $t->string('reference', 100)->nullable();
            $t->text('reason');
            $t->string('active_reference', 100)->nullable();
            $t->unique(['method', 'active_reference'], 'haruhay_deposit_active_ref_unique');
            $t->string('proof_path')->nullable();
            $t->string('proof_name')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('voided_at')->nullable();
            $t->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('void_reason')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'occurred_on']);
        });
        Schema::table('haruhay_lease_contracts', function (Blueprint $t) {
            $t->foreignId('amends_contract_id')->nullable()->unique()->constrained('haruhay_lease_contracts')->restrictOnDelete();
            $t->date('billing_coverage_starts_on')->nullable();
            $t->text('amendment_reason')->nullable();
        });
        Schema::create('haruhay_occupancy_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('unit_id')->constrained('haruhay_units')->restrictOnDelete();
            $t->foreignId('property_id')->constrained('haruhay_properties')->restrictOnDelete();
            $t->string('property_name');
            $t->string('room_no');
            $t->string('status', 20);
            $t->string('room_condition', 40);
            $t->unsignedSmallInteger('capacity')->nullable();
            $t->json('occupied_tenant_ids');
            $t->json('reserved_tenant_ids');
            $t->timestamp('recorded_at');
            $t->string('source', 30);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->index(['unit_id', 'recorded_at']);
        });
        foreach (DB::table('haruhay_units')->get() as $unit) {
            DB::table('haruhay_occupancy_events')->insert([
                'unit_id' => $unit->id, 'property_id' => $unit->property_id, 'property_name' => DB::table('haruhay_properties')->where('id', $unit->property_id)->value('name'), 'room_no' => $unit->unit_no,
                'status' => $unit->status, 'room_condition' => $unit->room_condition, 'capacity' => $unit->capacity,
                'occupied_tenant_ids' => json_encode(DB::table('haruhay_tenants')->where('unit_id', $unit->id)->whereIn('status', ['Active', 'On Notice'])->pluck('id')->all()),
                'reserved_tenant_ids' => json_encode(DB::table('haruhay_tenants')->where('unit_id', $unit->id)->where('status', 'Pending Move-in')->pluck('id')->all()),
                'recorded_at' => now(), 'source' => 'baseline',
            ]);
        }
        Schema::create('haruhay_access_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedBigInteger('old_position_id')->nullable();
            $t->unsignedBigInteger('new_position_id')->nullable();
            $t->text('reason');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haruhay_access_audits');
        Schema::dropIfExists('haruhay_occupancy_events');
        Schema::table('haruhay_lease_contracts', function (Blueprint $t) {
            $t->dropForeign(['amends_contract_id']);
            $t->dropUnique(['amends_contract_id']);
            $t->dropColumn(['amends_contract_id', 'billing_coverage_starts_on', 'amendment_reason']);
        });
        Schema::dropIfExists('haruhay_deposit_entries');
        Schema::table('haruhay_billing_payments', function (Blueprint $t) {
            $t->dropForeign(['voided_by']);
            $t->dropColumn(['voided_at', 'voided_by', 'void_reason']);
        });
        Schema::table('haruhay_collections', function (Blueprint $t) {
            $t->dropForeign(['voided_by']);
            $t->dropUnique('haruhay_active_receipt_ref_unique');
            $t->dropColumn(['voided_at', 'voided_by', 'void_reason', 'active_reference']);
        });
        // Historical duplicate references can exist after corrections; do not re-add the obsolete unique constraint.
    }
};
