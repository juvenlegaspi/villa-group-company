<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HaruhayPublicRoomTest extends TestCase
{
    use RefreshDatabase;

    private function property(string $company = 'Haruhay', string $status = 'Operational'): HaruhayProperty
    {
        $division = Division::firstOrCreate(['name' => $company]);

        return HaruhayProperty::create(['name' => $company.' Space', 'owner' => 'PRIVATE OWNER', 'type' => 'Dormitory', 'street_barangay' => 'PRIVATE ADDRESS', 'city' => 'Cebu', 'province' => 'Cebu', 'status' => $status, 'division_id' => $division->id]);
    }

    private function unit(HaruhayProperty $property, array $overrides = []): HaruhayUnit
    {
        return HaruhayUnit::create($overrides + ['property_id' => $property->id, 'floor' => 3, 'unit_type' => 'Single air-conditioned room', 'capacity' => 1, 'monthly_rent' => 10000, 'rent_basis' => 'per_room', 'room_condition' => 'Ready', 'occupied_by' => 'PRIVATE OCCUPANT', 'remarks' => 'PRIVATE REMARKS', 'rate_notes' => 'PRIVATE PRICING NOTES']);
    }

    public function test_public_catalogue_lists_all_fourteen_rooms_and_only_approved_fields(): void
    {
        $property = $this->property();
        for ($i = 0; $i < 14; $i++) {
            $this->unit($property);
        }
        $this->unit($this->property('Foreign Company'), ['unit_type' => 'FOREIGN ROOM']);
        $response = $this->getJson(route('public.haruhay.rooms'))->assertOk()->assertJsonCount(14, 'data')->assertJsonPath('meta.count', 14)
            ->assertJsonPath('data.0.unit_no', 'DR-01')->assertJsonPath('data.13.unit_no', 'DR-14')
            ->assertJsonPath('data.0.monthly_rent', '10000.00')->assertJsonPath('data.0.availability', 'available');
        $this->assertSame(['slug', 'unit_no', 'property_id', 'property_name', 'unit_type', 'category', 'floor', 'area', 'capacity', 'rent_basis', 'monthly_rent', 'shared_rate', 'room_condition', 'available_slots', 'availability', 'reservation_scope', 'can_reserve'], array_keys($response->json('data.0')));
        foreach (['PRIVATE OWNER', 'PRIVATE ADDRESS', 'PRIVATE OCCUPANT', 'PRIVATE REMARKS', 'PRIVATE PRICING NOTES', 'FOREIGN ROOM'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
    }

    public function test_shared_capacity_deducts_current_tenants_and_reservations(): void
    {
        $unit = $this->unit($this->property(), ['capacity' => 3, 'status' => 'Occupied', 'rent_basis' => 'per_person', 'monthly_rent' => 3000]);
        foreach (['Active', 'Pending Move-in'] as $status) {
            HaruhayTenant::create(['unit_id' => $unit->id, 'name' => 'PRIVATE TENANT', 'contact_number' => 'PRIVATE PHONE', 'status' => $status]);
        }
        $response = $this->getJson(route('public.haruhay.rooms'))->assertOk()->assertJsonPath('data.0.available_slots', 1)->assertJsonPath('data.0.availability', 'limited')->assertJsonPath('data.0.category', 'shared');
        $this->assertStringNotContainsString('PRIVATE TENANT', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE PHONE', $response->getContent());
    }

    public function test_full_maintenance_and_inactive_properties_remain_listed_as_unavailable(): void
    {
        $property = $this->property();
        $full = $this->unit($property, ['status' => 'Occupied']);
        HaruhayTenant::create(['unit_id' => $full->id, 'name' => 'Resident', 'contact_number' => '09170000000', 'status' => 'Active']);
        $this->unit($property, ['room_condition' => 'Under Maintenance']);
        $this->unit($this->property('Haruhay', 'Inactive'));
        $this->getJson(route('public.haruhay.rooms'))->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.availability', 'full')->assertJsonPath('data.0.available_slots', 0)
            ->assertJsonPath('data.1.availability', 'unavailable')->assertJsonPath('data.1.available_slots', 0)
            ->assertJsonPath('data.2.availability', 'unavailable');
    }

    public function test_public_endpoint_does_not_accept_writes(): void
    {
        $this->postJson(route('public.haruhay.rooms'), ['unit_type' => 'Altered'])->assertMethodNotAllowed();
        $this->assertDatabaseCount('haruhay_units', 0);
    }
    public function test_catalogue_includes_empty_haruhay_properties_and_room_property_ids(): void
    {
        $cebu = $this->property();
        $unit = $this->unit($cebu);
        $dumaguete = $this->property();
        $dumaguete->update(['name' => 'Haruhay Dumaguete']);
        $inactive = $this->property('Haruhay', 'Under Construction');
        $this->property('Foreign Company');

        $this->getJson(route('public.haruhay.rooms'))->assertOk()
            ->assertJsonCount(3, 'meta.properties')
            ->assertJsonPath('data.0.property_id', $cebu->id)
            ->assertJsonPath('meta.properties.1.id', $dumaguete->id)
            ->assertJsonPath('meta.properties.1.name', 'Haruhay Dumaguete')
            ->assertJsonPath('meta.properties.1.can_reserve', true)
            ->assertJsonPath('meta.properties.2.id', $inactive->id)
            ->assertJsonPath('meta.properties.2.can_reserve', false);
    }

}
