<?php

namespace Tests\Feature;

use App\Models\AccessRole;
use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayBilling;
use App\Models\HaruhayCollection;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayProperty;
use App\Models\HaruhayReservation;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Models\Position;
use App\Models\User;
use App\Notifications\HaruhayReservationRequested;
use App\Services\HaruhayReservationAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HaruhayPropertyAccessTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(string $positionCode = 'haruhay-property-staff'): array
    {
        $company = Division::whereRaw('LOWER(name) = ?', ['haruhay'])->sole();
        $department = Department::where('division_id', $company->id)->where('name', 'Property Management')->sole();
        $admin = User::create(['name' => 'Property Admin', 'lastname' => 'Test', 'username' => 'scope-admin', 'email' => 'scope-admin@example.com', 'password' => 'test-password', 'division_id' => $company->id, 'department_id' => $department->id, 'role' => 'admin', 'is_admin' => true, 'status' => true, 'must_change_password' => false]);
        $this->actingAs($admin);
        $position = Position::where('code', $positionCode)->sole();
        $staff = User::create(['name' => 'Property Employee', 'lastname' => 'Test', 'username' => 'scope-staff', 'email' => 'scope-staff@example.com', 'password' => 'test-password', 'division_id' => $company->id, 'department_id' => $department->id, 'position_id' => $position->id, 'access_role_id' => AccessRole::where('slug', 'standard-user')->sole()->id, 'role' => 'staff', 'status' => true, 'must_change_password' => false]);
        $locations = [];
        foreach (['Cebu Scope', 'Dumaguete Scope'] as $index => $name) {
            $property = HaruhayProperty::create(['division_id' => $company->id, 'name' => $name, 'owner' => 'Owner', 'type' => 'Dormitory', 'location' => $name, 'street_barangay' => 'Test Street', 'city' => 'Test City', 'province' => 'Test Province', 'status' => 'Operational']);
            $unit = HaruhayUnit::create(['property_id' => $property->id, 'floor' => 1, 'unit_type' => 'Solo room', 'capacity' => 3, 'monthly_rent' => 1000, 'rent_basis' => 'per_room', 'room_condition' => 'Ready']);
            $tenant = HaruhayTenant::create(['unit_id' => $unit->id, 'name' => $name.' Resident', 'contact_number' => '09170000000', 'status' => 'Active', 'move_in_date' => today()]);
            $contract = HaruhayLeaseContract::create(['code' => 'SCOPE-CTR-'.$index, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'tenant_name' => $tenant->name, 'property_name' => $name, 'room_no' => $unit->unit_no, 'floor' => 1, 'starts_on' => today(), 'ends_on' => today()->addMonths(6), 'monthly_rent' => 1000, 'billing_starts_on' => today()->addMonth(), 'first_due_on' => today()->addMonth(), 'due_day' => 1, 'billing_cycle' => 'calendar_month', 'first_month_policy' => 'full_month', 'first_month_charge' => 1000]);
            $bill = HaruhayBilling::create(['code' => 'SCOPE-BILL-'.$index, 'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'unit_id' => $unit->id, 'tenant_name' => $tenant->name, 'property_name' => $name, 'room_no' => $unit->unit_no, 'period_start' => today(), 'period_end' => today()->addMonth()->subDay(), 'issued_on' => today(), 'due_on' => today()->addMonth(), 'rent' => 1000, 'total' => 1000, 'finalized_at' => now()]);
            $receipt = HaruhayCollection::create(['code' => 'SCOPE-REC-'.$index, 'submission_token' => str()->uuid(), 'tenant_id' => $tenant->id, 'property_id' => $property->id, 'tenant_name' => $tenant->name, 'property_name' => $name, 'room_no' => $unit->unit_no, 'paid_on' => today(), 'amount' => 100, 'method' => 'Cash']);
            $reservation = HaruhayReservation::create(['reference' => 'RSV-20261126-'.($index + 100), 'submission_token' => str()->uuid(), 'submission_fingerprint' => hash('sha256', (string) $index), 'tracking_token_hash' => hash('sha256', 'secret'.$index), 'unit_id' => $unit->id, 'name' => $name.' Applicant', 'email' => 'guest'.$index.'@example.com', 'phone' => '09170000000', 'occupant_count' => 1, 'other_occupants' => [], 'requested_starts_on' => today()->addWeek(), 'starts_on' => today()->addWeek(), 'ends_on' => today()->addMonths(2), 'duration_months' => 1, 'terms_version' => 'test', 'terms_accepted_at' => now()]);
            $locations[] = compact('property', 'unit', 'tenant', 'contract', 'bill', 'receipt', 'reservation');
        }
        $staff->haruhayProperties()->syncWithoutDetaching([$locations[0]['property']->id => ['is_active' => true, 'assigned_by' => $admin->id]]);

        return [$admin, $staff, $locations];
    }

    public function test_assigned_properties_limit_every_core_model_and_listings(): void
    {
        [, $staff, $locations] = $this->fixtures();
        $this->actingAs($staff);
        foreach ([HaruhayProperty::class, HaruhayUnit::class, HaruhayTenant::class, HaruhayLeaseContract::class, HaruhayBilling::class, HaruhayCollection::class, HaruhayReservation::class] as $model) {
            $this->assertSame(1, $model::count(), $model);
        }
        foreach (['haruhay.units.index', 'haruhay.tenants.index', 'haruhay.billing.index', 'haruhay.collections.index', 'haruhay.reservations.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Cebu Scope')->assertDontSee('Dumaguete Scope');
        }
        $this->get(route('haruhay.reports.index'))->assertOk()->assertViewHas('report', fn ($report) => $report['totalUnits'] === 1 && $report['billingTotal'] === 100000);
    }

    public function test_unassigned_record_urls_and_writes_are_rejected(): void
    {
        [, $staff, $locations] = $this->fixtures();
        $other = $locations[1];
        $this->actingAs($staff);
        foreach (['haruhay.units.show' => $other['unit'], 'haruhay.tenants.show' => $other['tenant'], 'haruhay.contracts.tenant' => $other['tenant'], 'haruhay.billing.show' => $other['bill'], 'haruhay.collections.show' => $other['receipt'], 'haruhay.reservations.show' => $other['reservation'], 'haruhay.deposits.index' => $other['tenant']] as $route => $record) {
            $this->get(route($route, $record))->assertNotFound();
        }
        $this->put(route('haruhay.units.update', $other['unit']), [])->assertNotFound();
        $this->put(route('haruhay.units.update', $locations[0]['unit']), [
            'property_id' => $other['property']->id, 'floor' => 1, 'unit_type' => 'Attempted relocation',
            'capacity' => 3, 'rent_basis' => 'per_room', 'monthly_rent' => 1000, 'room_condition' => 'Ready',
        ])->assertSessionHasErrors('property_id', null, 'unitForm');

        $this->post(route('haruhay.tenants.store'), ['property_id' => $other['property']->id, 'unit_id' => $other['unit']->id, 'name' => 'Forged Guest', 'contact_number' => '09170000000', 'status' => 'Pending Move-in', 'move_in_date' => today()->addWeek()->toDateString()])->assertSessionHasErrors('unit_id', null, 'tenantForm');
        $this->assertDatabaseCount('haruhay_tenants', 2);
    }

    public function test_staff_can_add_units_only_to_assigned_properties(): void
    {
        [, $staff, $locations] = $this->fixtures();
        $this->actingAs($staff);
        $payload = ['property_id' => $locations[0]['property']->id, 'floor' => 1, 'unit_type' => 'Assigned new room', 'capacity' => 1, 'rent_basis' => 'per_room', 'monthly_rent' => 1000, 'room_condition' => 'Ready'];
        $this->post(route('haruhay.units.store'), $payload)->assertRedirect(route('haruhay.units.index'))->assertSessionHasNoErrors();
        $payload['property_id'] = $locations[1]['property']->id;
        $this->post(route('haruhay.units.store'), $payload)->assertNotFound();
        $this->assertDatabaseCount('haruhay_units', 3);
        $created = HaruhayUnit::where('unit_type', 'Assigned new room')->sole();
        $this->put(route('haruhay.units.update', $created), $payload)->assertNotFound();
        $this->assertDatabaseHas('haruhay_units', ['id' => $created->id, 'property_id' => $locations[0]['property']->id]);
    }

    public function test_no_assignments_means_no_property_access_and_reassignment_is_immediate(): void
    {
        [$admin, $staff, $locations] = $this->fixtures();
        $this->actingAs($staff);
        $staff->haruhayProperties()->updateExistingPivot($locations[0]['property']->id, ['is_active' => false]);
        $this->assertSame(0, HaruhayUnit::count());
        $this->get(route('haruhay.units.show', $locations[0]['unit']))->assertNotFound();
        $this->actingAs($admin);
        $staff->haruhayProperties()->syncWithoutDetaching([$locations[1]['property']->id => ['is_active' => true]]);
        $this->actingAs($staff);
        $this->assertSame([$locations[1]['unit']->id], HaruhayUnit::pluck('id')->all());
    }

    public function test_property_manager_and_admin_see_all_locations_without_assignments(): void
    {
        [$admin, $manager] = $this->fixtures('haruhay-property-manager');
        $this->actingAs($manager);
        $this->assertSame(2, HaruhayUnit::count());
        $this->get(route('haruhay.units.index'))->assertOk()->assertSee('Cebu Scope')->assertSee('Dumaguete Scope');
        $this->actingAs($admin);
        $this->assertSame(2, HaruhayUnit::count());
    }

    public function test_cashier_and_finance_positions_are_property_restricted(): void
    {
        [, $staff, $locations] = $this->fixtures('haruhay-cashier');
        foreach (['haruhay-cashier', 'haruhay-finance-reviewer'] as $code) {
            $staff->position_id = Position::where('code', $code)->sole()->id;
            $staff->unsetRelation('position');
            $this->actingAs($staff);
            $this->assertSame(1, HaruhayCollection::count());
            $this->get(route('haruhay.collections.show', $locations[1]['receipt']))->assertNotFound();
        }
    }

    public function test_reports_csv_and_notifications_do_not_leak_other_locations(): void
    {
        [$admin, $staff, $locations] = $this->fixtures();
        foreach ($locations as $location) {
            $staff->notify(new HaruhayReservationRequested($location['reservation']));
        }
        $foreignNotice = $staff->notifications()->where('data->reservation_id', $locations[1]['reservation']->id)->sole();
        $this->actingAs($staff);
        $this->post(route('notifications.read', $foreignNotice->id))->assertNotFound();
        $response = $this->get(route('haruhay.reports.export', ['type' => 'income', 'format' => 'csv']))->assertOk();
        $this->assertStringContainsString('Cebu Scope', $response->streamedContent());
        $this->assertStringNotContainsString('Dumaguete Scope', $response->streamedContent());
        $this->get(route('notifications.index'))->assertOk()->assertSee('Cebu Scope Applicant')->assertDontSee('Dumaguete Scope Applicant');
        $this->getJson(route('haruhay.reservations.live'))->assertOk()->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'items');
        $this->actingAs($admin);
        app(HaruhayReservationAlertService::class)->notify($locations[1]['reservation']);
        $this->assertSame(2, $staff->notifications()->count());
    }

    private function userPayload(User $staff, array $properties): array
    {
        return ['name' => 'Assigned Employee', 'lastname' => 'Test', 'username' => 'created-scope-user', 'email' => 'created-scope@example.com', 'cell_number' => '09170000000', 'division_id' => $staff->division_id, 'department_id' => $staff->department_id, 'position_id' => $staff->position_id, 'access_role_id' => $staff->access_role_id, 'property_assignment_present' => 1, 'property_ids' => $properties];
    }

    public function test_user_create_and_edit_persist_and_revoke_multiple_property_assignments(): void
    {
        [$admin, $staff, $locations] = $this->fixtures();
        $ids = array_map(fn ($location) => $location['property']->id, $locations);
        $this->actingAs($admin)->get(route('users.create'))->assertOk()->assertSee('Haruhay Property / Location Assignments')->assertSee('name="property_ids[]"', false);
        $payload = $this->userPayload($staff, $ids);
        $this->post(route('users.store'), $payload)->assertRedirect('/users')->assertSessionHasNoErrors();
        $created = User::where('username', 'created-scope-user')->sole();
        $this->assertSame(2, $created->haruhayProperties()->wherePivot('is_active', true)->count());
        $payload['status'] = 1;
        $payload['property_ids'] = [$ids[1]];
        $this->post(route('users.update', $created), $payload)->assertRedirect(route('users.edit', $created))->assertSessionHasNoErrors();
        $this->assertSame([$ids[1]], $created->haruhayProperties()->wherePivot('is_active', true)->pluck('haruhay_properties.id')->all());
        $this->get(route('users.edit', $created))->assertOk()->assertSee('Haruhay Property / Location Assignments');
        $payload['property_ids'] = [];
        $this->post(route('users.update', $created), $payload)->assertSessionHasNoErrors();
        $this->assertSame(0, $created->haruhayProperties()->wherePivot('is_active', true)->count());
    }

    public function test_user_assignment_rejects_foreign_company_properties(): void
    {
        [$admin, $staff] = $this->fixtures();
        $foreign = Division::create(['name' => 'Foreign Property Company']);
        $property = HaruhayProperty::create(['division_id' => $foreign->id, 'name' => 'Foreign Building', 'owner' => 'Owner', 'type' => 'Dormitory', 'street_barangay' => 'Street', 'city' => 'City', 'province' => 'Province', 'status' => 'Operational']);
        $this->actingAs($admin)->post(route('users.store'), $this->userPayload($staff, [$property->id]))->assertSessionHasErrors('property_ids');
        $this->assertDatabaseMissing('users', ['username' => 'created-scope-user']);
    }

    public function test_public_website_catalogue_still_contains_all_locations(): void
    {
        $this->fixtures();
        auth()->forgetGuards();
        $this->getJson(route('public.haruhay.rooms'))->assertOk()->assertJsonCount(2, 'data')->assertJsonCount(2, 'meta.properties');
    }

    public function test_directory_queries_stay_bounded_when_loading_ten_tenants(): void
    {
        [, $staff, $locations] = $this->fixtures();
        $this->actingAs($staff);
        $countQueries = function (string $route): int {
            \Illuminate\Support\Facades\DB::flushQueryLog();
            \Illuminate\Support\Facades\DB::enableQueryLog();
            try {
                $this->get(route($route))->assertOk()->assertDontSee('Dumaguete Scope');

                return count(\Illuminate\Support\Facades\DB::getQueryLog());
            } finally {
                \Illuminate\Support\Facades\DB::disableQueryLog();
            }
        };
        $routes = ['haruhay.billing.index', 'haruhay.collections.index'];
        $single = [];
        foreach ($routes as $route) {
            $single[$route] = $countQueries($route);
        }
        for ($index = 1; $index <= 9; $index++) {
            $unit = $locations[0]['unit']->replicate();
            $unit->save();
            $tenant = $locations[0]['tenant']->replicate();
            $tenant->fill(['unit_id' => $unit->id, 'name' => 'Batch Tenant '.$index])->save();
            $contract = $locations[0]['contract']->replicate();
            $contract->fill(['code' => 'BATCH-CTR-'.$index, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'tenant_name' => $tenant->name, 'room_no' => $unit->unit_no])->save();
            $bill = $locations[0]['bill']->replicate();
            $bill->fill(['code' => 'BATCH-INV-'.$index, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'contract_id' => $contract->id, 'tenant_name' => $tenant->name, 'room_no' => $unit->unit_no])->save();
        }
        foreach ($routes as $route) {
            $many = $countQueries($route);
            $this->assertLessThanOrEqual($single[$route] + 8, $many, $route.' must batch tenant billing schedules instead of querying each tenant.');
        }
    }
}
