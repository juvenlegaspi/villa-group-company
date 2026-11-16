<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\HaruhayProperty;
use App\Models\HaruhayPropertyAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HaruhayPropertyTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'admin', string $company = 'Villa Group'): User
    {
        $division = Division::firstOrCreate(['name' => $company]);
        $department = \App\Models\Department::create(['name' => 'Property Management', 'division_id' => $division->id]);
        return User::create(['name' => 'Property Tester', 'lastname' => 'Test', 'username' => 'test-'.str()->uuid(), 'email' => str()->uuid().'@example.com', 'password' => 'test-password-123', 'department_id' => $department->id, 'division_id' => $division->id, 'role' => $role, 'is_admin' => $role === 'admin', 'status' => true, 'must_change_password' => false]);
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Haruhay Space T. Padilla', 'owner' => 'Registered Owner',
            'type' => 'Dormitory', 'street_barangay' => 'T. Padilla', 'city' => 'Cebu City',
            'province' => 'Cebu', 'status' => 'Under Construction', 'floors' => 4,
        ], $overrides);
    }

    public function test_attachment_limit_prevents_partial_property_update(): void
    {
        Storage::fake('local');
        $user = $this->actor(); $this->actingAs($user);
        $this->post(route('villa-group.properties.store'), $this->data())->assertSessionHasNoErrors();
        $property = HaruhayProperty::sole();
        for ($i = 0; $i < 10; $i++) $property->attachments()->create(['path' => 'existing-'.$i.'.pdf', 'original_name' => 'existing.pdf', 'size' => 1]);
        $this->put(route('villa-group.properties.update', $property), $this->data(['name' => 'Should Not Save', 'attachments' => [UploadedFile::fake()->create('extra.pdf', 1, 'application/pdf')]]))->assertSessionHasErrors('attachments');
        $this->assertSame(10, $property->attachments()->count());
        $this->assertSame('Haruhay Space T. Padilla', $property->fresh()->name);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_create_edit_view_and_private_attachment_download(): void
    {
        Storage::fake('local');
        $user = $this->actor();
        $this->actingAs($user)->get(route('villa-group.properties.create'))->assertOk();
        $this->post(route('villa-group.properties.store'), $this->data([
            'attachments' => [UploadedFile::fake()->create('property.pdf', 12, 'application/pdf')],
            'code' => 'FORGED', 'division_id' => 999,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('villa-group.properties.index'));
        $property = HaruhayProperty::sole();
        $this->assertSame('PROP-0001', $property->code);
        $this->assertSame($user->division_id, $property->division_id);
        $attachment = $property->attachments()->sole();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get(route('villa-group.properties.show', $property))->assertOk()->assertSee('Registered Owner')->assertSee('property.pdf');
        $this->get(route('villa-group.properties.attachment', [$property, $attachment]))->assertDownload('property.pdf');
        $this->get(route('villa-group.properties.edit', $property))->assertOk();
        $this->put(route('villa-group.properties.update', $property), $this->data(['status' => 'Operational']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Operational', $property->fresh()->status);
        $this->assertSame(1, $property->attachments()->count());
    }

    public function test_listing_filters_stats_and_sidebar(): void
    {
        $user = $this->actor();
        HaruhayProperty::create($this->data() + ['division_id' => $user->division_id, 'code' => 'PROP-0001']);
        HaruhayProperty::create($this->data(['name' => 'Warehouse North', 'type' => 'Warehouse', 'status' => 'Operational']) + ['division_id' => $user->division_id, 'code' => 'PROP-0002']);
        $this->actingAs($user)->get(route('villa-group.properties.index', ['search' => 'Warehouse', 'type' => 'Warehouse', 'status' => 'Operational']))
            ->assertOk()->assertSee('Warehouse North')
            ->assertSee('Property Directory')->assertViewHas('stats', fn ($stats) => $stats['total'] === 2 && $stats['operational'] === 1)
            ->assertViewHas('properties', fn ($properties) => $properties->total() === 1 && $properties->first()->name === 'Warehouse North');
        $this->get('/haruhay/properties')->assertNotFound();
    }

    public function test_dashboard_and_property_rows_use_connected_room_counts(): void
    {
        $user = $this->actor();
        $property = HaruhayProperty::create($this->data() + ['division_id' => $user->division_id]);
        HaruhayProperty::create($this->data(['name' => 'Empty Property', 'type' => 'Residential', 'status' => 'Active']) + ['division_id' => $user->division_id]);
        foreach (['Occupied', 'Occupied', 'Vacant', 'Reserved'] as $status) {
            \App\Models\HaruhayUnit::create(['property_id' => $property->id, 'floor' => 3, 'unit_type' => 'Shared room', 'capacity' => 4, 'status' => $status]);
        }
        $this->actingAs($user)->get(route('villa-group.properties.index'))->assertOk()
            ->assertSee('Property Manager')->assertSee('50.00%')
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 2 && $stats['total_units'] === 4 && $stats['occupied_units'] === 2 && $stats['vacant_units'] === 1 && $stats['occupancy_rate'] === 50.0)
            ->assertViewHas('properties', fn ($rows) => $rows->firstWhere('id', $property->id)->units_count === 4 && $rows->firstWhere('id', $property->id)->occupied_units_count === 2);
        $this->get(route('villa-group.properties.index', ['search' => 'Empty']))->assertOk()->assertSee('0.00%')->assertViewHas('properties', fn ($rows) => $rows->total() === 1 && $rows->first()->units_count === 0);
    }

    public function test_company_isolation_and_read_only_users(): void
    {
        $user = $this->actor('staff');
        $this->actingAs($user)->get(route('villa-group.properties.index'))->assertForbidden();
        $this->post(route('villa-group.properties.store'), $this->data())->assertForbidden();
        $this->actingAs($this->actor('owner'))->get(route('villa-group.properties.index'))->assertOk()->assertDontSee('Add Property');
        $this->post(route('villa-group.properties.store'), $this->data())->assertForbidden();
        $this->actingAs($this->actor('staff', 'Yatira'))->get(route('villa-group.properties.index'))->assertForbidden();
    }

    public function test_validation_rejects_invalid_data_without_saving(): void
    {
        Storage::fake('local');
        $this->actingAs($this->actor())->post(route('villa-group.properties.store'), $this->data([
            'location' => '', 'lot_area' => -1, 'floors' => 0, 'status' => 'Invalid',
            'attachments' => [UploadedFile::fake()->create('script.php', 1, 'text/plain')],
        ]))->assertSessionHasErrors(['location', 'lot_area', 'floors', 'status', 'attachments.0'], null, 'propertyCreate');
        $this->get(route('villa-group.properties.index'))->assertOk()
            ->assertSee('id="addPropertyModal"', false)
            ->assertSee('Please reselect any attachments')
            ->assertSee('window.bootstrap.Modal.getOrCreateInstance', false);
        $this->assertDatabaseCount('haruhay_properties', 0);
        $this->assertDatabaseCount('haruhay_property_attachments', 0);
    }

    public function test_simple_property_form_and_updates_preserve_legacy_details_and_room_links(): void
    {
        $user = $this->actor(); $this->actingAs($user);
        $data = ['name' => 'Simple Property', 'location' => 'T. Padilla, Cebu City, Cebu', 'type' => 'Residential', 'person_in_charge' => 'Property Manager', 'status' => 'Active'];
        $this->get(route('villa-group.properties.index'))->assertOk()->assertSee('name="location"', false)->assertSee('name="owner"', false)->assertSee('name="floors"', false)->assertSee('name="lot_area"', false)->assertSee('name="floor_area"', false)->assertSee('name="remarks"', false)->assertDontSee('name="attachments[]"', false);
        $this->post(route('villa-group.properties.store'), $data)->assertSessionHasNoErrors();
        $property = HaruhayProperty::sole();
        $this->assertSame('T. Padilla, Cebu City, Cebu', $property->location);
        $property->update(['owner' => 'Original Owner', 'lot_area' => 100, 'remarks' => 'Keep this record']);
        $unit = \App\Models\HaruhayUnit::create(['property_id' => $property->id, 'floor' => 3, 'unit_type' => 'Single bed']);
        $this->put(route('villa-group.properties.update', $property), array_replace($data, ['location' => 'Updated Location', 'status' => 'Inactive']))->assertSessionHasNoErrors();
        $this->assertSame('Original Owner', $property->fresh()->owner);
        $this->assertSame('100.00', $property->fresh()->lot_area);
        $this->assertSame('Keep this record', $property->fresh()->remarks);
        $this->assertSame('Inactive', $unit->fresh()->property_status);
        $this->get(route('villa-group.properties.index', ['search' => 'Updated Location']))->assertOk()->assertViewHas('properties', fn ($rows) => $rows->total() === 1)->assertDontSee('>Actions</th>', false);
        $this->get(route('villa-group.properties.show', $property))->assertOk()->assertSee('Original Owner')->assertSee('Updated Location');
        $this->from(route('villa-group.properties.show', $property))->put(route('villa-group.properties.update', $property), array_replace($data, ['name' => '']))->assertSessionHasErrors(['name']);
        $this->get(route('villa-group.properties.show', $property))->assertOk()->assertSee('id="updatePropertyModal"', false)->assertSee('Please check the following:')->assertSee('window.bootstrap.Modal.getOrCreateInstance', false);
    }

    public function test_property_and_attachment_cannot_be_accessed_under_wrong_scope(): void
    {
        $user = $this->actor();
        $division = Division::create(['name' => 'Other Company']);
        $foreign = HaruhayProperty::create($this->data() + ['division_id' => $division->id]);
        $local = HaruhayProperty::create($this->data() + ['division_id' => $user->division_id]);
        $attachment = HaruhayPropertyAttachment::create(['property_id' => $foreign->id, 'path' => 'private.pdf', 'original_name' => 'private.pdf', 'size' => 12]);
        $this->actingAs($user)->get(route('villa-group.properties.show', $foreign))->assertOk();
        $this->put(route('villa-group.properties.update', $foreign), $this->data())->assertSessionHasNoErrors();
        $this->assertSame($division->id, $foreign->fresh()->division_id);
        $this->get(route('villa-group.properties.attachment', [$local, $attachment]))->assertNotFound();
    }
}
