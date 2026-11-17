<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayProperty;
use App\Models\HaruhayUnit;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VillaGroupWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'staff', string $company = 'Villa Group', ?string $positionCode = null, string $departmentName = 'Information Technology'): User
    {
        $division = Division::firstOrCreate(['name' => $company]);
        $department = Department::firstOrCreate(['name' => $departmentName, 'division_id' => $division->id]);
        $position = $positionCode ? Position::firstOrCreate(['division_id' => $division->id, 'department_id' => $department->id, 'code' => $positionCode], ['name' => 'Property Specialist', 'legacy_role' => 'staff', 'is_active' => true]) : null;
        return User::create(['name' => 'Workspace Tester', 'lastname' => 'Test', 'username' => 'test-'.str()->uuid(), 'email' => str()->uuid().'@example.com', 'password' => 'test-password-123', 'department_id' => $department->id, 'position_id' => $position?->id, 'division_id' => $division->id, 'role' => $role, 'is_admin' => $role === 'admin', 'status' => true, 'must_change_password' => false]);
    }

    public function test_corporate_property_can_be_connected_to_haruhay_from_create_or_update(): void
    {
        $haruhay = Division::firstOrCreate(['name' => 'Haruhay']);
        $admin = $this->actor('admin');
        $this->actingAs($admin)->post(route('villa-group.properties.store'), $this->data())->assertSessionHasNoErrors();
        $property = HaruhayProperty::sole();
        $this->assertSame($admin->division_id, $property->division_id);
        $this->get(route('haruhay.units.index'))->assertOk()->assertViewHas('properties', fn ($properties) => ! $properties->contains('id', $property->id));
        $this->put(route('villa-group.properties.update', $property), $this->data() + ['use_in_haruhay' => 1])->assertSessionHasNoErrors();
        $this->assertSame($haruhay->id, $property->fresh()->division_id);
        $this->get(route('haruhay.units.index'))->assertOk()->assertViewHas('properties', fn ($properties) => $properties->contains('id', $property->id));
        $this->get(route('villa-group.properties.index'))->assertOk()->assertSee($property->code);
        $this->post(route('villa-group.properties.store'), array_replace($this->data(), ['name' => 'New Haruhay Property', 'use_in_haruhay' => 1]))->assertSessionHasNoErrors();
        $this->assertSame($haruhay->id, HaruhayProperty::where('name', 'New Haruhay Property')->sole()->division_id);
    }

    public function test_haruhay_connection_cannot_be_removed_when_property_has_rooms(): void
    {
        $haruhay = Division::firstOrCreate(['name' => 'Haruhay']);
        $admin = $this->actor('admin');
        $property = HaruhayProperty::create($this->data() + ['division_id' => $haruhay->id, 'code' => 'PROP-0001']);
        HaruhayUnit::create(['property_id' => $property->id, 'floor' => 1, 'unit_type' => 'Single bed']);
        $this->actingAs($admin)->put(route('villa-group.properties.update', $property), $this->data() + ['use_in_haruhay' => 0])->assertSessionHasErrors('use_in_haruhay');
        $this->assertSame($haruhay->id, $property->fresh()->division_id);
    }

    private function data(): array
    {
        return ['name' => 'Haruhay Space', 'owner' => 'Registered Owner', 'type' => 'Dormitory', 'street_barangay' => 'T. Padilla', 'city' => 'Cebu City', 'province' => 'Cebu', 'status' => 'Under Construction'];
    }

    public function test_admin_has_three_cards_and_it_user_management_navigation(): void
    {
        $this->actingAs($this->actor('admin'))->get(route('companies'))->assertOk()->assertSee(route('villa-group.applications'), false);
        $this->get(route('dashboard'))->assertOk()->assertSee(route('villa-group.applications'), false);
        $this->get(route('villa-group.applications'))->assertOk()->assertViewHas('canAccessIt', true)->assertViewHas('canAccessHr', true)->assertViewHas('canAccessProperties', true);
        $this->get(route('villa-group.it'))->assertOk()->assertSee('User Management')->assertSee(route('users.index'), false);
        $this->get(route('users.index'))->assertOk()->assertSee('title="User Management"', false);
        $this->get(route('villa-group.hr'))->assertOk();
        $this->get(route('villa-group.properties.index'))->assertOk();
    }

    public function test_property_specialist_has_properties_without_it_hr_or_other_company_access(): void
    {
        $specialist = $this->actor('staff', 'Villa Group', 'property-specialist', 'Property Management');
        $this->actingAs($specialist)->get(route('villa-group.applications'))->assertOk()->assertViewHas('canAccessIt', false)->assertViewHas('canAccessHr', false)->assertViewHas('canAccessProperties', true);
        $this->get(route('villa-group.it'))->assertForbidden();
        $this->get(route('villa-group.hr'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('haruhay.units.index'))->assertForbidden();
        $this->get(route('villa-group.properties.index'))->assertOk()->assertSee('Add Property');
        $this->post(route('villa-group.properties.store'), $this->data())->assertSessionHasNoErrors()->assertRedirect(route('villa-group.properties.index'));
        $property = HaruhayProperty::sole();
        $this->assertSame($specialist->division_id, $property->division_id);
        $this->get(route('villa-group.properties.show', $property))->assertOk()->assertSee('data-bs-target="#updatePropertyModal"', false);
        $this->get(route('villa-group.properties.edit', $property))->assertOk();
    }

    public function test_executive_properties_are_read_only_and_hr_staff_are_separate(): void
    {
        $owner = $this->actor('owner');
        $this->actingAs($owner)->get(route('villa-group.applications'))->assertOk()->assertViewHas('canAccessProperties', true)->assertViewHas('canAccessIt', false)->assertViewHas('canAccessHr', false);
        $this->get(route('villa-group.properties.index'))->assertOk()->assertDontSee('Add Property');
        $this->get(route('villa-group.properties.create'))->assertForbidden();
        $this->post(route('villa-group.properties.store'), $this->data())->assertForbidden();
        $this->get(route('villa-group.it'))->assertForbidden();
        $this->actingAs($this->actor('hr', 'Villa Group', null, 'Human Resources'))->get(route('villa-group.applications'))->assertOk()->assertViewHas('canAccessHr', true)->assertViewHas('canAccessIt', false)->assertViewHas('canAccessProperties', false);
        $this->get(route('villa-group.hr'))->assertOk();
        $this->get(route('villa-group.properties.index'))->assertForbidden();
        $this->actingAs($this->actor())->get(route('villa-group.properties.index'))->assertForbidden();
        $this->actingAs($this->actor('staff', 'Yatira', 'property-specialist'))->get(route('villa-group.applications'))->assertForbidden();
        $this->get(route('villa-group.properties.index'))->assertForbidden();
    }

    public function test_shared_properties_preserve_haruhay_links_and_protect_downloads(): void
    {
        Storage::fake('local');
        $admin = $this->actor('admin');
        $haruhay = Division::firstOrCreate(['name' => 'Haruhay']);
        $property = HaruhayProperty::create($this->data() + ['division_id' => $haruhay->id, 'code' => 'PROP-0001']);
        $unit = HaruhayUnit::create(['property_id' => $property->id, 'floor' => 3, 'unit_type' => 'Single bed']);
        $this->actingAs($admin)->get(route('villa-group.properties.index'))->assertOk()->assertSee('Haruhay Space');
        $this->get(route('haruhay.units.index'))->assertOk()->assertDontSee('title="Properties"', false)->assertSee('Haruhay Space');
        $this->get('/haruhay/properties')->assertNotFound();
        $this->post('/haruhay/properties', $this->data())->assertNotFound();
        $this->put(route('villa-group.properties.update', $property), array_replace($this->data(), ['status' => 'Operational', 'division_id' => $admin->division_id, 'attachments' => [UploadedFile::fake()->create('property.pdf', 10, 'application/pdf')]]))->assertSessionHasNoErrors()->assertRedirect(route('villa-group.properties.show', $property));
        $this->assertSame($haruhay->id, $property->fresh()->division_id);
        $this->assertSame($property->id, $unit->fresh()->property_id);
        $this->assertSame('Operational', $unit->fresh()->property_status);
        $this->assertDatabaseCount('haruhay_properties', 1);
        $attachment = $property->attachments()->sole();
        $this->actingAs($this->actor('owner'))->get(route('villa-group.properties.attachment', [$property, $attachment]))->assertDownload('property.pdf');
        $this->put(route('villa-group.properties.update', $property), $this->data())->assertForbidden();
        $this->actingAs($this->actor())->get(route('villa-group.properties.attachment', [$property, $attachment]))->assertForbidden();
    }
}
