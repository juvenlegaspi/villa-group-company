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

class HaruhayAuditTest extends TestCase
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

    public function test_month_end_billing_and_collection_totals_include_the_entire_last_day(): void
    {
        $this->travelTo(today()->endOfMonth()->setTime(13, 0));
        $user = $this->actor(); $this->actingAs($user); $tenant = $this->tenant($user); $contract = $this->add($tenant);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $bill = HaruhayBilling::sole();
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        $this->post(route('haruhay.collections.store', $tenant), ['receipt_type' => 'payment', 'billing_id' => $bill->id, 'submission_token' => (string) str()->uuid(), 'paid_on' => today()->toDateString(), 'amount' => 3000, 'method' => 'Cash'])->assertSessionHasNoErrors();
        $this->get(route('haruhay.billing.index'))->assertOk()->assertViewHas('stats', fn ($s) => (float) $s['Issued This Month'] === 10700.0 && (float) $s['Collected This Month'] === 3000.0);
        $this->get(route('haruhay.collections.index'))->assertOk()->assertViewHas('stats', fn ($s) => (float) $s['Collections This Month'] === 3000.0 && $s['Transactions This Month'] === 1);
        $this->travelBack();
    }

    public function test_transaction_filter_accepts_an_end_date_without_a_start_date(): void
    {
        $user = $this->actor(); $this->actingAs($user);
        $this->get(route('haruhay.collections.transactions', ['to' => today()->toDateString()]))->assertOk();
    }

    public function test_unit_amounts_reject_extra_decimal_places_instead_of_silent_rounding(): void
    {
        $user = $this->actor(); $this->actingAs($user); $tenant = $this->tenant($user);
        $this->post(route('haruhay.units.store'), ['property_id' => $tenant->unit->property_id, 'floor' => 1, 'unit_type' => 'Single', 'rent_basis' => 'per_room', 'room_condition' => 'Ready', 'monthly_rent' => '1000.999'])->assertSessionHasErrors('monthly_rent', null, 'unitForm');
        $this->assertDatabaseCount('haruhay_units', 1);
    }

    public function test_room_directory_keeps_numeric_order_beyond_room_99(): void
    {
        $user = $this->actor(); $this->actingAs($user); $tenant = $this->tenant($user);
        $template = $tenant->unit->only(['property_id', 'floor', 'unit_type', 'status', 'room_condition']);
        foreach ([98, 99, 100] as $id) HaruhayUnit::forceCreate(['id' => $id] + $template);
        $this->get(route('haruhay.units.index'))->assertOk()->assertViewHas('units', fn ($rooms) => $rooms->pluck('unit_no')->all() === ['DR-01', 'DR-98', 'DR-99', 'DR-100']);
    }

    public function test_collections_stay_with_original_property_after_tenant_transfer(): void
    {
        $user = $this->actor(); $this->actingAs($user); $tenant = $this->tenant($user); $oldProperty = $tenant->unit->property; $contract = $this->add($tenant);
        $receipt = ['submission_token' => (string) str()->uuid(), 'paid_on' => today()->toDateString(), 'amount' => 1000, 'method' => 'Cash', 'property_id' => 999];
        $this->post(route('haruhay.collections.store', $tenant), $receipt + ['receipt_type' => 'advance'])->assertSessionHasNoErrors();
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $bill = HaruhayBilling::sole(); $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        $receipt['submission_token'] = (string) str()->uuid();
        $this->post(route('haruhay.collections.store', $tenant), $receipt + ['receipt_type' => 'payment', 'billing_id' => $bill->id])->assertSessionHasNoErrors();
        $newTenant = $this->tenant($user); $newRoom = $newTenant->unit;
        $newRoom->property->update(['name' => 'Second Property']); $newRoom->update(['capacity' => 2]);
        $this->post(route('haruhay.transfers.store', $tenant), ['unit_id' => $newRoom->id, 'monthly_rent' => 10000, 'ends_on' => $contract->ends_on->toDateString(), 'reason' => 'Transfer while preserving original receipt property'])->assertSessionHasNoErrors();
        $oldProperty->update(['name' => 'Renamed Original Property']);
        $range = ['from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString()];
        $this->get(route('haruhay.reports.index', $range + ['property_id' => $oldProperty->id]))->assertOk()->assertViewHas('report', fn ($r) => $r['receivedTotal'] === 200000);
        $this->get(route('haruhay.reports.index', $range + ['property_id' => $newRoom->property_id]))->assertOk()->assertViewHas('report', fn ($r) => $r['receivedTotal'] === 0);
        $this->assertSame([$oldProperty->id], \App\Models\HaruhayCollection::pluck('property_id')->unique()->values()->all());
    }

    public function test_historical_room_cannot_be_reassigned_to_another_property(): void
    {
        $user = $this->actor(); $this->actingAs($user); $tenant = $this->tenant($user); $contract = $this->add($tenant);
        $newTenant = $this->tenant($user);
        $tenant->update(['status' => 'Moved Out']); $tenant->unit->update(['status' => 'Vacant']);
        $this->put(route('haruhay.units.update', $tenant->unit), ['property_id' => $newTenant->unit->property_id, 'floor' => 1, 'unit_type' => 'Single', 'rent_basis' => 'per_room', 'room_condition' => 'Ready'])->assertSessionHasErrors('property_id', null, 'unitForm');
        $this->assertSame($contract->unit->property_id, $tenant->unit->fresh()->property_id);
    }
}
