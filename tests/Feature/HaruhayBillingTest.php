<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayBilling;
use App\Services\HaruhayBillingSchedule;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HaruhayBillingTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_invoice_snapshots_contract_terms_totals_and_tenant_history(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $contract = $this->add($tenant);
        $this->get(route('haruhay.database', ['section' => 'billing']))->assertRedirect(route('haruhay.billing.index'));
        $this->get(route('haruhay.billing.index'))->assertOk()->assertSee('Tenant Billing Directory')->assertSee($tenant->code)->assertDontSee('Create Invoice');
        $this->get(route('haruhay.billing.tenant', $tenant))->assertOk()->assertSee('Monthly Billing History')->assertSee('Pending');
        $this->get(route('haruhay.billing.tenant', ['tenant' => $tenant, 'contract' => $contract->id, 'period' => $contract->starts_on->toDateString()]))->assertOk()->assertSee('id="billingModal"', false)->assertSee('Save Draft');
        $this->assertDatabaseCount('haruhay_billings', 0);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['rent' => '1', 'total' => '1', 'due_on' => '2099-01-01', 'tenant_id' => 999]))->assertSessionHasNoErrors()->assertRedirect();
        $invoice = HaruhayBilling::sole();
        $this->assertSame('INV-00001', $invoice->code);
        $this->assertSame($tenant->id, $invoice->tenant_id);
        $this->assertSame('10000.00', $invoice->rent);
        $this->assertSame('10700.00', $invoice->total);
        $this->assertSame($contract->first_due_on->toDateString(), $invoice->due_on->toDateString());
        $tenant->unit->update(['monthly_rent' => 20000]);
        $tenant->update(['name' => 'Renamed Tenant']);
        $this->get(route('haruhay.billing.show', $invoice))->assertOk()->assertSee('Contract Tenant')->assertSee('10,700.00')->assertSee('Charge Breakdown')->assertSee('Tenant Billing History');
        $this->get(route('haruhay.billing.index', ['tenant_id' => $tenant->id]))->assertRedirect(route('haruhay.billing.tenant', $tenant));
        $this->get(route('haruhay.billing.tenant', $tenant))->assertOk()->assertSee($invoice->code);
        $this->get(route('haruhay.tenants.show', $tenant))->assertOk()->assertSee('View Billing History');
    }

    public function test_directory_previews_next_bill_without_creating_invoices(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user); $contract = $this->add($tenant);
        $periods = (new HaruhayBillingSchedule)->periods($contract);
        $this->get(route('haruhay.billing.index'))->assertOk()->assertSee('Next Billing / Due')->assertSee('Expected rent: ₱10,000.00')
            ->assertViewHas('nextBills', fn ($rows) => $rows[$tenant->id]['period']['start'] === $periods[0]['start']);
        $this->assertDatabaseCount('haruhay_billings', 0);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $bill = HaruhayBilling::sole();
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        $this->get(route('haruhay.billing.index'))->assertOk()->assertViewHas('nextBills', fn ($rows) => $rows[$tenant->id]['period']['start'] === $periods[1]['start']);
        $bill->payments()->create(['amount' => $bill->total, 'paid_on' => today(), 'reference' => 'PREVIEW-FULL-PAYMENT']);
        $this->get(route('haruhay.billing.index'))->assertOk()->assertSee('Rent only')->assertDontSee('@else', false)
            ->assertViewHas('nextBills', fn ($rows) => $rows[$tenant->id]['period']['start'] === $periods[1]['start'] && $rows[$tenant->id]['invoice'] === null);
        $this->assertDatabaseCount('haruhay_billings', 1);
    }

    public function test_duplicates_invalid_periods_and_invalid_charges_are_rejected(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user); $contract = $this->add($tenant);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['period_start' => '1990-01-01']))->assertSessionHasErrors('period_start', null, 'billingForm');
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['discount' => 999999]))->assertSessionHasErrors('discount', null, 'billingForm');
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['water' => '-1', 'electric' => '5.999']))->assertSessionHasErrors(['water', 'electric'], null, 'billingForm');
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract))->assertSessionHasNoErrors();
        $this->from(route('haruhay.billing.index'))->post(route('haruhay.billing.store'), $this->invoiceData($contract))->assertSessionHasErrors('period_start', null, 'billingForm');
        $this->get(route('haruhay.billing.tenant', $tenant))->assertOk()->assertSee('This tenant already has an invoice');
        $this->assertDatabaseCount('haruhay_billings', 1);
    }

    public function test_schedule_handles_calendar_anniversary_leap_year_and_termination(): void
    {
        $schedule = new HaruhayBillingSchedule;
        $contract = new HaruhayLeaseContract(['starts_on' => '2028-01-31', 'ends_on' => '2028-04-29', 'billing_starts_on' => '2028-02-29', 'first_due_on' => '2028-02-29', 'due_day' => 31, 'billing_cycle' => 'lease_anniversary', 'monthly_rent' => 10000, 'first_month_charge' => 5000]);
        $periods = $schedule->periods($contract);
        $this->assertSame(['2028-01-31', '2028-02-29', '2028-03-31'], array_column($periods, 'start'));
        $this->assertSame(['2028-02-28', '2028-03-30', '2028-04-29'], array_column($periods, 'end'));
        $this->assertSame('2028-03-29', $periods[1]['due']);
        $this->assertSame('5000.00', $periods[0]['rent']);
        $contract->billing_cycle = 'calendar_month';
        $periods = $schedule->periods($contract);
        $this->assertSame('2028-01-31', $periods[0]['end']);
        $this->assertSame('2028-02-01', $periods[1]['start']);
        $this->assertSame('2028-03-31', $periods[1]['due']);
        $contract->terminated_on = '2028-02-15';
        $periods = $schedule->periods($contract);
        $this->assertCount(2, $periods);
        $this->assertSame('2028-02-15', $periods[1]['end']);
    }

    public function test_status_balance_dashboard_and_history_pagination_use_only_issued_bills(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $contract = $this->add($tenant, ['starts_on' => today()->toDateString(), 'ends_on' => today()->addYears(2)->toDateString(), 'billing_starts_on' => today()->toDateString(), 'first_due_on' => today()->toDateString()]);
        $periods = (new HaruhayBillingSchedule)->periods($contract);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['period_start' => $periods[0]['start']]))->assertSessionHasNoErrors();
        $invoice = HaruhayBilling::first();
        $this->assertSame('Draft / Pending Computation', $invoice->status);
        $this->get(route('haruhay.billing.index'))->assertOk()->assertViewHas('stats', fn ($stats) => (float) $stats['Outstanding · Issued Bills'] === 0.0);
        $this->put(route('haruhay.billing.update', $invoice), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $this->post(route('haruhay.billing.issue', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('Unpaid', $invoice->status);
        $invoice->payments()->create(['amount' => 700, 'paid_on' => today(), 'reference' => 'TEST-RECEIPT-1']);
        $this->assertSame('Partially Paid', $invoice->fresh()->status);
        $this->assertSame(1000000, $invoice->fresh()->balance_cents);
        $this->get(route('haruhay.billing.tenant', $tenant))->assertOk()->assertSee('Partially Paid')->assertViewHas('balance', 1000000);
        $invoice->update(['due_on' => today()->subDay()]);
        $this->assertSame('Overdue', $invoice->fresh()->status);
        $this->get(route('haruhay.billing.tenant', $tenant))->assertOk()->assertSee('Overdue');
        $invoice->payments()->create(['amount' => 10000, 'paid_on' => today(), 'reference' => 'TEST-RECEIPT-2']);
        $this->assertSame('Paid', $invoice->fresh()->status);
        $this->get(route('haruhay.billing.index'))->assertOk()->assertViewHas('stats', fn ($stats) => (float) $stats['Collected This Month'] === 10700.0);
        $this->get(route('haruhay.billing.tenant', ['tenant' => $tenant, 'page' => 2]))->assertOk()->assertViewHas('bills', fn ($rows) => $rows->perPage() === 10 && $rows->count() === 10);
    }

    public function test_pending_utilities_can_be_saved_updated_and_issued_once_then_locked(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user); $contract = $this->add($tenant);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['water' => '', 'electric' => '', 'finalized_at' => now(), 'issued_on' => today()]))->assertSessionHasNoErrors();
        $invoice = HaruhayBilling::sole();
        $this->assertNull($invoice->water);
        $this->assertNull($invoice->electric);
        $this->assertNull($invoice->issued_on);
        $this->assertNull($invoice->finalized_at);
        $this->get(route('haruhay.billing.show', $invoice))->assertOk()->assertSee('Pending computation')->assertSee('Update Billing')->assertSee('Provisional Total');
        $this->post(route('haruhay.billing.issue', $invoice))->assertSessionHasErrors('utilities_ready', null, 'billingForm');
        $this->put(route('haruhay.billing.update', $invoice), $this->invoiceData($contract, ['water' => '', 'utilities_ready' => 1]))->assertSessionHasErrors('utilities_ready', null, 'billingForm');
        $this->put(route('haruhay.billing.update', $invoice), $this->invoiceData($contract, ['water' => '0', 'electric' => '1,250.50', 'utilities_ready' => 1]))->assertSessionHasNoErrors();
        $this->assertSame('11350.50', $invoice->fresh()->total);
        $this->post(route('haruhay.billing.issue', $invoice))->assertSessionHasNoErrors();
        $this->assertNotNull($invoice->fresh()->finalized_at);
        $this->assertSame(today()->toDateString(), $invoice->fresh()->issued_on->toDateString());
        $this->get(route('haruhay.billing.show', $invoice))->assertOk()->assertDontSee('Update Billing')->assertDontSee('id="billingModal"', false)->assertSee('Charges are finalized and locked');
        $this->put(route('haruhay.billing.update', $invoice), $this->invoiceData($contract))->assertSessionHasErrors('billing', null, 'billingForm');
        $this->post(route('haruhay.billing.issue', $invoice))->assertSessionHasErrors('billing', null, 'billingForm');
        $this->assertSame('11350.50', $invoice->fresh()->total);
    }

    public function test_tenants_appear_without_bills_and_draft_update_errors_reopen_popup(): void
    {
        $user = $this->actor(); $this->actingAs($user);
        for ($i = 0; $i < 11; $i++) $tenant = $this->tenant($user);
        $this->get(route('haruhay.billing.index'))->assertOk()->assertViewHas('tenants', fn ($rows) => $rows->total() === 11 && $rows->perPage() === 10);
        $this->get(route('haruhay.billing.tenant', $tenant))->assertOk()->assertSee('No contract billing periods yet');
        $contract = $this->add($tenant);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract))->assertSessionHasNoErrors();
        $invoice = HaruhayBilling::sole();
        $this->from(route('haruhay.billing.show', $invoice))->put(route('haruhay.billing.update', $invoice), $this->invoiceData($contract, ['electric' => -10]))->assertSessionHasErrors('electric', null, 'billingForm');
        $this->get(route('haruhay.billing.show', $invoice))->assertOk()->assertSee("getElementById('billingModal')", false)->assertSee('value="-10"', false);
        $this->assertSame('500.00', $invoice->fresh()->electric);
    }

    public function test_only_current_month_unlocks_and_paid_history_is_separate_readonly(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user); $contract = $this->add($tenant);
        $periods = (new HaruhayBillingSchedule)->periods($contract);
        $first = $periods[0]; $next = $periods[1];
        $nextUrl = route('haruhay.billing.tenant', ['tenant' => $tenant, 'contract' => $contract->id, 'period' => $next['start']]);
        $this->get(route('haruhay.billing.tenant', $tenant))->assertOk()->assertSee('Current / Upcoming Bills')->assertSee('Paid History')->assertSee('Locked')->assertViewHas('currentKey', $contract->id.':'.$first['start']);
        $this->get($nextUrl)->assertForbidden();
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['period_start' => $next['start']]))->assertSessionHasErrors('billing', null, 'billingForm');
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $bill = HaruhayBilling::sole();
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        $bill->payments()->create(['amount' => 700, 'paid_on' => today(), 'reference' => 'PARTIAL']);
        $this->get($nextUrl)->assertForbidden();
        $this->get(route('haruhay.billing.tenant', ['tenant' => $tenant, 'tab' => 'paid']))->assertOk()->assertSee('No fully paid bills yet.')->assertDontSee($bill->code);
        $bill->payments()->create(['amount' => 10000, 'paid_on' => today(), 'reference' => 'REMAINDER']);
        $this->get(route('haruhay.billing.tenant', $tenant))->assertOk()->assertDontSee($bill->code)->assertViewHas('currentKey', $contract->id.':'.$next['start']);
        $this->get(route('haruhay.billing.tenant', ['tenant' => $tenant, 'tab' => 'paid']))->assertOk()->assertSee($bill->code)->assertSee('Paid');
        $this->get(route('haruhay.billing.show', $bill))->assertOk()->assertDontSee('Update Billing')->assertDontSee('id="billingModal"', false);
        $this->get($nextUrl)->assertOk()->assertSee('Save Draft');
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['period_start' => $next['start']]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('haruhay_billings', 2);
    }

    public function test_existing_upcoming_drafts_cannot_be_updated_or_issued_out_of_order(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user); $contract = $this->add($tenant);
        $periods = (new HaruhayBillingSchedule)->periods($contract);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $first = HaruhayBilling::sole();
        // Simulate a future draft created before the sequential billing workflow.
        $future = $first->replicate();
        $future->fill(['code' => 'LEGACY-FUTURE', 'period_start' => $periods[1]['start'], 'period_end' => $periods[1]['end'], 'due_on' => $periods[1]['due']])->save();
        $this->get(route('haruhay.billing.show', $future))->assertOk()->assertSee('This upcoming bill is locked')->assertDontSee('Update Billing')->assertDontSee('id="billingModal"', false);
        $this->put(route('haruhay.billing.update', $future), $this->invoiceData($contract, ['water' => '900']))->assertSessionHasErrors('billing', null, 'billingForm');
        $this->post(route('haruhay.billing.issue', $future))->assertSessionHasErrors('billing', null, 'billingForm');
        $this->assertSame('100.00', $future->fresh()->water);
        $this->assertNull($future->fresh()->finalized_at);
    }

    public function test_readonly_foreign_company_and_cancelled_tenant_protections(): void
    {
        $admin = $this->actor(); $tenant = $this->tenant($admin); $this->actingAs($admin); $contract = $this->add($tenant);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract))->assertSessionHasNoErrors();
        $invoice = HaruhayBilling::sole();
        $viewer = $this->actor('owner'); $this->actingAs($viewer);
        $this->get(route('haruhay.billing.index'))->assertOk()->assertDontSee('id="billingModal"', false);
        $this->get(route('haruhay.billing.show', $invoice))->assertOk();
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract))->assertForbidden();
        $this->put(route('haruhay.billing.update', $invoice), $this->invoiceData($contract))->assertForbidden();
        $this->post(route('haruhay.billing.issue', $invoice))->assertForbidden();
        $this->get(route('haruhay.billing.tenant', $tenant))->assertOk()->assertDontSee('id="billingModal"', false);
        $foreign = $this->actor('admin', 'Other Company'); $otherTenant = $this->tenant($foreign);
        $otherContract = $contract->replicate(['code']); $otherContract->fill(['code' => 'FOREIGN', 'tenant_id' => $otherTenant->id, 'unit_id' => $otherTenant->unit_id])->save();
        $this->actingAs($admin)->post(route('haruhay.billing.store'), $this->invoiceData($otherContract))->assertNotFound();
        $this->get(route('haruhay.billing.tenant', $otherTenant))->assertNotFound();
        $tenant->update(['status' => 'Cancelled']);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract))->assertSessionHasErrors('contract_id', null, 'billingForm');
    }
}
