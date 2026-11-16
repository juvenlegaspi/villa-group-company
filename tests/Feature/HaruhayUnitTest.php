<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayProperty;
use App\Models\HaruhayUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HaruhayUnitTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'admin', string $company = 'Haruhay'): User
    {
        $division = Division::firstOrCreate(['name' => $company]);
        $department = Department::create(['name' => 'Property Management', 'division_id' => $division->id]);
        return User::create(['name' => 'Room Tester', 'lastname' => 'Test', 'username' => 'test-'.str()->uuid(), 'email' => str()->uuid().'@example.com', 'password' => 'test-password-123', 'department_id' => $department->id, 'division_id' => $division->id, 'role' => $role, 'is_admin' => $role === 'admin', 'status' => true, 'must_change_password' => false]);
    }

    private function property(int $divisionId): HaruhayProperty
    {
        return HaruhayProperty::create(['name' => 'Haruhay Space T. Padilla', 'owner' => 'Owner', 'type' => 'Dormitory', 'street_barangay' => 'T. Padilla', 'city' => 'Cebu City', 'province' => 'Cebu', 'status' => 'Under Construction', 'division_id' => $divisionId]);
    }

    private function data(HaruhayProperty $property, array $overrides = []): array
    {
        return array_replace(['property_id' => $property->id, 'unit_no' => 'DR-01', 'floor' => 3, 'unit_type' => '2 single beds, air-conditioned, with CR', 'area' => 12.5, 'capacity' => 2, 'rent_basis' => 'per_room', 'monthly_rent' => 10000, 'status' => 'Vacant', 'room_condition' => 'Under Maintenance', 'remarks' => 'Under construction'], $overrides);
    }

    public function test_create_view_and_edit_room_with_property_connection(): void
    {
        $user = $this->actor();
        $property = $this->property($user->division_id);
        $this->actingAs($user)->get(route('haruhay.units.index'))->assertOk()->assertSee('Add Room')->assertSee('id="unitModal"', false);
        $this->post(route('haruhay.units.store'), $this->data($property, ['unit_no' => ' dr-01 ']))->assertSessionHasNoErrors()->assertRedirect(route('haruhay.units.index'));
        $unit = HaruhayUnit::sole();
        $this->assertSame('DR-01', $unit->unit_no);
        $this->assertSame($property->id, $unit->property->id);
        $this->get(route('haruhay.units.show', $unit))->assertOk()->assertSee('10,000.00')->assertSee('Haruhay Space T. Padilla');
        $this->get(route('haruhay.units.edit', $unit))->assertOk()->assertSee('Edit Room');
        $this->put(route('haruhay.units.update', $unit), $this->data($property, ['status' => 'Occupied', 'occupied_by' => 'Current Tenant', 'move_in_date' => today()->toDateString()]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Vacant', $unit->fresh()->status);
        $this->assertNull($unit->fresh()->occupied_by);
        $this->put(route('haruhay.units.update', $unit), $this->data($property, ['status' => 'Vacant', 'occupied_by' => 'Stale Tenant', 'move_in_date' => today()->toDateString()]))->assertSessionHasNoErrors();
        $this->assertNull($unit->fresh()->occupied_by);
        $this->assertNull($unit->fresh()->move_in_date);
    }

    public function test_room_numbers_are_generated_from_ids_and_cannot_be_changed(): void
    {
        $user = $this->actor(); $property = $this->property($user->division_id);
        $this->actingAs($user)->post(route('haruhay.units.store'), $this->data($property))->assertSessionHasNoErrors();
        $this->post(route('haruhay.units.store'), collect($this->data($property))->except('unit_no')->all())->assertSessionHasNoErrors();
        $this->post(route('haruhay.units.store'), $this->data($property, ['floor' => 4]))->assertSessionHasNoErrors();
        $other = $this->property($user->division_id);
        $this->post(route('haruhay.units.store'), $this->data($other))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('haruhay_units', 4);
        foreach (HaruhayUnit::all() as $unit) {
            $this->assertSame('DR-'.str_pad((string) $unit->id, 2, '0', STR_PAD_LEFT), $unit->unit_no);
        }
        $unit = HaruhayUnit::first();
        $original = $unit->unit_no;
        $this->put(route('haruhay.units.update', $unit), $this->data($property, ['unit_no' => 'FORGED', 'floor' => 9]))->assertSessionHasNoErrors();
        $this->assertSame($original, $unit->fresh()->unit_no);
        $unit->update(['unit_no' => 'MANUAL']);
        $this->assertSame($original, $unit->fresh()->unit_no);
    }

    public function test_rates_support_unknown_per_person_and_shared_occupancy(): void
    {
        $user = $this->actor(); $property = $this->property($user->division_id);
        $this->actingAs($user)->post(route('haruhay.units.store'), $this->data($property, ['monthly_rent' => null]))->assertSessionHasNoErrors();
        $unit = HaruhayUnit::sole();
        $this->get(route('haruhay.units.show', $unit))->assertOk()->assertSee('Rate not set');
        $this->put(route('haruhay.units.update', $unit), $this->data($property, ['rent_basis' => 'occupancy_based', 'monthly_rent' => 5000, 'shared_rate' => 3000]))->assertSessionHasNoErrors();
        $this->get(route('haruhay.units.show', $unit))->assertOk()->assertSee('5,000.00')->assertSee('3,000.00')->assertSee('Single occupancy');
        $this->put(route('haruhay.units.update', $unit), $this->data($property, ['rent_basis' => 'per_person', 'monthly_rent' => 3000, 'shared_rate' => 999]))->assertSessionHasNoErrors();
        $this->assertNull($unit->fresh()->shared_rate);
    }

    public function test_validation_errors_reopen_modal_and_no_invalid_record_is_created(): void
    {
        $user = $this->actor(); $property = $this->property($user->division_id);
        $this->actingAs($user)->from(route('haruhay.units.index'))->post(route('haruhay.units.store'), $this->data($property, ['floor' => -1, 'area' => -1, 'status' => 'Occupied', 'rent_basis' => 'occupancy_based', 'shared_rate' => null]))
            ->assertSessionHasErrors(['floor', 'area', 'shared_rate'], null, 'unitForm');
        $this->get(route('haruhay.units.index'))->assertOk()->assertSee('Please check the following:')->assertSee('window.bootstrap.Modal.getOrCreateInstance', false);
        $this->assertDatabaseCount('haruhay_units', 0);
    }

    public function test_company_scope_and_read_only_access_are_enforced(): void
    {
        $admin = $this->actor(); $property = $this->property($admin->division_id);
        $otherDivision = Division::create(['name' => 'Other']); $foreign = $this->property($otherDivision->id);
        $foreignUnit = HaruhayUnit::create($this->data($foreign));
        $this->actingAs($admin)->post(route('haruhay.units.store'), $this->data($foreign))->assertSessionHasErrors(['property_id'], null, 'unitForm');
        $this->get(route('haruhay.units.show', $foreignUnit))->assertNotFound();
        $this->put(route('haruhay.units.update', $foreignUnit), $this->data($property))->assertNotFound();
        $this->actingAs($this->actor('staff'))->get(route('haruhay.units.index'))->assertOk()->assertDontSee('Add Room');
        $this->post(route('haruhay.units.store'), $this->data($property))->assertForbidden();
        $this->actingAs($this->actor('owner'))->get(route('haruhay.units.index'))->assertOk()->assertDontSee('Add Room');
        $this->post(route('haruhay.units.store'), $this->data($property))->assertForbidden();
        $this->actingAs($this->actor('staff', 'Yatira'))->get(route('haruhay.units.index'))->assertForbidden();
    }

    public function test_property_status_updates_independently_of_room_remarks(): void
    {
        $user = $this->actor(); $property = $this->property($user->division_id);
        $this->actingAs($user)->post(route('haruhay.units.store'), $this->data($property, ['remarks' => 'Manual override']))->assertSessionHasNoErrors();
        $unit = HaruhayUnit::sole();
        $this->assertSame('Under Construction', $unit->property_status);
        $this->assertSame('Manual override', $unit->remarks);
        $property->update(['status' => 'Operational']);
        $this->assertSame('Operational', $unit->fresh()->property_status);
        $this->assertSame('Manual override', $unit->fresh()->remarks);
        $this->get(route('haruhay.units.show', $unit))->assertOk()->assertSee('Operational');
        $this->get(route('haruhay.units.index'))->assertOk()->assertSee('data-status="Operational"', false);
        $other = $this->property($user->division_id);
        $other->update(['status' => 'Idle']);
        $this->put(route('haruhay.units.update', $unit), $this->data($other, ['remarks' => 'Forged']))->assertSessionHasNoErrors();
        $this->assertSame('Idle', $unit->fresh()->property_status);
        $this->assertSame('Forged', $unit->fresh()->remarks);
    }

    public function test_occupied_room_can_be_under_maintenance_and_availability_requires_ready_and_vacant(): void
    {
        $user = $this->actor(); $property = $this->property($user->division_id);
        $this->actingAs($user)->post(route('haruhay.units.store'), $this->data($property, [
            'status' => 'Occupied', 'room_condition' => 'Under Maintenance',
            'occupied_by' => 'Current Tenant', 'move_in_date' => today()->toDateString(),
        ]))->assertSessionHasNoErrors();
        $unit = HaruhayUnit::sole();
        $this->assertSame('Vacant', $unit->status);
        $this->assertNull($unit->occupied_by);
        $unit->update(['status' => 'Occupied', 'occupied_by' => 'Current Tenant', 'move_in_date' => today()->toDateString()]);
        $this->put(route('haruhay.units.update', $unit), $this->data($property, ['status' => 'Vacant', 'occupied_by' => 'Forged']))->assertSessionHasNoErrors();
        $unit->refresh();
        $this->assertSame('Occupied', $unit->status);
        $this->assertSame('Current Tenant', $unit->occupied_by);
        $this->assertSame('Under Maintenance', $unit->room_condition);
        $this->assertFalse($unit->is_available);
        $this->get(route('haruhay.units.show', $unit))->assertOk()->assertSee('Occupied')->assertSee('Under Maintenance')->assertSee('Property Status');
        $unit->update(['status' => 'Vacant', 'room_condition' => 'Under Construction']);
        $this->assertFalse($unit->is_available);
        $unit->update(['room_condition' => 'Ready']);
        $this->assertTrue($unit->is_available);
        $this->get(route('haruhay.units.index', ['room_condition' => 'Ready']))->assertOk()->assertViewHas('units', fn ($units) => $units->total() === 1);
        $this->post(route('haruhay.units.store'), $this->data($property, ['room_condition' => 'Broken']))->assertSessionHasErrors(['room_condition'], null, 'unitForm');
    }

    public function test_legacy_maintenance_records_are_converted_to_vacant_under_maintenance(): void
    {
        $user = $this->actor(); $property = $this->property($user->division_id);
        $migration = require database_path('migrations/2026_11_05_000005_separate_haruhay_room_condition.php');
        $migration->down();
        \Illuminate\Support\Facades\DB::table('haruhay_units')->insert([
            'property_id' => $property->id, 'unit_no' => 'DR-01', 'floor' => 3,
            'unit_type' => 'Single bed', 'status' => 'Maintenance', 'remarks' => 'Repair notes',
        ]);
        $migration->up();
        $unit = HaruhayUnit::sole();
        $this->assertSame('Vacant', $unit->status);
        $this->assertSame('Under Maintenance', $unit->room_condition);
        $this->assertSame('Repair notes', $unit->remarks);
    }

    public function test_formatted_rent_amounts_are_saved_as_numbers(): void
    {
        $user = $this->actor(); $property = $this->property($user->division_id);
        $this->actingAs($user)->post(route('haruhay.units.store'), $this->data($property, [
            'rent_basis' => 'occupancy_based', 'monthly_rent' => '10,000.50', 'shared_rate' => '1,000',
        ]))->assertSessionHasNoErrors();
        $unit = HaruhayUnit::sole();
        $this->assertSame('10000.50', $unit->monthly_rent);
        $this->assertSame('1000.00', $unit->shared_rate);
        $this->put(route('haruhay.units.update', $unit), $this->data($property, ['monthly_rent' => '1,00,0']))->assertSessionHasErrors(['monthly_rent'], null, 'unitForm');
    }

    public function test_dashboard_and_filters_count_actual_rooms(): void
    {
        $user = $this->actor(); $property = $this->property($user->division_id);
        HaruhayUnit::create($this->data($property));
        HaruhayUnit::create($this->data($property, ['unit_no' => 'DR-02', 'floor' => 4, 'status' => 'Vacant']));
        $this->actingAs($user)->get(route('haruhay.units.index', ['property_id' => $property->id, 'floor' => 4, 'status' => 'Vacant', 'search' => 'DR-02']))
            ->assertOk()->assertViewHas('units', fn ($units) => $units->total() === 1 && $units->first()->unit_no === 'DR-02')
            ->assertViewHas('stats', fn ($stats) => $stats['Total Units'] === 2 && $stats['Under Maintenance'] === 2 && $stats['Vacant'] === 2 && $stats['Available'] === 0);
        $this->get(route('haruhay.database', ['section' => 'units']))->assertRedirect(route('haruhay.units.index'));
    }
}
