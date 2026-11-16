<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HaruhayTenantTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'admin', string $company = 'Haruhay'): User
    {
        $division = Division::firstOrCreate(['name' => $company]);
        $department = Department::create(['name' => 'Property Management', 'division_id' => $division->id]);
        return User::create(['name' => 'Tenant Tester', 'lastname' => 'Test', 'username' => 'test-'.str()->uuid(), 'email' => str()->uuid().'@example.com', 'password' => 'test-password-123', 'department_id' => $department->id, 'division_id' => $division->id, 'role' => $role, 'is_admin' => $role === 'admin', 'status' => true, 'must_change_password' => false]);
    }

    private function room(User $user, array $overrides = []): HaruhayUnit
    {
        $property = HaruhayProperty::create(['name' => 'Haruhay Space', 'owner' => 'Owner', 'type' => 'Dormitory', 'street_barangay' => 'T. Padilla', 'city' => 'Cebu City', 'province' => 'Cebu', 'status' => 'Operational', 'division_id' => $user->division_id]);
        return HaruhayUnit::create(array_replace(['property_id' => $property->id, 'floor' => 3, 'unit_type' => '2 single beds', 'capacity' => 2, 'room_condition' => 'Ready', 'status' => 'Vacant'], $overrides));
    }

    private function data(HaruhayUnit $room, array $overrides = []): array
    {
        return array_replace(['property_id' => $room->property_id, 'unit_id' => $room->id, 'name' => 'First Tenant', 'contact_number' => '09171234567', 'email' => 'tenant@example.com', 'move_in_date' => today()->subDays(10)->toDateString(), 'status' => 'Active'], $overrides);
    }

    private function add(HaruhayUnit $room, array $overrides = []): HaruhayTenant
    {
        $this->post(route('haruhay.tenants.store'), $this->data($room, $overrides))->assertSessionHasNoErrors()->assertRedirect(route('haruhay.tenants.index'));
        return HaruhayTenant::latest('id')->firstOrFail();
    }

    public function test_add_view_edit_and_contract_placeholder(): void
    {
        $user = $this->actor(); $room = $this->room($user); $this->actingAs($user);
        $this->get(route('haruhay.tenants.index'))->assertOk()->assertSee('id="tenantModal"', false);
        $tenant = $this->add($room, ['code' => 'FORGED', 'emergency_contact_name' => 'Parent', 'emergency_contact_number' => '09170000000']);
        $this->assertSame('TEN-0001', $tenant->code);
        $this->assertSame('Occupied', $room->fresh()->status);
        $this->assertSame('First Tenant', $room->fresh()->occupied_by);
        $this->assertSame('Ready', $room->fresh()->room_condition);
        $this->get(route('haruhay.tenants.index'))->assertOk()
            ->assertSee('href="'.route('haruhay.tenants.show', $tenant).'"', false)
            ->assertDontSee('>Actions</th>', false);
        $this->get(route('haruhay.tenants.show', $tenant))->assertOk()->assertSee('No Contract Yet')->assertSee('Parent')
            ->assertDontSee("@include('haruhay.contracts.summary')", false)
            ->assertSee('Update Tenant')->assertSee('data-bs-target="#tenantModal"', false)
            ->assertDontSee('window.bootstrap.Modal.getOrCreateInstance', false);
        $this->get(route('haruhay.tenants.edit', $tenant))->assertOk()->assertSee('Edit Tenant');
        $room->update(['room_condition' => 'Under Maintenance']);
        $this->put(route('haruhay.tenants.update', $tenant), $this->data($room, ['name' => 'Updated Tenant', 'code' => 'ALTERED', 'status' => 'On Notice']))->assertSessionHasNoErrors()->assertRedirect(route('haruhay.tenants.show', $tenant));
        $this->assertSame('TEN-0001', $tenant->fresh()->code);
        $this->assertSame('Updated Tenant', $room->fresh()->occupied_by);
        $this->assertSame('Under Maintenance', $room->fresh()->room_condition);
        $this->assertSame(2, $tenant->audits()->count());
        $this->get(route('haruhay.tenants.index', ['status' => 'On Notice', 'search' => 'Updated']))->assertOk()->assertViewHas('tenants', fn ($rows) => $rows->total() === 1)->assertSee('No Contract Yet');
    }

    public function test_multiple_tenants_capacity_and_last_move_out_synchronize_occupancy(): void
    {
        $user = $this->actor(); $room = $this->room($user); $this->actingAs($user);
        $first = $this->add($room);
        $second = $this->add($room, ['name' => 'Second Tenant']);
        $this->post(route('haruhay.tenants.store'), $this->data($room, ['name' => 'Third Tenant']))->assertSessionHasErrors(['unit_id'], null, 'tenantForm');
        $this->assertDatabaseCount('haruhay_tenants', 2);
        $this->put(route('haruhay.tenants.update', $first), $this->data($room, ['status' => 'Moved Out', 'move_out_date' => today()->toDateString()]))->assertSessionHasNoErrors();
        $this->assertSame('Occupied', $room->fresh()->status);
        $this->assertSame('Second Tenant', $room->fresh()->occupied_by);
        $room->update(['room_condition' => 'Under Maintenance']);
        $this->put(route('haruhay.tenants.update', $second), $this->data($room, ['name' => 'Second Tenant', 'status' => 'Moved Out', 'move_out_date' => today()->toDateString()]))->assertSessionHasNoErrors();
        $this->assertSame('Vacant', $room->fresh()->status);
        $this->assertNull($room->fresh()->occupied_by);
        $this->assertNull($room->fresh()->move_in_date);
        $this->assertSame('Under Maintenance', $room->fresh()->room_condition);
        $this->get(route('haruhay.tenants.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['Moved Out'] === 2 && $stats['Active'] === 0);
    }

    public function test_transfer_updates_both_rooms_and_records_history(): void
    {
        $user = $this->actor(); $old = $this->room($user); $new = $this->room($user); $this->actingAs($user);
        $tenant = $this->add($old);
        $this->put(route('haruhay.tenants.update', $tenant), $this->data($new))->assertSessionHasNoErrors();
        $this->assertSame('Vacant', $old->fresh()->status);
        $this->assertSame('Occupied', $new->fresh()->status);
        $audit = $tenant->audits()->latest('id')->first();
        $this->assertSame($old->id, $audit->before['unit_id']);
        $this->assertSame($new->id, $audit->after['unit_id']);
    }

    public function test_new_assignments_require_ready_rooms_and_valid_property_room_pair(): void
    {
        $user = $this->actor(); $room = $this->room($user); $other = $this->room($user); $this->actingAs($user);
        foreach (['Under Maintenance', 'Under Construction', 'Out of Service'] as $condition) {
            $room->update(['room_condition' => $condition]);
            $this->post(route('haruhay.tenants.store'), $this->data($room))->assertSessionHasErrors(['unit_id'], null, 'tenantForm');
        }
        $room->update(['room_condition' => 'Ready', 'status' => 'Reserved']);
        $this->post(route('haruhay.tenants.store'), $this->data($room))->assertSessionHasErrors(['unit_id'], null, 'tenantForm');
        $this->post(route('haruhay.tenants.store'), $this->data($room, ['property_id' => $other->property_id]))->assertSessionHasErrors(['unit_id'], null, 'tenantForm');
        $this->assertDatabaseCount('haruhay_tenants', 0);
    }

    public function test_move_out_date_validation_and_failed_transfer_leave_records_unchanged(): void
    {
        $user = $this->actor(); $room = $this->room($user); $blocked = $this->room($user, ['room_condition' => 'Under Maintenance']); $this->actingAs($user);
        $tenant = $this->add($room);
        $this->put(route('haruhay.tenants.update', $tenant), $this->data($room, ['status' => 'Moved Out', 'move_out_date' => today()->subDays(20)->toDateString()]))->assertSessionHasErrors(['move_out_date'], null, 'tenantForm');
        $this->put(route('haruhay.tenants.update', $tenant), $this->data($blocked))->assertSessionHasErrors(['unit_id'], null, 'tenantForm');
        $this->assertSame($room->id, $tenant->fresh()->unit_id);
        $this->assertSame('Occupied', $room->fresh()->status);
        $this->assertSame('Vacant', $blocked->fresh()->status);
        $this->assertSame(1, $tenant->audits()->count());
    }

    public function test_invalid_fields_reopen_modal_and_room_capacity_edits_protect_current_tenants(): void
    {
        $user = $this->actor(); $room = $this->room($user); $this->actingAs($user);
        $this->from(route('haruhay.tenants.index'))->post(route('haruhay.tenants.store'), $this->data($room, [
            'name' => '', 'email' => 'invalid', 'move_in_date' => today()->addDay()->toDateString(),
            'emergency_contact_name' => 'Parent',
        ]))->assertSessionHasErrors(['name', 'email', 'move_in_date', 'emergency_contact_number'], null, 'tenantForm');
        $this->get(route('haruhay.tenants.index'))->assertOk()->assertSee('Please check the following:')->assertSee('window.bootstrap.Modal.getOrCreateInstance', false);
        $this->assertDatabaseCount('haruhay_tenants', 0);
        $this->add($room); $this->add($room, ['name' => 'Second Tenant']);
        $roomData = ['property_id' => $room->property_id, 'floor' => 3, 'unit_type' => 'Single bed', 'capacity' => 1, 'rent_basis' => 'per_room', 'room_condition' => 'Ready'];
        $this->put(route('haruhay.units.update', $room), $roomData)->assertSessionHasErrors(['capacity'], null, 'unitForm');
        $this->assertSame(2, $room->fresh()->capacity);
        $other = $this->room($user);
        $this->put(route('haruhay.units.update', $room), array_replace($roomData, ['property_id' => $other->property_id, 'capacity' => 2]))->assertSessionHasErrors(['property_id'], null, 'unitForm');
    }

    public function test_future_reservations_hold_slots_and_can_confirm_early(): void
    {
        $user = $this->actor(); $room = $this->room($user, ['capacity' => 1]); $this->actingAs($user);
        $scheduled = today()->addDays(5)->toDateString();
        $tenant = $this->add($room, ['status' => 'Pending Move-in', 'move_in_date' => $scheduled]);
        $this->assertNull($tenant->move_in_date);
        $this->assertSame($scheduled, $tenant->scheduled_move_in_date->toDateString());
        $this->assertSame('Reserved', $room->fresh()->status);
        $this->assertSame(0, $room->fresh()->available_slots);
        $this->post(route('haruhay.tenants.store'), $this->data($room))->assertSessionHasErrors(['unit_id'], null, 'tenantForm');
        $this->put(route('haruhay.tenants.update', $tenant), $this->data($room))->assertSessionHasErrors(['status'], null, 'tenantForm');
        $this->assertSame('Pending Move-in', $tenant->fresh()->status);
        $response = $this->get(route('haruhay.tenants.show', $tenant))->assertOk()->assertSee('Reserved for Scheduled Move-in');
        preg_match('/<button[^>]*>Confirm Move-in<\/button>/', $response->getContent(), $button);
        $this->assertNotEmpty($button);
        $this->assertStringNotContainsString('disabled', $button[0]);
        $this->post(route('haruhay.tenants.confirm', $tenant))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Active', $tenant->fresh()->status);
        $this->assertSame(today()->toDateString(), $tenant->fresh()->move_in_date->toDateString());
        $this->assertSame($scheduled, $tenant->fresh()->scheduled_move_in_date->toDateString());
        $this->assertSame('Occupied', $room->fresh()->status);
        $this->post(route('haruhay.tenants.confirm', $tenant))->assertSessionHasErrors(['status'], null, 'tenantForm');
        $this->post(route('haruhay.tenants.cancel', $tenant))->assertSessionHasErrors(['status'], null, 'tenantForm');
    }

    public function test_shared_room_tracks_occupied_reserved_and_available_slots_and_cancellation(): void
    {
        $user = $this->actor(); $room = $this->room($user); $this->actingAs($user);
        $this->add($room);
        $reservation = $this->add($room, ['name' => 'Scheduled Tenant', 'status' => 'Pending Move-in', 'move_in_date' => today()->addWeek()->toDateString()]);
        $this->assertSame('Occupied', $room->fresh()->status);
        $this->assertSame('First Tenant', $room->fresh()->occupied_by);
        $this->assertSame(0, $room->fresh()->available_slots);
        $this->get(route('haruhay.units.index'))->assertOk()->assertSee('1 occupied')->assertSee('1 reserved')->assertSee('0 available');
        $this->post(route('haruhay.tenants.store'), $this->data($room, ['status' => 'Pending Move-in', 'move_in_date' => today()->addWeeks(2)->toDateString()]))->assertSessionHasErrors(['unit_id'], null, 'tenantForm');
        $this->post(route('haruhay.tenants.cancel', $reservation))->assertSessionHasNoErrors();
        $this->assertSame('Cancelled', $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->move_in_date);
        $this->assertSame('Occupied', $room->fresh()->status);
        $this->assertSame(1, $room->fresh()->available_slots);
        $this->post(route('haruhay.tenants.cancel', $reservation))->assertSessionHasErrors(['status'], null, 'tenantForm');
    }

    public function test_cancel_last_reservation_and_confirm_rechecks_room_condition(): void
    {
        $user = $this->actor(); $room = $this->room($user); $this->actingAs($user);
        $reservation = $this->add($room, ['status' => 'Pending Move-in', 'move_in_date' => today()->subDay()->toDateString()]);
        $room->update(['room_condition' => 'Under Maintenance']);
        $this->post(route('haruhay.tenants.confirm', $reservation))->assertSessionHasErrors(['unit_id'], null, 'tenantForm');
        $this->assertSame('Pending Move-in', $reservation->fresh()->status);
        $this->get(route('haruhay.tenants.show', $reservation))->assertOk()->assertSee('Overdue Move-in');
        $this->post(route('haruhay.tenants.cancel', $reservation))->assertSessionHasNoErrors();
        $this->assertSame('Vacant', $room->fresh()->status);
        $this->assertSame('Under Maintenance', $room->fresh()->room_condition);
    }

    public function test_reservation_transfer_and_capacity_edits_preserve_holds(): void
    {
        $user = $this->actor(); $first = $this->room($user); $second = $this->room($user, ['capacity' => null]); $this->actingAs($user);
        $reservation = $this->add($first, ['status' => 'Pending Move-in', 'move_in_date' => today()->addDay()->toDateString()]);
        $this->put(route('haruhay.tenants.update', $reservation), $this->data($second, ['status' => 'Pending Move-in', 'move_in_date' => today()->addDays(2)->toDateString()]))->assertSessionHasNoErrors();
        $this->assertSame('Vacant', $first->fresh()->status);
        $this->assertSame('Reserved', $second->fresh()->status);
        $this->get(route('haruhay.tenants.edit', $reservation))->assertOk()->assertSee(today()->addDays(2)->toDateString());
        $this->post(route('haruhay.tenants.store'), $this->data($second))->assertSessionHasErrors(['unit_id'], null, 'tenantForm');
        $this->actingAs($this->actor('owner'))->post(route('haruhay.tenants.confirm', $reservation))->assertForbidden();
        $this->post(route('haruhay.tenants.cancel', $reservation))->assertForbidden();
    }

    public function test_room_dropdown_hides_full_and_unready_rooms_but_keeps_current_room_on_edit(): void
    {
        $user = $this->actor(); $this->actingAs($user);
        $available = $this->room($user);
        $shared = $this->room($user);
        $this->add($shared);
        $full = $this->room($user, ['capacity' => 1]);
        $reserved = $this->add($full, ['status' => 'Pending Move-in', 'move_in_date' => today()->addDay()->toDateString()]);
        $blocked = [];
        foreach (['Under Maintenance', 'Under Construction', 'Out of Service'] as $condition) {
            $blocked[] = $this->room($user, ['room_condition' => $condition]);
        }
        $response = $this->get(route('haruhay.tenants.index'))->assertOk();
        preg_match('/<select id="tenantRoom".*?<\/select>/s', $response->getContent(), $matches);
        $options = $matches[0];
        $this->assertStringContainsString('value="'.$available->id.'"', $options);
        $this->assertStringContainsString('value="'.$shared->id.'"', $options);
        $this->assertStringNotContainsString('value="'.$full->id.'"', $options);
        foreach ($blocked as $room) $this->assertStringNotContainsString('value="'.$room->id.'"', $options);
        $response = $this->get(route('haruhay.tenants.edit', $reserved))->assertOk();
        preg_match('/<select id="tenantRoom".*?<\/select>/s', $response->getContent(), $matches);
        $this->assertStringContainsString('value="'.$full->id.'"', $matches[0]);
    }

    public function test_company_scope_and_read_only_users(): void
    {
        $admin = $this->actor(); $room = $this->room($admin); $this->actingAs($admin); $tenant = $this->add($room);
        $other = $this->actor('staff', 'Yatira'); $foreignRoom = $this->room($other);
        $this->post(route('haruhay.tenants.store'), $this->data($foreignRoom))->assertSessionHasErrors(['property_id'], null, 'tenantForm');
        $this->actingAs($other)->get(route('haruhay.tenants.index'))->assertForbidden();
        $this->get(route('haruhay.tenants.show', $tenant))->assertForbidden();
        foreach (['staff', 'owner'] as $role) {
            $this->actingAs($this->actor($role))->get(route('haruhay.tenants.index'))->assertOk()->assertDontSee('Add Tenant');
            $this->post(route('haruhay.tenants.store'), $this->data($room))->assertForbidden();
            $this->put(route('haruhay.tenants.update', $tenant), $this->data($room))->assertForbidden();
        }
        $this->actingAs($admin)->get(route('haruhay.database', ['section' => 'tenants']))->assertRedirect(route('haruhay.tenants.index'));
    }
}
