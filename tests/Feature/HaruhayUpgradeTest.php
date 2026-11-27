<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayBilling;
use App\Models\HaruhayCollection;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Models\User;
use App\Services\HaruhayBillingSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HaruhayUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function actor(string $role = 'admin', string $company = 'Haruhay'): User
    {
        $division = Division::firstOrCreate(['name' => $company]);
        $department = Department::create(['name' => 'Property Management', 'division_id' => $division->id]);

        return User::create(['name' => 'Contract Tester', 'lastname' => 'Test', 'username' => 'test-'.str()->uuid(), 'email' => str()->uuid().'@example.com', 'password' => 'test-password-123', 'department_id' => $department->id, 'division_id' => $division->id, 'role' => $role, 'is_admin' => $role === 'admin', 'status' => true, 'must_change_password' => false]);
    }

    private function tenant(User $user): HaruhayTenant
    {
        $property = HaruhayProperty::create(['name' => 'Haruhay Space', 'owner' => 'Owner', 'type' => 'Dormitory', 'street_barangay' => 'T. Padilla', 'city' => 'Cebu City', 'province' => 'Cebu', 'status' => 'Operational', 'division_id' => $user->division_id]);
        $room = HaruhayUnit::create(['property_id' => $property->id, 'floor' => 3, 'unit_type' => 'Single bed', 'monthly_rent' => 10000, 'status' => 'Occupied', 'room_condition' => 'Ready']);

        return HaruhayTenant::create(['unit_id' => $room->id, 'name' => 'Contract Tenant', 'contact_number' => '09171234567', 'move_in_date' => today()->subDays(10), 'status' => 'Active']);
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'starts_on' => today()->subDays(10)->toDateString(), 'ends_on' => today()->addMonthsNoOverflow(6)->toDateString(),
            'monthly_rent' => '10,000', 'billing_starts_on' => today()->subDays(10)->toDateString(),
            'first_due_on' => today()->subDays(10)->toDateString(), 'due_day' => 5,
            'billing_cycle' => 'calendar_month', 'first_month_policy' => 'full_month',
            'deposit_required' => '10,000', 'advance_required' => '10,000', 'advance_application' => 'Apply to first billing month',
        ], $overrides);
    }

    private function add(HaruhayTenant $tenant, array $overrides = []): HaruhayLeaseContract
    {
        $this->post(route('haruhay.contracts.store', $tenant), $this->data($overrides))->assertSessionHasNoErrors()->assertRedirect(route('haruhay.contracts.tenant', $tenant));

        return $tenant->contracts()->latest('id')->firstOrFail();
    }

    private function invoiceData(HaruhayLeaseContract $contract, array $overrides = []): array
    {
        return array_replace(['contract_id' => $contract->id, 'period_start' => $contract->starts_on->toDateString(), 'issued_on' => today()->toDateString(), 'water' => '100', 'electric' => '500', 'utilities_other' => '0', 'parking' => '200', 'addons_other' => '0', 'penalty' => '50', 'discount' => '150'], $overrides);
    }

    private function issued(HaruhayTenant $tenant): HaruhayBilling
    {
        $contract = $this->add($tenant);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $bill = $tenant->billings()->latest('id')->firstOrFail();
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();

        return $bill->fresh();
    }

    private function payment(?HaruhayBilling $bill = null, array $overrides = []): array
    {
        $data = array_replace(['submission_token' => (string) str()->uuid(), 'receipt_type' => $bill ? 'payment' : 'advance', 'billing_id' => $bill?->id, 'paid_on' => today()->toDateString(), 'amount' => '1,000', 'method' => 'Cash', 'external_reference' => '', 'remarks' => 'Received at office'], $overrides);
        if (in_array($data['method'], ['Bank Transfer', 'GCash', 'Maya']) && ! array_key_exists('proof', $overrides)) {
            $data['proof'] = UploadedFile::fake()->image('transaction.png');
        }

        return $data;
    }

    private function deposit(HaruhayTenant $tenant, array $overrides = []): array
    {
        return array_replace(['submission_token' => (string) str()->uuid(), 'type' => 'received', 'amount' => 5000, 'occurred_on' => today()->toDateString(), 'method' => 'Cash', 'reason' => 'Security deposit received'], $overrides);
    }

    public function test_receipt_reversal_restores_balance_and_retains_original_history(): void
    {
        $admin = $this->actor();
        $tenant = $this->tenant($admin);
        $this->actingAs($admin);
        $bill = $this->issued($tenant);
        $data = $this->payment($bill, ['amount' => 3000, 'method' => 'GCash', 'external_reference' => 'CORRECTABLE-REF']);
        $this->post(route('haruhay.collections.store', $tenant), $data)->assertSessionHasNoErrors();
        $receipt = HaruhayCollection::sole();
        $this->post(route('haruhay.collections.reverse', $receipt), ['reason' => 'Wrong amount confirmed by cashier'])->assertSessionHasNoErrors();
        $this->assertSame(1070000, $bill->fresh()->balance_cents);
        $this->assertSame('Reversed', $receipt->fresh()->status);
        $this->assertSame(1, $receipt->allocationHistory()->count());
        $this->assertSame(0, $receipt->allocations()->count());
        $this->get(route('haruhay.collections.show', $receipt))->assertOk()->assertSee('Wrong amount confirmed by cashier')->assertSee('Reversed');
        $this->get(route('haruhay.collections.index'))->assertOk()->assertViewHas('stats', fn ($s) => (float) $s['Collections This Month'] === 0.0);
        $this->post(route('haruhay.collections.reverse', $receipt), ['reason' => 'Duplicate reversal'])->assertSessionHasErrors('receipt', null, 'collectionForm');
        $data['submission_token'] = (string) str()->uuid();
        $data['amount'] = 2000;
        $this->post(route('haruhay.collections.store', $tenant), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('haruhay_collections', 2);
        $this->assertSame(870000, $bill->fresh()->balance_cents);
    }

    public function test_reversing_advance_reverses_all_allocations_but_not_other_receipts(): void
    {
        $admin = $this->actor();
        $tenant = $this->tenant($admin);
        $this->actingAs($admin);
        $bill = $this->issued($tenant);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => 12000]))->assertSessionHasNoErrors();
        $receipt = HaruhayCollection::sole();
        $periods = (new HaruhayBillingSchedule)->periods($bill->contract);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($bill->contract, ['period_start' => $periods[1]['start'], 'utilities_ready' => 1]))->assertSessionHasNoErrors();
        $next = HaruhayBilling::latest('id')->first();
        $this->post(route('haruhay.billing.issue', $next))->assertSessionHasNoErrors();
        $this->post(route('haruhay.collections.reverse', $receipt), ['reason' => 'Bank transaction was entered twice'])->assertSessionHasNoErrors();
        $this->assertSame(1070000, $bill->fresh()->balance_cents);
        $this->assertSame(1070000, $next->fresh()->balance_cents);
        $this->assertSame(2, $receipt->allocationHistory()->count());
        $this->assertSame(0, $receipt->fresh()->unapplied_cents);
        $this->get(route('haruhay.billing.tenant', $tenant))->assertOk()->assertViewHas('currentKey', $bill->contract_id.':'.$bill->period_start->toDateString());
    }

    public function test_security_deposit_deductions_refunds_and_reversals_are_separate_from_rent(): void
    {
        $admin = $this->actor();
        $tenant = $this->tenant($admin);
        $this->actingAs($admin);
        $this->add($tenant);
        $this->post(route('haruhay.deposits.store', $tenant), $this->deposit($tenant))->assertSessionHasNoErrors();
        $this->assertSame(500000, \App\Models\HaruhayDepositEntry::heldCents($tenant->id));
        $this->assertDatabaseCount('haruhay_collections', 0);
        $this->assertDatabaseCount('haruhay_billing_payments', 0);
        $this->post(route('haruhay.deposits.store', $tenant), $this->deposit($tenant, ['type' => 'deduction', 'amount' => 1000, 'reason' => 'Documented door repair']))->assertSessionHasNoErrors();
        $this->post(route('haruhay.deposits.store', $tenant), $this->deposit($tenant, ['type' => 'refund', 'amount' => 1000]))->assertSessionHasErrors('type', null, 'depositForm');
        $tenant->update(['status' => 'Moved Out']);
        $this->post(route('haruhay.deposits.store', $tenant), $this->deposit($tenant, ['type' => 'refund', 'amount' => 3000, 'reason' => 'Partial move-out deposit return']))->assertSessionHasNoErrors();
        $this->assertSame(100000, \App\Models\HaruhayDepositEntry::heldCents($tenant->id));
        $refund = \App\Models\HaruhayDepositEntry::latest('id')->first();
        $this->post(route('haruhay.deposits.reverse', [$tenant, $refund]), ['reason' => 'Incorrect refund entry; cash was not released'])->assertSessionHasNoErrors();
        $this->assertSame(400000, \App\Models\HaruhayDepositEntry::heldCents($tenant->id));
        $received = \App\Models\HaruhayDepositEntry::first();
        $this->post(route('haruhay.deposits.reverse', [$tenant, $received]), ['reason' => 'Cannot remove spent deposit'])->assertSessionHasErrors('amount', null, 'depositForm');
        $this->get(route('haruhay.deposits.index', $tenant))->assertOk()->assertSee('4,000.00')->assertSee('Documented door repair');
    }

    public function test_deposit_proof_duplicates_and_historical_negative_balances_are_guarded(): void
    {
        $admin = $this->actor();
        $tenant = $this->tenant($admin);
        $this->actingAs($admin);
        $data = $this->deposit($tenant, ['method' => 'GCash', 'reference' => 'DEP-REF']);
        $this->post(route('haruhay.deposits.store', $tenant), $data)->assertSessionHasErrors('proof', null, 'depositForm');
        $data['proof'] = UploadedFile::fake()->image('deposit.png');
        $this->post(route('haruhay.deposits.store', $tenant), $data)->assertSessionHasNoErrors();
        $this->post(route('haruhay.deposits.store', $tenant), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('haruhay_deposit_entries', 1);
        $entry = \App\Models\HaruhayDepositEntry::sole();
        $this->get(route('haruhay.deposits.proof', [$tenant, $entry]))->assertOk();
        $this->post(route('haruhay.deposits.store', $tenant), $this->deposit($tenant, ['type' => 'deduction', 'amount' => 1, 'occurred_on' => today()->subDay()->toDateString()]))->assertSessionHasErrors('occurred_on', null, 'depositForm');
        $this->assertDatabaseCount('haruhay_deposit_entries', 1);
        $this->post(route('haruhay.deposits.store', $tenant), $this->deposit($tenant, ['type' => 'deduction', 'amount' => 6000]))->assertSessionHasErrors('amount', null, 'depositForm');
    }

    public function test_room_amendment_preserves_existing_coverage_and_changes_room_safely(): void
    {
        $admin = $this->actor();
        $tenant = $this->tenant($admin);
        $this->actingAs($admin);
        $bill = $this->issued($tenant);
        $old = $bill->contract;
        $oldRoom = $tenant->unit;
        $other = $this->tenant($admin);
        $other->update(['status' => 'Moved Out']);
        $room = $other->unit;
        $room->update(['status' => 'Vacant', 'monthly_rent' => 15000]);
        $this->get(route('haruhay.transfers.create', $tenant))->assertOk()->assertSee('Room Transfer / Contract Amendment');
        $this->post(route('haruhay.transfers.store', $tenant), ['unit_id' => $room->id, 'monthly_rent' => '15,000', 'ends_on' => today()->addYear()->toDateString(), 'reason' => 'Agreed move to larger room'])->assertSessionHasNoErrors();
        $new = $tenant->contracts()->latest('id')->first();
        $this->assertSame($old->id, $new->amends_contract_id);
        $this->assertSame('15000.00', $new->monthly_rent);
        $this->assertSame('Amended', $old->fresh()->status);
        $this->assertSame($room->id, $tenant->fresh()->unit_id);
        $this->assertSame('Vacant', $oldRoom->fresh()->status);
        $this->assertSame('Occupied', $room->fresh()->status);
        $oldPeriods = (new HaruhayBillingSchedule)->periods($old->fresh());
        $newPeriods = (new HaruhayBillingSchedule)->periods($new);
        $this->assertLessThan($newPeriods[0]['start'], end($oldPeriods)['end']);
        $this->assertSame('10700.00', $bill->fresh()->total);
        $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk()->assertSee('Amendment of '.$old->code)->assertSee('Agreed move to larger room');
    }

    public function test_historical_occupancy_tracks_actual_state_changes_and_unknown_history(): void
    {
        $this->travelTo(today()->subDays(5)->setTime(10, 0));
        $day1 = today()->toDateString();
        $admin = $this->actor();
        $this->actingAs($admin);
        $tenant = $this->tenant($admin);
        $tenant->update(['status' => 'Moved Out']);
        $room = $tenant->unit;
        $room->update(['status' => 'Vacant']);
        $this->post(route('haruhay.tenants.store'), ['property_id' => $room->property_id, 'unit_id' => $room->id, 'name' => 'Reserved Resident', 'contact_number' => '09170000000', 'status' => 'Pending Move-in', 'move_in_date' => today()->addWeek()->toDateString()])->assertSessionHasNoErrors();
        $resident = HaruhayTenant::latest('id')->first();
        $this->travel(1)->days();
        $day2 = today()->toDateString();
        $this->post(route('haruhay.tenants.confirm', $resident))->assertSessionHasNoErrors();
        $this->travel(1)->days();
        $room->refresh()->update(['room_condition' => 'Under Maintenance']);
        $filters = ['type' => 'occupancy', 'from' => $day1, 'to' => today()->toDateString(), 'occupancy_on' => $day1];
        $this->get(route('haruhay.reports.index', $filters))->assertOk()->assertViewHas('report', fn ($r) => $r['unitStatus']['Reserved'] === 1 && $r['activeTenants'] === 0);
        $this->get(route('haruhay.reports.index', array_replace($filters, ['occupancy_on' => $day2])))->assertOk()->assertViewHas('report', fn ($r) => $r['unitStatus']['Occupied'] === 1 && $r['maintenance'] === 0 && $r['activeTenants'] === 1);
        $this->get(route('haruhay.reports.index', array_replace($filters, ['occupancy_on' => today()->toDateString()])))->assertOk()->assertViewHas('report', fn ($r) => $r['unitStatus']['Occupied'] === 1 && $r['maintenance'] === 1);
        $this->travelBack();
    }

    public function test_cashier_and_property_staff_permissions_are_separate_and_assignments_audited(): void
    {
        $admin = $this->actor();
        $staff = $this->actor('staff');
        $this->actingAs($admin);
        $cashier = \App\Models\Position::where('code', 'haruhay-cashier')->firstOrFail();
        $this->get(route('haruhay.access.index'))->assertOk()->assertSee('Cashier');
        $this->from(route('haruhay.access.index'))->put(route('haruhay.access.update', $staff), ['position_id' => $cashier->id, 'reason' => 'Assigned as Haruhay cashier'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('haruhay_access_audits', 1);
        $this->assertSame($cashier->id,$staff->companyAssignments()->where('division_id',$staff->division_id)->sole()->position_id);
        $tenant = $this->tenant($admin);
        $bill = $this->issued($tenant);
        $staff->haruhayProperties()->syncWithoutDetaching([$tenant->unit->property_id => ['is_active' => true, 'assigned_by' => $admin->id]]);
        $this->actingAs($staff->fresh());
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill))->assertSessionHasNoErrors();
        $receipt = HaruhayCollection::sole();
        $this->post(route('haruhay.collections.reverse', $receipt), ['reason' => 'Not permitted'])->assertForbidden();
        $this->post(route('haruhay.deposits.store', $tenant), $this->deposit($tenant))->assertSessionHasNoErrors();
        $this->post(route('haruhay.deposits.store', $tenant), $this->deposit($tenant, ['type' => 'refund']))->assertForbidden();
        $this->get(route('haruhay.transfers.create', $tenant))->assertForbidden();
        $this->get(route('haruhay.access.index'))->assertForbidden();
        $cashier->update(['is_active' => false]);
        $this->actingAs($staff->fresh());
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill))->assertForbidden();
        $this->actingAs($this->actor('owner'))->get(route('haruhay.deposits.index', $tenant))->assertOk()->assertDontSee('Save Deposit Entry');
    }

    public function test_reversal_reports_preserve_prior_dates_and_do_not_count_reversed_cash(): void
    {
        $this->travelTo(today()->subDays(3)->setTime(10, 0));
        $receivedOn = today()->toDateString();
        $admin = $this->actor();
        $tenant = $this->tenant($admin);
        $this->actingAs($admin);
        $bill = $this->issued($tenant);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => 3000]))->assertSessionHasNoErrors();
        $receipt = HaruhayCollection::sole();
        $this->travel(1)->days();
        $this->post(route('haruhay.collections.reverse', $receipt), ['reason' => 'Duplicate receipt correction'])->assertSessionHasNoErrors();
        $range = ['from' => $receivedOn, 'to' => $receivedOn];
        $this->get(route('haruhay.reports.index', $range))->assertOk()->assertViewHas('report', fn ($r) => $r['receivedTotal'] === 300000 && $r['outstanding'] === 770000);
        $this->get(route('haruhay.reports.index', array_replace($range, ['to' => today()->toDateString()])))->assertOk()->assertViewHas('report', fn ($r) => $r['receivedTotal'] === 0 && $r['outstanding'] === 1070000);
        $this->travelBack();
    }

    public function test_readonly_preset_overrides_old_role_grants_and_unknown_history_is_not_fabricated(): void
    {
        $admin = $this->actor();
        $staff = $this->actor('staff');
        $this->actingAs($admin);
        $readonly = \App\Models\Position::where('code', 'haruhay-read-only')->firstOrFail();
        $role = \App\Models\AccessRole::create(['name' => 'Old Cashier', 'slug' => 'old-cashier-test', 'scope' => 'company', 'rank' => 10, 'is_active' => true]);
        $role->permissions()->attach(\App\Models\Permission::where('slug', 'haruhay.collections.manage')->sole());
        $staff->update(['position_id' => $readonly->id, 'department_id' => $readonly->department_id, 'access_role_id' => $role->id]);
        $tenant = $this->tenant($admin);
        $room = $tenant->unit;
        $room->update(['created_at' => today()->subYear()]);
        $staff->haruhayProperties()->syncWithoutDetaching([$tenant->unit->property_id => ['is_active' => true, 'assigned_by' => $admin->id]]);
        $this->actingAs($staff->fresh())->post(route('haruhay.collections.store', $tenant), $this->payment())->assertForbidden();
        $this->actingAs($admin)->get(route('haruhay.reports.index', ['occupancy_on' => today()->subMonth()->toDateString()]))->assertOk()->assertSee('Not recorded')->assertViewHas('report', fn ($r) => $r['unknownUnits'] === 1 && $r['totalUnits'] === 0);
    }

    public function test_new_private_flows_reject_foreign_tenants_and_future_renewals_are_not_overwritten(): void
    {
        $admin = $this->actor();
        $tenant = $this->tenant($admin);
        $this->actingAs($admin);
        $bill = $this->issued($tenant);
        $old = $bill->contract;
        $future = $old->replicate(['code']);
        $future->fill(['code' => 'FUTURE-AGREEMENT', 'previous_contract_id' => $old->id, 'starts_on' => today()->addMonth(), 'ends_on' => today()->addYear()])->save();
        $other = $this->tenant($admin);
        $this->post(route('haruhay.transfers.store', $tenant), ['unit_id' => $other->unit_id, 'monthly_rent' => 15000, 'ends_on' => today()->addYear()->toDateString(), 'reason' => 'Must resolve scheduled contract'])->assertSessionHasErrors('unit_id',null,'transferForm');
        $this->assertSame(2,$tenant->contracts()->count());
        $foreign = $this->tenant($this->actor('admin','Foreign Company'));
        $this->get(route('haruhay.deposits.index',$foreign))->assertNotFound();
        $this->post(route('haruhay.deposits.store',$foreign),$this->deposit($foreign))->assertNotFound();
        $this->get(route('haruhay.transfers.create',$foreign))->assertNotFound();
    }
}
