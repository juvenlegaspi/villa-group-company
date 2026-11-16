<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('haruhay_tenants', function (Blueprint $t) {
            $t->unsignedSmallInteger('occupant_count')->default(1);
        });
        Schema::table('haruhay_occupancy_events', function (Blueprint $t) {
            $t->unsignedInteger('occupied_slots')->nullable();
            $t->unsignedInteger('reserved_slots')->nullable();
        });
        Schema::create('haruhay_online_reservations', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();
            $t->uuid('submission_token')->unique();
            $t->string('submission_fingerprint', 64);
            $t->string('tracking_token_hash', 64)->unique();
            $t->foreignId('unit_id')->constrained('haruhay_units')->restrictOnDelete();
            $t->foreignId('tenant_id')->nullable()->unique()->constrained('haruhay_tenants')->restrictOnDelete();
            $t->foreignId('contract_id')->nullable()->constrained('haruhay_lease_contracts')->restrictOnDelete();
            $t->string('name');
            $t->string('email');
            $t->string('phone', 50);
            $t->unsignedSmallInteger('occupant_count');
            $t->json('other_occupants');
            $t->date('requested_starts_on');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->unsignedTinyInteger('duration_months');
            $t->string('status', 30)->default('PendingReview');
            $t->decimal('monthly_rent', 12, 2)->nullable();
            $t->decimal('advance_required', 12, 2)->nullable();
            $t->decimal('advance_received', 12, 2)->default(0);
            $t->timestamp('hold_expires_at')->nullable();
            $t->text('payment_instructions')->nullable();
            $t->text('notes')->nullable();
            $t->string('terms_version', 40);
            $t->timestamp('terms_accepted_at');
            $t->timestamp('quote_accepted_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->text('decision_reason')->nullable();
            $t->timestamps();
            $t->index(['status', 'hold_expires_at']);
        });
        Schema::create('haruhay_reservation_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reservation_id')->constrained('haruhay_online_reservations')->restrictOnDelete();
            $t->uuid('submission_token')->unique();
            $t->decimal('amount', 12, 2);
            $t->decimal('verified_amount', 12, 2)->nullable();
            $t->string('method', 30);
            $t->string('reference', 100)->nullable();
            $t->string('verified_reference', 100)->nullable();
            $t->unique(['method', 'verified_reference'], 'haruhay_booking_payment_ref_unique');
            $t->date('paid_on');
            $t->string('proof_path')->nullable();
            $t->string('proof_name')->nullable();
            $t->string('status', 20)->default('Pending');
            $t->text('review_reason')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('collection_id')->nullable()->unique()->constrained('haruhay_collections')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('haruhay_reservation_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reservation_id')->constrained('haruhay_online_reservations')->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 40);
            $t->string('status', 30);
            $t->text('message');
            $t->timestamp('created_at');
        });
        $division = \Illuminate\Support\Facades\DB::table('divisions')->whereRaw('LOWER(name) = ?', ['haruhay'])->value('id');
        if ($division) {
            foreach (['reservations.manage' => ['haruhay-property-manager', 'haruhay-property-staff'], 'reservations.verify' => ['haruhay-property-manager', 'haruhay-cashier']] as $suffix => $codes) {
                $slug = 'haruhay.'.$suffix;
                \Illuminate\Support\Facades\DB::table('permissions')->insertOrIgnore(['slug' => $slug, 'name' => 'Haruhay '.$suffix, 'module' => 'haruhay', 'created_at' => now(), 'updated_at' => now()]);
                $permission = \Illuminate\Support\Facades\DB::table('permissions')->where('slug', $slug)->value('id');
                foreach (\Illuminate\Support\Facades\DB::table('positions')->where('division_id', $division)->whereIn('code', $codes)->pluck('id') as $position) {
                    \Illuminate\Support\Facades\DB::table('permission_position')->insertOrIgnore(['position_id' => $position, 'permission_id' => $permission]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('haruhay_reservation_events');
        Schema::dropIfExists('haruhay_reservation_payments');
        Schema::dropIfExists('haruhay_online_reservations');
        Schema::table('haruhay_occupancy_events', fn (Blueprint $t) => $t->dropColumn(['occupied_slots', 'reserved_slots']));
        Schema::table('haruhay_tenants', fn (Blueprint $t) => $t->dropColumn('occupant_count'));
    }
};
