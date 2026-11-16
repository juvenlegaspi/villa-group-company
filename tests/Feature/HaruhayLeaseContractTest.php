<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HaruhayLeaseContractTest extends TestCase
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

    public function test_early_move_in_suggests_actual_start_instead_of_old_reservation_date(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $tenant->update(['move_in_date' => today(), 'scheduled_move_in_date' => today()->addDays(5)]);
        $response = $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk();
        preg_match('/<input id="leaseStart"[^>]*>/', $response->getContent(), $input);
        $this->assertStringContainsString('value="'.today()->toDateString().'"', $input[0]);
    }

    public function test_duration_calculates_end_date_and_ignores_submitted_end(): void
    {
        $user = $this->actor(); $this->actingAs($user);
        foreach ([[1, '2028-02-28'], [3, '2028-04-29'], [6, '2028-07-30'], [12, '2029-01-30']] as [$months, $end]) {
            $tenant = $this->tenant($user);
            $contract = $this->add($tenant, ['starts_on' => '2028-01-31', 'duration_months' => $months, 'ends_on' => '2099-01-01', 'billing_starts_on' => '2028-01-31', 'first_due_on' => '2028-01-31']);
            $this->assertSame($end, $contract->ends_on->toDateString());
        }
        $tenant = $this->tenant($user);
        $this->post(route('haruhay.contracts.store', $tenant), $this->data(['duration_months' => 2]))->assertSessionHasErrors(['duration_months'], null, 'contractForm');
    }

    public function test_contract_rent_defaults_to_unit_rate_and_can_be_overridden(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $response = $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk();
        preg_match('/<input id="contractRent"[^>]*>/', $response->getContent(), $input);
        $this->assertNotEmpty($input);
        $this->assertStringContainsString('value="10000.00"', $input[0]);
        $this->assertStringNotContainsString('readonly', $input[0]);
        $contract = $this->add($tenant, ['monthly_rent' => '9,000']);
        $this->assertSame('9000.00', $contract->monthly_rent);
        $this->assertSame('10000.00', $tenant->unit->fresh()->monthly_rent);
    }

    public function test_tenant_directory_creation_snapshots_and_linked_tenant_column(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $this->get(route('haruhay.contracts.index'))->assertOk()->assertSee('Contract Tenant')->assertSee('No Contract Yet');
        $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk()->assertSee('Add Contract');
        $contract = $this->add($tenant, ['code' => 'FORGED', 'tenant_name' => 'Fake', 'unit_id' => 999]);
        $this->assertSame('CTR-0001', $contract->code);
        $this->assertSame('10000.00', $contract->monthly_rent);
        $this->assertSame('10000.00', $contract->first_month_charge);
        $this->assertSame('Contract Tenant', $contract->tenant_name);
        $this->assertSame($tenant->unit_id, $contract->unit_id);
        $tenant->unit->update(['monthly_rent' => 15000]);
        $tenant->update(['name' => 'Changed Name']);
        $this->assertSame('10000.00', $contract->fresh()->monthly_rent);
        $this->assertSame('Contract Tenant', $contract->fresh()->tenant_name);
        $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk()->assertSee('CTR-0001')->assertSee('10,000.00')->assertSee('Security Deposit Required');
        $this->get(route('haruhay.tenants.index'))->assertOk()->assertSee('CTR-0001');
        $this->get(route('haruhay.tenants.show', $tenant))->assertOk()->assertSee('CTR-0001');
        $this->get(route('haruhay.database', ['section' => 'lease-contracts']))->assertRedirect(route('haruhay.contracts.index'));
    }

    public function test_overlap_rejected_and_renewal_preserves_prior_terms(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $first = $this->add($tenant, ['ends_on' => today()->addDays(20)->toDateString()]);
        $this->post(route('haruhay.contracts.store', $tenant), $this->data())->assertSessionHasErrors(['previous_contract_id'], null, 'contractForm');
        $start = $first->ends_on->copy()->addDay()->toDateString();
        $renewal = $this->add($tenant, ['previous_contract_id' => $first->id, 'starts_on' => $start, 'ends_on' => $first->ends_on->copy()->addYearNoOverflow()->toDateString(), 'billing_starts_on' => $start, 'first_due_on' => $start, 'monthly_rent' => '12,000']);
        $this->assertSame($first->id, (int) $renewal->previous_contract_id);
        $this->assertSame('12000.00', $renewal->monthly_rent);
        $this->assertSame('10000.00', $first->fresh()->monthly_rent);
        $this->assertSame('Scheduled', $renewal->status);
        $this->assertSame($first->id, $tenant->fresh()->currentContract->id);
        $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk()->assertSee('Renewal of CTR-0001');
        $this->post(route('haruhay.contracts.store', $tenant), $this->data(['previous_contract_id' => $first->id]))->assertSessionHasErrors(['previous_contract_id'], null, 'contractForm');
        $this->assertDatabaseCount('haruhay_lease_contracts', 2);
    }

    public function test_contract_status_and_expiry_follow_dates_without_changing_occupancy(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $contract = $this->add($tenant, ['ends_on' => today()->addDays(15)->toDateString()]);
        $this->assertSame('Expiring Soon', $contract->status);
        $this->assertSame(15, $contract->days_to_expire);
        $this->travel(16)->days();
        $this->assertSame('Overdue', $contract->fresh()->status);
        $this->assertSame('Occupied', $tenant->unit->fresh()->status);
        $this->assertNull($tenant->fresh()->currentContract);
        $this->travelBack();
        $this->post(route('haruhay.contracts.terminate', [$tenant, $contract]), ['terminated_on' => today()->toDateString(), 'termination_reason' => 'Agreement ended early'])->assertSessionHasNoErrors();
        $this->assertSame('Terminated', $contract->fresh()->status);
        $this->assertSame('Occupied', $tenant->unit->fresh()->status);
    }

    public function test_private_documents_and_cross_tenant_access(): void
    {
        Storage::fake('local');
        $user = $this->actor(); $tenant = $this->tenant($user); $other = $this->tenant($user); $this->actingAs($user);
        $contract = $this->add($tenant, ['document' => UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf')]);
        Storage::disk('local')->assertExists($contract->document_path);
        $this->get(route('haruhay.contracts.document', [$tenant, $contract]))->assertDownload('signed.pdf');
        $this->get(route('haruhay.contracts.document', [$other, $contract]))->assertNotFound();
        $this->post(route('haruhay.contracts.store', $other), $this->data(['previous_contract_id' => $contract->id]))->assertSessionHasErrors(['previous_contract_id'], null, 'contractForm');
        $this->post(route('haruhay.contracts.terminate', [$other, $contract]), ['terminated_on' => today()->toDateString(), 'termination_reason' => 'Wrong tenant'])->assertNotFound();
        $this->post(route('haruhay.contracts.upload', [$tenant, $contract]), ['document' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf')])->assertStatus(422);
    }

    public function test_later_document_upload_and_validation(): void
    {
        Storage::fake('local');
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $contract = $this->add($tenant);
        $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk()->assertSee('Attach Signed Copy');
        $this->post(route('haruhay.contracts.upload', [$tenant, $contract]), ['document' => UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        Storage::disk('local')->assertExists($contract->fresh()->document_path);
        $this->post(route('haruhay.contracts.store', $tenant), $this->data(['ends_on' => today()->subYear()->toDateString(), 'deposit_required' => -1, 'due_day' => 32, 'advance_application' => '', 'first_month_policy' => 'prorated']))->assertSessionHasErrors(['ends_on', 'deposit_required', 'due_day', 'advance_application', 'first_month_charge'], null, 'contractForm');
        $this->assertDatabaseCount('haruhay_lease_contracts', 1);
    }

    public function test_current_contract_is_first_and_renewed_history_is_not_overdue(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $oldStart = today()->subYear()->toDateString();
        $old = $this->add($tenant, ['starts_on' => $oldStart, 'ends_on' => today()->subDay()->toDateString(), 'billing_starts_on' => $oldStart, 'first_due_on' => $oldStart]);
        $this->assertSame('Overdue', $old->status);
        $current = $this->add($tenant, ['previous_contract_id' => $old->id, 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDays(20)->toDateString(), 'billing_starts_on' => today()->toDateString(), 'first_due_on' => today()->toDateString()]);
        $futureStart = today()->addDays(21)->toDateString();
        $future = $this->add($tenant, ['previous_contract_id' => $current->id, 'starts_on' => $futureStart, 'ends_on' => today()->addYear()->toDateString(), 'billing_starts_on' => $futureStart, 'first_due_on' => $futureStart]);
        $this->assertSame('Renewed', $old->fresh()->status);
        $this->assertSame('Expiring Soon', $current->fresh()->status);
        $this->assertSame('Scheduled', $future->status);
        $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk()
            ->assertViewHas('focusContract', fn ($contract) => $contract->id === $current->id)
            ->assertViewHas('contracts', fn ($contracts) => $contracts->pluck('id')->all() === [$current->id, $future->id, $old->id])
            ->assertSee('id="contractView'.$current->id.'"', false);
        $this->get(route('haruhay.contracts.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['Overdue'] === 0);
        $this->travel(21)->days();
        $this->assertSame('Renewed', $current->fresh()->status);
        $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk()->assertViewHas('focusContract', fn ($contract) => $contract->id === $future->id);
        $this->travelBack();
    }

    public function test_overdue_summary_and_thirty_day_expiring_threshold(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $contract = $this->add($tenant, ['ends_on' => today()->addDays(31)->toDateString()]);
        $this->assertSame('Active', $contract->status);
        $this->travel(1)->days();
        $this->assertSame('Expiring Soon', $contract->fresh()->status);
        $this->travel(31)->days();
        $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk()->assertSee('Overdue — Needs Renewal');
        $this->get(route('haruhay.contracts.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['Overdue'] === 1);
        $this->assertSame('Occupied', $tenant->unit->fresh()->status);
        $this->travelBack();
    }

    public function test_contract_history_paginates_ten_rows_and_keeps_current_summary_on_later_pages(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $current = $this->add($tenant);
        foreach (range(1, 11) as $month) {
            $start = today()->subYears(2)->addMonthsNoOverflow($month);
            // Historical records are fixtures; new agreements must use renewal.
            HaruhayLeaseContract::create([
                'tenant_id' => $tenant->id, 'unit_id' => $tenant->unit_id, 'code' => 'OLD-'.$month,
                'tenant_name' => $tenant->name, 'property_name' => 'Haruhay Space', 'room_no' => $tenant->unit->unit_no, 'floor' => 3,
                'starts_on' => $start, 'ends_on' => $start->copy()->addMonthNoOverflow()->subDay(),
                'billing_starts_on' => $start, 'first_due_on' => $start, 'due_day' => 5,
                'monthly_rent' => 10000, 'billing_cycle' => 'calendar_month', 'first_month_policy' => 'full_month',
                'first_month_charge' => 10000, 'deposit_required' => 0, 'advance_required' => 0,
            ]);
        }
        $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk()
            ->assertViewHas('contracts', fn ($rows) => $rows->total() === 12 && $rows->count() === 10 && $rows->first()->id === $current->id)
            ->assertSee('12 contracts in history');
        $this->get(route('haruhay.contracts.tenant', ['tenant' => $tenant, 'page' => 2]))->assertOk()
            ->assertViewHas('contracts', fn ($rows) => $rows->currentPage() === 2 && $rows->count() === 2 && !$rows->contains('id', $current->id))
            ->assertViewHas('focusContract', fn ($contract) => $contract->id === $current->id)
            ->assertSee('12 contracts in history');
    }

    public function test_original_contract_is_added_once_and_only_due_contracts_can_be_renewed(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $original = $this->add($tenant);
        $this->get(route('haruhay.contracts.tenant', $tenant))->assertOk()->assertDontSee('Add Contract')->assertDontSee('id="contractModal"', false);
        $this->post(route('haruhay.contracts.store', $tenant), $this->data())->assertSessionHasErrors(['previous_contract_id'], null, 'contractForm');
        $this->get(route('haruhay.contracts.tenant', ['tenant' => $tenant, 'renew' => $original->id]))->assertStatus(422);
        $nextStart = $original->ends_on->copy()->addDay()->toDateString();
        $renewalData = ['previous_contract_id' => $original->id, 'starts_on' => $nextStart, 'ends_on' => $original->ends_on->copy()->addYearNoOverflow()->toDateString(), 'billing_starts_on' => $nextStart, 'first_due_on' => $nextStart];
        $this->post(route('haruhay.contracts.store', $tenant), $this->data($renewalData))->assertSessionHasErrors(['previous_contract_id'], null, 'contractForm');
        $this->assertDatabaseCount('haruhay_lease_contracts', 1);
        $this->travelTo($original->ends_on->copy()->subDays(30));
        $this->get(route('haruhay.contracts.tenant', ['tenant' => $tenant, 'renew' => $original->id]))->assertOk()->assertSee('Save Renewal');
        $this->add($tenant, $renewalData);
        $this->post(route('haruhay.contracts.store', $tenant), $this->data($renewalData))->assertSessionHasErrors(['previous_contract_id'], null, 'contractForm');
        $this->assertDatabaseCount('haruhay_lease_contracts', 2);
        $this->travelBack();
    }

    public function test_prorated_charge_anniversary_validation_and_company_scope(): void
    {
        $user = $this->actor(); $tenant = $this->tenant($user); $this->actingAs($user);
        $this->post(route('haruhay.contracts.store', $tenant), $this->data(['billing_cycle' => 'lease_anniversary', 'due_day' => 32]))->assertSessionHasErrors(['due_day'], null, 'contractForm');
        $contract = $this->add($tenant, ['billing_cycle' => 'lease_anniversary', 'due_day' => today()->subDays(10)->day, 'first_month_policy' => 'prorated', 'first_month_charge' => '5,250.50']);
        $this->assertSame('5250.50', $contract->first_month_charge);
        $foreign = $this->actor('staff', 'Yatira');
        $this->actingAs($foreign)->get(route('haruhay.contracts.index'))->assertForbidden();
        $this->get(route('haruhay.contracts.tenant', $tenant))->assertForbidden();
        foreach (['staff', 'owner'] as $role) {
            $this->actingAs($this->actor($role))->get(route('haruhay.contracts.tenant', $tenant))->assertOk()->assertDontSee('Add Contract');
            $this->post(route('haruhay.contracts.store', $tenant), $this->data())->assertForbidden();
            $this->post(route('haruhay.contracts.terminate', [$tenant, $contract]), ['terminated_on' => today()->toDateString(), 'termination_reason' => 'Not authorized'])->assertForbidden();
        }
    }
}
