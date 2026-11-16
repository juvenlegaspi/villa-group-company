<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayBilling;
use App\Models\HaruhayBillingPayment;
use App\Models\HaruhayCollection;
use App\Models\HaruhayDepositEntry;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HaruhayDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'admin'): User
    {
        $division = Division::firstOrCreate(['name' => 'Haruhay']);
        $department = Department::create(['name' => 'Property Management', 'division_id' => $division->id]);

        return User::create(['name' => 'Executive Test', 'lastname' => 'Test', 'username' => 'dashboard-'.str()->uuid(), 'email' => str()->uuid().'@example.com', 'password' => 'test-password-123', 'department_id' => $department->id, 'division_id' => $division->id, 'role' => $role, 'is_admin' => $role === 'admin', 'status' => true, 'must_change_password' => false]);
    }

    private function property(int $divisionId, string $name = 'Haruhay Space'): HaruhayProperty
    {
        return HaruhayProperty::create(['name' => $name, 'owner' => 'Owner', 'type' => 'Dormitory', 'street_barangay' => 'Test Street', 'city' => 'Cebu City', 'province' => 'Cebu', 'status' => 'Operational', 'division_id' => $divisionId]);
    }

    private function records(User $user): array
    {
        $property = $this->property($user->division_id);
        $unit = HaruhayUnit::create(['property_id' => $property->id, 'floor' => 1, 'unit_type' => 'Shared', 'capacity' => 3, 'status' => 'Occupied', 'room_condition' => 'Ready']);
        HaruhayUnit::create(['property_id' => $property->id, 'floor' => 2, 'unit_type' => 'Single', 'capacity' => 1, 'status' => 'Vacant', 'room_condition' => 'Under Maintenance']);
        $tenant = HaruhayTenant::create(['unit_id' => $unit->id, 'name' => 'Tenant One', 'contact_number' => '09170000001', 'status' => 'Active', 'move_in_date' => today()->subMonth()]);
        HaruhayTenant::create(['unit_id' => $unit->id, 'name' => 'Reserved Tenant', 'contact_number' => '09170000002', 'status' => 'Pending Move-in', 'scheduled_move_in_date' => today()->addWeek()]);
        $contract = HaruhayLeaseContract::create([
            'code' => 'DASH-CTR-'.str()->uuid(), 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'tenant_name' => $tenant->name, 'property_name' => $property->name, 'room_no' => $unit->unit_no, 'floor' => 1,
            'starts_on' => today()->subMonth(), 'ends_on' => today()->addDays(10), 'monthly_rent' => 10000, 'billing_starts_on' => today()->startOfMonth(),
            'first_due_on' => today(), 'due_day' => 10, 'billing_cycle' => 'calendar_month', 'first_month_policy' => 'full_month', 'first_month_charge' => 10000,
        ]);
        $bill = HaruhayBilling::create([
            'code' => 'DASH-INV-'.str()->uuid(), 'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'unit_id' => $unit->id,
            'tenant_name' => $tenant->name, 'property_name' => $property->name, 'room_no' => $unit->unit_no,
            'period_start' => today()->startOfMonth(), 'period_end' => today()->endOfMonth(), 'issued_on' => today(), 'due_on' => today()->subDay(),
            'rent' => 10000, 'total' => 10000, 'finalized_at' => now(), 'utilities_ready' => true,
        ]);
        $receipt = HaruhayCollection::create([
            'code' => 'DASH-OR-'.str()->uuid(), 'submission_token' => str()->uuid(), 'tenant_id' => $tenant->id, 'initial_billing_id' => $bill->id,
            'tenant_name' => $tenant->name, 'property_id' => $property->id, 'property_name' => $property->name, 'room_no' => $unit->unit_no,
            'paid_on' => today(), 'amount' => 3000, 'method' => 'Cash',
        ]);
        HaruhayBillingPayment::create(['billing_id' => $bill->id, 'collection_id' => $receipt->id, 'amount' => 3000, 'paid_on' => today(), 'reference' => 'DASH-ALLOCATION-'.str()->uuid()]);

        return compact('property', 'unit', 'tenant', 'contract', 'bill', 'receipt');
    }

    public function test_admin_sees_the_executive_dashboard_and_empty_states(): void
    {
        $this->actingAs($this->actor())->get(route('division.dashboard', 'Haruhay'))->assertOk()
            ->assertViewIs('dashboard.haruhay')->assertSee('Executive overview')
            ->assertSee('No outstanding accounts.')->assertSee('No rooms recorded for the selected property.')
            ->assertSee('id="he-cashflow"', false)->assertSee('id="he-occupancy"', false)
            ->assertSee('id="he-performance"', false)->assertSee('id="he-aging"', false)
            ->assertDontSee('@foreach', false)->assertDontSee('@include', false)
            ->assertViewHas('report', fn ($r) => $r['receivedTotal'] === 0 && $r['totalUnits'] === 0);
    }

    public function test_owner_can_view_but_regular_haruhay_staff_cannot(): void
    {
        $this->actingAs($this->actor('owner'))->get(route('division.dashboard', 'Haruhay'))->assertOk()->assertSee('Read only');
        $this->actingAs($this->actor('user'))->get(route('division.dashboard', 'Haruhay'))->assertForbidden();
    }

    public function test_guest_must_sign_in(): void
    {
        $this->get(route('division.dashboard', 'Haruhay'))->assertRedirect(route('login'));
    }

    public function test_actual_financial_metrics_reservations_and_foreign_company_scope(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(10, 0));
        $user = $this->actor();
        $this->actingAs($user);
        $records = $this->records($user);
        $advance = $records['receipt']->replicate();
        $advance->fill(['code' => 'DASH-ADV', 'submission_token' => str()->uuid(), 'initial_billing_id' => null, 'amount' => 500])->save();
        $reversed = $records['receipt']->replicate();
        $reversed->fill(['code' => 'DASH-REVERSED', 'submission_token' => str()->uuid(), 'amount' => 900, 'voided_at' => now(), 'void_reason' => 'Test reversal'])->save();
        HaruhayDepositEntry::create(['code' => 'DEP-TEST', 'room_no' => 'DR-01', 'submission_token' => str()->uuid(), 'tenant_id' => $records['tenant']->id, 'property_id' => $records['property']->id, 'tenant_name' => 'Tenant One', 'property_name' => 'Haruhay Space', 'type' => 'received', 'amount' => 2000, 'occurred_on' => today(), 'method' => 'Cash', 'reason' => 'Security deposit']);
        $draft = $records['bill']->replicate();
        $draft->fill(['code' => 'DRAFT-TEST', 'period_start' => today()->addMonth()->startOfMonth(), 'total' => 99999, 'finalized_at' => null])->save();
        $foreign = Division::create(['name' => 'Foreign Company']);
        $foreignProperty = $this->property($foreign->id, 'FOREIGN-SECRET');
        HaruhayUnit::create(['property_id' => $foreignProperty->id, 'floor' => 1, 'unit_type' => 'Foreign', 'capacity' => 99]);
        $this->get(route('division.dashboard', ['division' => 'Haruhay', 'from' => '2026-11-01', 'to' => '2026-11-10']))->assertOk()
            ->assertDontSee('FOREIGN-SECRET')
            ->assertViewHas('report', fn ($r) => $r['billingTotal'] === 1000000 && $r['receivedTotal'] === 350000 && $r['outstanding'] === 700000 && $r['rate'] == 30 && $r['totalUnits'] === 2 && $r['occupancyRate'] == 50 && $r['upcoming']->count() === 1)
            ->assertViewHas('overdue', 700000)
            ->assertViewHas('operations', fn ($r) => $r['availableSlots'] === 1 && $r['maintenance'] === 1 && $r['pendingMoveIns'] === 1);
    }

    public function test_property_filter_changes_financial_and_room_totals(): void
    {
        $user = $this->actor();
        $this->actingAs($user);
        $this->records($user);
        $otherProperty = $this->property($user->division_id, 'Second Property');
        HaruhayUnit::create(['property_id' => $otherProperty->id, 'floor' => 1, 'unit_type' => 'Single', 'capacity' => 2]);
        $this->get(route('division.dashboard', ['division' => 'Haruhay', 'property_id' => $otherProperty->id]))->assertOk()
            ->assertViewHas('report', fn ($r) => $r['billingTotal'] === 0 && $r['receivedTotal'] === 0 && $r['totalUnits'] === 1)
            ->assertViewHas('operations', fn ($r) => $r['availableSlots'] === 2 && $r['readyVacant'] === 1);
    }

    public function test_foreign_property_and_invalid_dates_are_rejected(): void
    {
        $user = $this->actor();
        $this->actingAs($user);
        $foreign = Division::create(['name' => 'Foreign Company']);
        $property = $this->property($foreign->id);
        $this->get(route('division.dashboard', ['division' => 'Haruhay', 'property_id' => $property->id]))->assertSessionHasErrors('property_id');
        $this->get(route('division.dashboard', ['division' => 'Haruhay', 'from' => today()->toDateString(), 'to' => today()->subDay()->toDateString()]))->assertSessionHasErrors('to');
        $this->get(route('division.dashboard', ['division' => 'Haruhay', 'to' => today()->addDay()->toDateString()]))->assertSessionHasErrors('to');
        $this->get(route('division.dashboard', ['division' => 'Haruhay', 'from' => today()->subYears(3)->toDateString()]))->assertSessionHasErrors('to');
    }

    public function test_historical_financial_filter_keeps_room_counts_current(): void
    {
        $user = $this->actor();
        $this->actingAs($user);
        $this->records($user);
        $this->get(route('division.dashboard', ['division' => 'Haruhay', 'from' => today()->subMonths(3)->startOfMonth()->toDateString(), 'to' => today()->subMonths(2)->endOfMonth()->toDateString()]))->assertOk()
            ->assertSee('Rooms and leases reflect today.')
            ->assertViewHas('report', fn ($r) => $r['billingTotal'] === 0 && $r['receivedTotal'] === 0 && $r['outstanding'] === 0 && $r['totalUnits'] === 2);
    }
}
