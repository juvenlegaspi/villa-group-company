<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayBilling;
use App\Models\HaruhayCollection;
use App\Services\HaruhayBillingSchedule;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HaruhayCollectionTest extends TestCase
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
        if (in_array($data['method'], ['Bank Transfer', 'GCash', 'Maya']) && !array_key_exists('proof', $overrides)) $data['proof'] = UploadedFile::fake()->image('transaction.png');
        return $data;
    }

    public function test_bill_payment_requires_issued_bill_but_advance_waits_for_final_utilities(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment(null, ['receipt_type' => 'payment']))->assertSessionHasErrors('billing_id', null, 'collectionForm');
        $this->get(route('haruhay.collections.tenant', $tenant))->assertOk()->assertSee('Receive Advance')->assertSee('data-receipt-mode="payment" disabled', false);
        $contract = $this->add($tenant, ['monthly_rent' => '3,000']);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment(null, ['amount' => '3,000']))->assertSessionHasNoErrors();
        $receipt = HaruhayCollection::sole();
        $this->assertSame('Advance Payment', $receipt->receipt_type);
        $this->assertSame(300000, $receipt->unapplied_cents);
        $draft = $this->invoiceData($contract, ['water' => '', 'electric' => '', 'parking' => 0, 'penalty' => 0, 'discount' => 0]);
        $this->post(route('haruhay.billing.store'), $draft)->assertSessionHasNoErrors();
        $bill = HaruhayBilling::sole();
        $this->post(route('haruhay.collections.apply', $receipt), ['billing_id' => $bill->id])->assertSessionHasErrors('billing_id', null, 'collectionForm');
        $this->assertSame('Draft / Pending Computation', $bill->fresh()->status);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['receipt_type' => 'advance']))->assertSessionHasErrors('billing_id', null, 'collectionForm');
        $this->put(route('haruhay.billing.update', $bill), array_replace($draft, ['water' => 100, 'electric' => 400, 'utilities_ready' => 1]))->assertSessionHasNoErrors();
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        $this->assertSame(50000, $bill->fresh()->balance_cents);
        $this->assertSame('Partially Paid', $bill->fresh()->payment_status);
        $this->assertSame(0, $receipt->fresh()->unapplied_cents);
        $this->get(route('haruhay.collections.index'))->assertOk()->assertSee('Remaining to Pay')->assertSee('Payments / advance applied: ₱3,000.00')
            ->assertViewHas('summaries', fn ($rows) => $rows[$tenant->id]['current']['invoice']->balance_cents === 50000);
        $this->get(route('haruhay.collections.tenant', $tenant))->assertOk()->assertSee('value="500.00"', false)
            ->assertDontSee('data-receipt-mode="advance"', false);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment(null))
            ->assertSessionHasErrors('receipt_type', null, 'collectionForm');
        $this->assertDatabaseCount('haruhay_collections', 1);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => 500]))->assertSessionHasNoErrors();
        $this->assertSame('Paid', $bill->fresh()->status);
        $this->get(route('haruhay.collections.tenant', $tenant))->assertOk()->assertSee('data-receipt-mode="advance"', false);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment(null))->assertSessionHasNoErrors();
        $this->assertSame(100000, $tenant->collections()->latest('id')->firstOrFail()->unapplied_cents);
    }

    public function test_unpaid_issued_bill_hides_and_blocks_advance_receipts(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $bill = $this->issued($tenant);
        $this->get(route('haruhay.collections.tenant', $tenant))->assertOk()
            ->assertSee('data-receipt-mode="payment"', false)->assertDontSee('data-receipt-mode="advance"', false);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment(null))
            ->assertSessionHasErrors('receipt_type', null, 'collectionForm');
        $this->assertDatabaseCount('haruhay_collections', 0);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => $bill->balance_cents / 100 + 1000]))
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $bill->fresh()->balance_cents);
        $this->assertSame(100000, HaruhayCollection::sole()->unapplied_cents);
    }

    public function test_issue_applies_oldest_credit_once_and_leaves_excess_for_next_month(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        foreach ([1000, 2500, 2000] as $amount) $this->post(route('haruhay.collections.store', $tenant), $this->payment(null, ['amount' => $amount]))->assertSessionHasNoErrors();
        $contract = $this->add($tenant, ['monthly_rent' => 4767]);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $bill = HaruhayBilling::sole();
        $this->assertSame('5467.00', $bill->total);
        $this->assertDatabaseCount('haruhay_billing_payments', 0);
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        $this->assertSame('Paid', $bill->fresh()->status);
        $receipts = HaruhayCollection::orderBy('id')->get();
        $this->assertSame(0, $receipts[0]->unapplied_cents);
        $this->assertSame(0, $receipts[1]->unapplied_cents);
        $this->assertSame(3300, $receipts[2]->unapplied_cents);
        app(\App\Services\HaruhayCollectionService::class)->applyAvailableCredit($tenant, $bill->id, $user->division_id);
        $this->assertDatabaseCount('haruhay_billing_payments', 3);
        $this->assertDatabaseCount('haruhay_collections', 3);
        $this->get(route('haruhay.collections.index'))->assertOk()->assertViewHas('stats', fn ($stats) => (float) $stats['Collections This Month'] === 5500.0);
        $this->get(route('haruhay.billing.index'))->assertOk()->assertViewHas('stats', fn ($stats) => (float) $stats['Collected This Month'] === 5500.0);
    }

    public function test_existing_unapplied_credit_can_still_be_applied_manually(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user); $bill = $this->issued($tenant);
        // Preserve support for historical unapplied receipts created before the advance restriction.
        $receipt = HaruhayCollection::create([
            'code' => 'OR-LEGACY', 'submission_token' => (string) str()->uuid(), 'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name, 'property_id' => $tenant->unit->property_id,
            'property_name' => $tenant->unit->property->name, 'room_no' => $tenant->unit->unit_no,
            'paid_on' => today(), 'amount' => 3000, 'method' => 'Cash', 'created_by' => $user->id,
        ]);
        $this->post(route('haruhay.collections.apply', $receipt), ['billing_id' => $bill->id])->assertSessionHasNoErrors();
        $this->assertSame(770000, $bill->fresh()->balance_cents);
    }

    public function test_online_methods_require_private_image_proof_and_cash_does_not(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        foreach (['Bank Transfer', 'GCash', 'Maya'] as $method) {
            $this->post(route('haruhay.collections.store', $tenant), $this->payment(null, ['method' => $method, 'external_reference' => 'PROOF-'.$method, 'proof' => null]))->assertSessionHasErrors('proof', null, 'collectionForm');
            $this->post(route('haruhay.collections.store', $tenant), $this->payment(null, ['method' => $method, 'external_reference' => 'PROOF-'.$method]))->assertSessionHasNoErrors();
            $receipt = HaruhayCollection::latest('id')->first();
            Storage::disk('local')->assertExists($receipt->proof_path);
            $this->get(route('haruhay.collections.show', $receipt))->assertOk()->assertSee('View Full Image');
            $this->get(route('haruhay.collections.proof', $receipt))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->post(route('haruhay.collections.store', $tenant), $this->payment())->assertSessionHasNoErrors();
        $cash = HaruhayCollection::latest('id')->first();
        $this->assertNull($cash->proof_path);
        $this->get(route('haruhay.collections.proof', $cash))->assertNotFound();
    }

    public function test_invalid_proofs_and_foreign_receipt_access_are_rejected(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        foreach ([UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'), UploadedFile::fake()->image('large.png')->size(10241)] as $proof) {
            $this->post(route('haruhay.collections.store', $tenant), $this->payment(null, ['method' => 'GCash', 'external_reference' => 'INVALID', 'proof' => $proof]))->assertSessionHasErrors('proof', null, 'collectionForm');
        }
        $this->assertDatabaseCount('haruhay_collections', 0);
        $data = $this->payment(null, ['method' => 'GCash', 'external_reference' => 'IMAGE-REF']);
        $this->post(route('haruhay.collections.store', $tenant), $data)->assertSessionHasNoErrors();
        $this->post(route('haruhay.collections.store', $tenant), $data)->assertSessionHasNoErrors();
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $receipt = HaruhayCollection::sole();
        $foreign = $this->tenant($this->actor('admin', 'Other Company'));
        $receipt->update(['tenant_id' => $foreign->id]);
        $this->get(route('haruhay.collections.proof', $receipt))->assertNotFound();
    }

    public function test_partial_and_full_payments_update_billing_and_unlock_next_month(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user); $bill = $this->issued($tenant);
        $this->get(route('haruhay.database', ['section' => 'collections']))->assertRedirect(route('haruhay.collections.index'));
        $this->get(route('haruhay.collections.index'))->assertOk()->assertSee($tenant->code)->assertSee('All Transactions');
        $this->get(route('haruhay.collections.tenant', $tenant))->assertOk()->assertSee('Record Payment')->assertSee('10,700.00');
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['created_by' => 999, 'amount_due' => 1]))->assertSessionHasNoErrors()->assertRedirect();
        $receipt = HaruhayCollection::sole();
        $this->assertSame('OR-00001', $receipt->code);
        $this->assertSame($user->id, $receipt->created_by);
        $this->assertSame('10700.00', $receipt->amount_due);
        $this->assertSame(0, $receipt->unapplied_cents);
        $this->assertSame('Overdue', $bill->fresh()->status);
        $this->assertSame(970000, $bill->fresh()->balance_cents);
        $this->get(route('haruhay.collections.show', $receipt))->assertOk()->assertSee('Payment Applications')->assertSee('OR-00001')->assertSee('9,700.00');
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => '9,700', 'method' => 'GCash', 'external_reference' => 'GCASH-123']))->assertSessionHasNoErrors();
        $this->assertSame('Paid', $bill->fresh()->status);
        $this->get(route('haruhay.billing.tenant', ['tenant' => $tenant, 'tab' => 'paid']))->assertOk()->assertSee($bill->code);
        $this->get(route('haruhay.billing.tenant', $tenant))->assertOk()->assertViewHas('currentKey', fn ($key) => $key !== $bill->contract_id.':'.$bill->period_start->toDateString());
        $this->get(route('haruhay.collections.transactions'))->assertOk()->assertSee('OR-00002')->assertSee('GCASH-123');
    }

    public function test_directory_status_branches_do_not_render_blade_directives(): void
    {
        $user = $this->actor(); $this->actingAs($user);
        $issuedTenant = $this->tenant($user); $this->issued($issuedTenant);
        $awaitingTenant = $this->tenant($user); $this->add($awaitingTenant);
        $this->tenant($user);
        $this->get(route('haruhay.collections.index'))->assertOk()
            ->assertSee('Unpaid')->assertSee('Awaiting issued bill')->assertSee('No current bill')
            ->assertDontSee('@else', false)->assertDontSee('@endif', false)->assertDontSee('@if(', false);
    }

    public function test_directory_shows_next_contract_period_before_bill_is_created(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $contract = $this->add($tenant);
        $periods = (new HaruhayBillingSchedule)->periods($contract);
        $this->get(route('haruhay.collections.index'))->assertOk()->assertSee('Next Billing / Due')->assertSee('Expected rent: ₱10,000.00')
            ->assertViewHas('summaries', fn ($rows) => $rows[$tenant->id]['next']['period']['start'] === $periods[0]['start']);
        $this->assertDatabaseCount('haruhay_billings', 0);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $bill = HaruhayBilling::sole();
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        $this->get(route('haruhay.collections.index'))->assertOk()->assertViewHas('summaries', fn ($rows) => $rows[$tenant->id]['current']['invoice']->id === $bill->id && $rows[$tenant->id]['next']['period']['start'] === $periods[1]['start']);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => '10,700']))->assertSessionHasNoErrors();
        $this->get(route('haruhay.collections.index'))->assertOk()->assertSee('Rent only')->assertDontSee('@else', false)
            ->assertViewHas('summaries', fn ($rows) => $rows[$tenant->id]['next']['period']['start'] === $periods[1]['start'] && $rows[$tenant->id]['next']['invoice'] === null);
        $this->assertDatabaseCount('haruhay_billings', 1);
    }

    public function test_payment_amount_defaults_to_remaining_balance_and_remains_editable(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user); $bill = $this->issued($tenant);
        $response = $this->get(route('haruhay.collections.tenant', $tenant))->assertOk();
        preg_match('/<input[^>]*id="receiptAmount"[^>]*>/', $response->getContent(), $input);
        $this->assertStringContainsString('value="10,700.00"', $input[0]);
        $this->assertStringNotContainsString('readonly', $input[0]);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => '3,000']))->assertSessionHasNoErrors();
        $response = $this->get(route('haruhay.collections.tenant', $tenant))->assertOk();
        preg_match('/<input[^>]*id="receiptAmount"[^>]*>/', $response->getContent(), $input);
        $this->assertStringContainsString('value="7,700.00"', $input[0]);
        $this->from(route('haruhay.collections.tenant', $tenant))->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => '500', 'method' => 'GCash']))->assertSessionHasErrors('external_reference', null, 'collectionForm');
        $response = $this->get(route('haruhay.collections.tenant', $tenant))->assertOk();
        preg_match('/<input[^>]*id="receiptAmount"[^>]*>/', $response->getContent(), $input);
        $this->assertStringContainsString('value="500"', $input[0]);
    }

    public function test_receipt_allocation_is_not_confused_with_bill_payment_status(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $contract = $this->add($tenant, ['monthly_rent' => '4,767']);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $bill = HaruhayBilling::sole();
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => '3,000']))->assertSessionHasNoErrors();
        $receipt = HaruhayCollection::sole();
        $this->assertSame('Fully Applied', $receipt->status);
        $this->assertSame('Partially Paid', $bill->fresh()->payment_status);
        $this->assertSame('Overdue', $bill->fresh()->status);
        $this->assertSame(246700, $bill->fresh()->balance_cents);
        foreach ([route('haruhay.collections.tenant', $tenant), route('haruhay.collections.transactions'), route('haruhay.collections.show', $receipt)] as $url) {
            $this->get($url)->assertOk()->assertSee('Partially Paid')->assertSee('2,467.00')->assertSee('Overdue')->assertDontSee('Fully Applied');
        }
        $this->get(route('haruhay.collections.index'))->assertOk()->assertSee('Partially Paid');
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => '2,467']))->assertSessionHasNoErrors();
        $this->assertSame('Paid', $bill->fresh()->payment_status);
        $this->get(route('haruhay.collections.show', $receipt))->assertOk()->assertSee('Remaining Balance: ₱0.00')->assertDontSee('Partially Paid');
    }

    public function test_excess_credit_applies_to_next_bill_without_double_counting_cash(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user); $bill = $this->issued($tenant);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => '12,000']))->assertSessionHasNoErrors();
        $receipt = HaruhayCollection::sole();
        $this->assertSame(130000, $receipt->unapplied_cents);
        $periods = (new HaruhayBillingSchedule)->periods($bill->contract);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($bill->contract, ['period_start' => $periods[1]['start'], 'utilities_ready' => 1]))->assertSessionHasNoErrors();
        $next = HaruhayBilling::latest('id')->first();
        $this->post(route('haruhay.collections.apply', $receipt), ['billing_id' => $next->id])->assertSessionHasErrors('billing_id', null, 'collectionForm');
        $this->post(route('haruhay.billing.issue', $next))->assertSessionHasNoErrors();
        $this->get(route('haruhay.collections.tenant', $tenant))->assertOk()->assertDontSee('Available Credit Receipts');
        $this->assertSame(0, $receipt->fresh()->unapplied_cents);
        $this->assertSame(940000, $next->fresh()->balance_cents);
        $this->assertDatabaseCount('haruhay_collections', 1);
        $this->assertDatabaseCount('haruhay_billing_payments', 2);
        $this->get(route('haruhay.collections.index'))->assertOk()->assertViewHas('stats', fn ($stats) => (float) $stats['Collections This Month'] === 12000.0 && (int) $stats['Transactions This Month'] === 1);
        $this->get(route('haruhay.billing.index'))->assertOk()->assertViewHas('stats', fn ($stats) => (float) $stats['Collected This Month'] === 12000.0);
        $this->post(route('haruhay.collections.apply', $receipt), ['billing_id' => $next->id])->assertSessionHasErrors('billing_id', null, 'collectionForm');
    }

    public function test_duplicate_submission_is_idempotent_and_duplicate_reference_rejected(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user); $bill = $this->issued($tenant);
        $data = $this->payment($bill, ['method' => 'Bank Transfer', 'external_reference' => 'BANK-UNIQUE']);
        $this->post(route('haruhay.collections.store', $tenant), $data)->assertSessionHasNoErrors();
        $this->post(route('haruhay.collections.store', $tenant), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('haruhay_collections', 1);
        $this->assertDatabaseCount('haruhay_billing_payments', 1);
        $this->post(route('haruhay.collections.store', $tenant), array_replace($data, ['amount' => 2000]))->assertSessionHasErrors('amount', null, 'collectionForm');
        $this->post(route('haruhay.collections.store', $tenant), array_replace($data, ['submission_token' => (string) str()->uuid()]))->assertSessionHasErrors('external_reference', null, 'collectionForm');
        $this->assertSame(970000, $bill->fresh()->balance_cents);
    }

    public function test_unapplied_receipts_before_invoice_and_validation_reopen_modal(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment(null, ['amount' => '5,000']))->assertSessionHasNoErrors();
        $receipt = HaruhayCollection::sole();
        $this->assertSame('Unapplied', $receipt->status);
        $this->assertSame(500000, $receipt->unapplied_cents);
        $this->assertDatabaseCount('haruhay_billing_payments', 0);
        $this->get(route('haruhay.collections.show', $receipt))->assertOk()->assertSee('Held as unapplied credit');
        $this->from(route('haruhay.collections.tenant', $tenant))->post(route('haruhay.collections.store', $tenant), $this->payment(null, ['amount' => '-1', 'paid_on' => today()->addDay()->toDateString(), 'method' => 'GCash']))->assertSessionHasErrors(['amount', 'paid_on', 'external_reference'], null, 'collectionForm');
        $this->get(route('haruhay.collections.tenant', $tenant))->assertOk()->assertSee("getElementById('collectionModal')", false);
    }

    public function test_draft_other_tenant_paid_and_future_bills_cannot_receive_payment(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user); $contract = $this->add($tenant);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $bill = HaruhayBilling::sole();
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill))->assertSessionHasErrors('billing_id', null, 'collectionForm');
        $other = $this->tenant($user);
        $this->post(route('haruhay.collections.store', $other), $this->payment($bill))->assertSessionHasErrors('billing_id', null, 'collectionForm');
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['paid_on' => today()->subDay()->toDateString()]))->assertSessionHasErrors('paid_on', null, 'collectionForm');
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill, ['amount' => '10,700']))->assertSessionHasNoErrors();
        $this->post(route('haruhay.collections.store', $tenant), $this->payment($bill))->assertSessionHasErrors('billing_id', null, 'collectionForm');
        $this->assertDatabaseCount('haruhay_collections', 1);
    }

    public function test_readonly_company_scope_and_transaction_filters_and_pagination(): void
    {
        $admin = $this->actor(); $tenant = $this->tenant($admin); $this->actingAs($admin);
        for ($i = 0; $i < 11; $i++) $this->post(route('haruhay.collections.store', $tenant), $this->payment(null, ['method' => $i === 0 ? 'GCash' : 'Cash', 'external_reference' => $i === 0 ? 'FILTER-REF' : '']))->assertSessionHasNoErrors();
        $receipt = HaruhayCollection::first();
        $this->get(route('haruhay.collections.transactions', ['method' => 'GCash', 'search' => 'FILTER-REF', 'received_by' => $admin->id, 'from' => today()->toDateString(), 'to' => today()->toDateString()]))->assertOk()->assertViewHas('receipts', fn ($rows) => $rows->total() === 1);
        $this->get(route('haruhay.collections.transactions', ['page' => 2]))->assertOk()->assertViewHas('receipts', fn ($rows) => $rows->perPage() === 10 && $rows->count() === 1);
        $this->get(route('haruhay.collections.tenant', $tenant))->assertOk()->assertViewHas('receipts', fn ($rows) => $rows->perPage() === 10 && $rows->total() === 11);
        $owner = $this->actor('owner'); $this->actingAs($owner);
        $this->get(route('haruhay.collections.tenant', $tenant))->assertOk()->assertDontSee('id="collectionModal"', false);
        $this->post(route('haruhay.collections.store', $tenant), $this->payment())->assertForbidden();
        $this->post(route('haruhay.collections.apply', $receipt), ['billing_id' => 1])->assertForbidden();
        $foreignAdmin = $this->actor('admin', 'Other Company'); $foreignTenant = $this->tenant($foreignAdmin);
        $this->actingAs($admin)->get(route('haruhay.collections.tenant', $foreignTenant))->assertNotFound();
        $this->post(route('haruhay.collections.store', $foreignTenant), $this->payment())->assertNotFound();
        $foreignReceipt = $receipt->replicate();
        $foreignReceipt->fill(['tenant_id' => $foreignTenant->id, 'code' => 'FOREIGN-OR', 'submission_token' => (string) str()->uuid(), 'external_reference' => 'FOREIGN-REF'])->save();
        $this->get(route('haruhay.collections.show', $foreignReceipt))->assertNotFound();
        $this->post(route('haruhay.collections.apply', $foreignReceipt), ['billing_id' => 1])->assertNotFound();
        $this->get(route('haruhay.collections.transactions'))->assertOk()->assertDontSee('FOREIGN-OR');
    }
}
