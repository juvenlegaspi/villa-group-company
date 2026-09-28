<?php

namespace Tests\Feature;

use App\Models\AccessRole;
use App\Models\ActivityStatusVoyage;
use App\Models\ActivityVoyage;
use App\Models\CrewLocationPortal;
use App\Models\Department;
use App\Models\Division;
use App\Models\DryDockingHeader;
use App\Models\FuelRobMonitoring;
use App\Models\Position;
use App\Models\Port;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserVesselAssignment;
use App\Models\Vessel;
use App\Models\VesselPositionLog;
use App\Models\VoyageLogHeader;
use App\Models\VoyageLogDetail;
use App\Models\VoyageActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VesselAccessScopeTest extends TestCase
{
    use RefreshDatabase;

    private Division $company;
    private Department $technical;
    private Vessel $assigned;
    private Vessel $unassigned;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Division::create(['name' => 'Villa shipping Lines']);
        $this->technical = Department::create(['division_id' => $this->company->id, 'name' => 'Technical Department']);
        $this->assigned = Vessel::create(['vessel_name' => 'MV Assigned']);
        $this->unassigned = Vessel::create(['vessel_name' => 'MV Restricted']);
    }

    public function test_assigned_personnel_see_only_assigned_vessels_across_shipping_modules(): void
    {
        $engineer = $this->user('Chief Engineer', 'chief-engineer', 'standard-user');
        UserVesselAssignment::create([
            'user_id' => $engineer->id,
            'vessel_id' => $this->assigned->id,
            'assigned_by' => $engineer->id,
            'is_active' => true,
            'effective_from' => today(),
        ]);
        DryDockingHeader::create(['vessel_id' => $this->assigned->id, 'status' => 'pending']);
        DryDockingHeader::create(['vessel_id' => $this->unassigned->id, 'status' => 'pending']);

        $this->actingAs($engineer)->get(route('vessels.index'))
            ->assertOk()->assertSee('MV Assigned')->assertDontSee('MV Restricted');
        $this->actingAs($engineer)->get(route('vessels.show', $this->unassigned))->assertForbidden();
        $this->actingAs($engineer)->get(route('vessel-certificates.index'))
            ->assertOk()->assertSee('MV Assigned')->assertDontSee('MV Restricted');
        $this->actingAs($engineer)->get(route('dry-docking.index'))
            ->assertOk()->assertSee('MV Assigned')->assertDontSee('MV Restricted');
    }

    public function test_technical_manager_sees_all_vessels_but_unrelated_legacy_manager_does_not(): void
    {
        $technicalManager = $this->user('Technical Manager', 'technical-manager', 'company-approver');
        $unrelatedManager = $this->user('Finance Manager', 'finance-manager', 'company-approver');

        $this->actingAs($technicalManager)->get(route('vessels.index'))
            ->assertOk()->assertSee('MV Assigned')->assertSee('MV Restricted');
        $this->actingAs($unrelatedManager)->get(route('vessels.index'))
            ->assertOk()->assertDontSee('MV Assigned')->assertDontSee('MV Restricted');
        $this->actingAs($unrelatedManager)->get(route('vessels.show', $this->assigned))->assertForbidden();
    }

    public function test_user_management_saves_and_removes_vessel_assignments_without_deleting_history(): void
    {
        $admin = $this->user('System Administrator', 'system-administrator', 'system-administrator', true);
        $engineerPosition = Position::create(['division_id' => $this->company->id, 'department_id' => $this->technical->id, 'name' => 'Second Engineer', 'code' => 'second-engineer', 'legacy_role' => 'staff', 'is_active' => true]);
        $standard = AccessRole::where('slug', 'standard-user')->firstOrFail();

        $payload = [
            'name' => 'Assigned', 'lastname' => 'Engineer', 'username' => 'assigned.engineer',
            'email' => 'assigned.engineer@example.test', 'cell_number' => '09171234567',
            'division_id' => $this->company->id, 'department_id' => $this->technical->id,
            'position_id' => $engineerPosition->id, 'access_role_id' => $standard->id,
            'vessel_assignment_present' => 1, 'vessel_ids' => [$this->assigned->id],
        ];

        $this->actingAs($admin)->post(route('users.store'), $payload)->assertRedirect('/users');
        $employee = User::where('username', 'assigned.engineer')->firstOrFail();
        $this->assertDatabaseHas('user_vessel_assignments', ['user_id' => $employee->id, 'vessel_id' => $this->assigned->id, 'is_active' => 1]);

        $this->actingAs($admin)->post(route('users.update', $employee), [...$payload, 'status' => 1, 'vessel_ids' => [$this->unassigned->id]])
            ->assertRedirect(route('users.edit', $employee));
        $this->assertDatabaseHas('user_vessel_assignments', ['user_id' => $employee->id, 'vessel_id' => $this->assigned->id, 'is_active' => 0]);
        $this->assertDatabaseHas('user_vessel_assignments', ['user_id' => $employee->id, 'vessel_id' => $this->unassigned->id, 'is_active' => 1]);
    }

    public function test_only_system_administrator_can_add_a_vessel(): void
    {
        $technicalManager = $this->user('Technical Manager', 'technical-manager', 'company-approver');
        $systemAdministrator = $this->user('System Administrator', 'system-administrator', 'system-administrator', true);

        $this->actingAs($technicalManager)->get(route('vessels.index'))
            ->assertOk()->assertDontSee('Add Vessel');
        $this->actingAs($technicalManager)->get(route('vessels.create'))->assertForbidden();
        $this->actingAs($technicalManager)->post(route('vessels.store'), ['vessel_name' => 'MV Unauthorized'])->assertForbidden();
        $this->assertDatabaseMissing('vessels', ['vessel_name' => 'MV Unauthorized']);

        $this->actingAs($systemAdministrator)->get(route('vessels.create'))->assertOk();
        $this->actingAs($systemAdministrator)->post(route('vessels.store'), ['vessel_name' => 'MV Authorized'])
            ->assertRedirect(route('vessels.index'));
        $this->assertDatabaseHas('vessels', ['vessel_name' => 'MV Authorized']);
    }

    public function test_create_voyage_saves_map_coordinates_and_initial_position_history(): void
    {
        $manager = $this->user('Technical Manager', 'technical-manager', 'company-approver');
        $origin = Port::create(['port_name' => 'Cebu Port', 'status' => 'ACTIVE']);
        $destination = Port::create(['port_name' => 'Manila Port', 'status' => 'ACTIVE']);
        $current = Port::create(['port_name' => 'Cebu Anchorage', 'status' => 'ACTIVE']);

        $response = $this->actingAs($manager)->post(route('voyages.store'), [
            'vessel_id' => $this->assigned->id,
            'cargo_type' => 'General Cargo',
            'cargo_volume' => 120,
            'cargo_unit' => 'MT',
            'crew_on_board' => 18,
            'port_id' => $origin->id,
            'port_destination_id' => $destination->id,
            'current_location_id' => $current->id,
            'origin_latitude' => 10.3098,
            'origin_longitude' => 123.8854,
            'destination_latitude' => 14.5995,
            'destination_longitude' => 120.9842,
            'current_latitude' => 10.3157,
            'current_longitude' => 123.9010,
            'current_position_source' => 'map_pin',
            'voyage_no' => 'VS-2026-001',
            'fuel_rob' => 5000,
        ]);

        $voyage = VoyageLogHeader::firstOrFail();
        $response->assertRedirect('/shipping/voyage-logs/'.$voyage->voyage_id);
        $this->assertSame(10.3157, $voyage->current_latitude);
        $this->assertSame(123.901, $voyage->current_longitude);
        $this->assertDatabaseHas('vessel_position_logs', [
            'vessel_id' => $this->assigned->id,
            'voyage_id' => $voyage->voyage_id,
            'location_name' => 'Cebu Anchorage',
            'source' => 'map_pin',
            'recorded_by' => $manager->id,
        ]);
        $this->assertSame(1, VesselPositionLog::where('voyage_id', $voyage->voyage_id)->count());
        $this->assertDatabaseHas('ports', ['id' => $origin->id, 'latitude' => 10.3098, 'longitude' => 123.8854]);
    }

    public function test_new_voyage_prefills_current_map_point_from_last_completed_voyage(): void
    {
        $manager = $this->user('Technical Manager', 'technical-manager', 'company-approver');
        VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'PREVIOUS-COMPLETED-001',
            'status' => 'COMPLETED',
            'date_completed' => today(),
            'current_location' => 'Previous Final Arrival',
            'current_latitude' => 14.5995,
            'current_longitude' => 120.9842,
        ]);

        $this->actingAs($manager)->get(route('voyages.create', $this->assigned->id))
            ->assertOk()
            ->assertViewHas('lastCompletedVoyage', fn ($voyage) =>
                $voyage?->current_location === 'Previous Final Arrival'
                && (float) $voyage->current_latitude === 14.5995
                && (float) $voyage->current_longitude === 120.9842
            )
            ->assertSee('From the last completed voyage; you may move this pin.')
            ->assertSee('value="previous_voyage"', false)
            ->assertSee('value="Previous Final Arrival"', false)
            ->assertSee('value="14.5995"', false)
            ->assertSee('value="120.9842"', false);
    }

    public function test_voyage_map_keeps_active_and_completed_history_for_accessible_vessels(): void
    {
        $engineer = $this->user('Chief Engineer', 'chief-engineer', 'standard-user');
        UserVesselAssignment::create([
            'user_id' => $engineer->id,
            'vessel_id' => $this->assigned->id,
            'assigned_by' => $engineer->id,
            'is_active' => true,
            'effective_from' => today(),
        ]);
        $visibleVoyage = VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'VISIBLE-VOYAGE',
            'status' => 'OPEN',
            'current_location' => 'Cebu Anchorage',
            'current_latitude' => 10.3157,
            'current_longitude' => 123.9010,
        ]);
        $completedVoyage = VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'COMPLETED-HISTORY',
            'status' => 'COMPLETED',
            'cargo_type' => 'Construction Materials',
            'cargo_volume' => '150 MT',
            'origin_latitude' => 0,
            'origin_longitude' => 0,
            'destination_latitude' => 0,
            'destination_longitude' => 1,
            'current_location' => 'Iloilo Port',
            'current_latitude' => 0,
            'current_longitude' => 1,
            'arrival_date' => '2026-09-15 12:00:00',
        ]);
        $completedVoyage->created_at = '2026-09-15 08:00:00';
        $completedVoyage->save();
        VesselPositionLog::create([
            'vessel_id' => $this->assigned->id,
            'voyage_id' => $completedVoyage->voyage_id,
            'latitude' => 0,
            'longitude' => 0,
            'location_name' => 'Origin Position',
            'source' => 'map_pin',
            'recorded_by' => $engineer->id,
            'recorded_at' => '2026-09-15 08:00:00',
        ]);
        VesselPositionLog::create([
            'vessel_id' => $this->assigned->id,
            'voyage_id' => $completedVoyage->voyage_id,
            'latitude' => 0,
            'longitude' => 1,
            'location_name' => 'Final Position',
            'source' => 'voyage_completion',
            'recorded_by' => $engineer->id,
            'recorded_at' => '2026-09-15 10:00:00',
        ]);
        FuelRobMonitoring::create([
            'voyage_id' => $completedVoyage->voyage_id,
            'vessel_id' => $this->assigned->id,
            'beginning_fuel' => 1000,
            'total_consumed' => 125.5,
            'remaining_fuel' => 874.5,
            'created_by' => $engineer->id,
        ]);
        $metricStatus = ActivityStatusVoyage::create(['name' => 'Metric Sailing', 'status' => true]);
        $metricActivity = ActivityVoyage::create([
            'activity_status_voyage_id' => $metricStatus->id,
            'name' => 'Metric Voyage Activity',
            'status' => true,
        ]);
        $metricDetail = VoyageLogDetail::create([
            'voyage_id' => $completedVoyage->voyage_id,
            'vessel_id' => $this->assigned->id,
            'status' => $metricStatus->id,
            'main_status' => 'COMPLETED',
        ]);
        VoyageActivity::create([
            'voyage_id' => $completedVoyage->voyage_id,
            'voyage_detail_id' => $metricDetail->dtl_id,
            'vessel_id' => $this->assigned->id,
            'status_id' => $metricStatus->id,
            'status_activity_id' => $metricActivity->id,
            'port_location' => 'Encoded Historical Activity',
            'start_date_time' => '2026-09-15 08:00:00',
            'end_date_time' => '2026-09-15 11:00:00',
            'total_hours' => 3,
            'main_status' => 'COMPLETED',
        ]);
        VoyageLogHeader::create([
            'vessel_id' => $this->unassigned->id,
            'voyage_no' => 'HIDDEN-VOYAGE',
            'status' => 'OPEN',
            'current_location' => 'Manila Anchorage',
            'current_latitude' => 14.5995,
            'current_longitude' => 120.9842,
        ]);

        $this->actingAs($engineer)->get(route('voyage-logs.fleet-map'))
            ->assertOk()
            ->assertSee('VISIBLE-VOYAGE')
            ->assertSee('COMPLETED-HISTORY')
            ->assertSee('Active Voyage Details')
            ->assertSee('Fuel at departure')
            ->assertSee('combined into one voyage track')
            ->assertDontSee('HIDDEN-VOYAGE');

        $this->actingAs($engineer)->get(route('voyage-logs.fleet-map', ['voyage' => $completedVoyage->voyage_id]))
            ->assertOk()
            ->assertViewHas('voyageMetrics', function ($metrics) use ($completedVoyage): bool {
                $summary = $metrics->get($completedVoyage->voyage_id);

                return abs(($summary['distance_nm'] ?? 0) - 60.04) < 0.1
                    && abs(($summary['average_speed_knots'] ?? 0) - 20.01) < 0.1
                    && ($summary['fuel_consumed'] ?? null) === 125.5
                    && ($summary['eta_performance'] ?? null) === 'Early by 1h 00m'
                    && ($summary['time_basis'] ?? null) === 'Entered activity times';
            })
            ->assertSee('Back to Voyage')
            ->assertSee('COMPLETED-HISTORY')
            ->assertSee('Completed Voyage Summary')
            ->assertSee('Construction Materials - 150 MT')
            ->assertSee('125.50 L')
            ->assertSee('20.01 kn')
            ->assertSee('Entered activity times')
            ->assertDontSee('VISIBLE-VOYAGE')
            ->assertSee(route('voyages.show', $completedVoyage->voyage_id), false);

        $this->actingAs($engineer)->get(route('voyage-logs.fleet-map', ['vessel' => $this->assigned->id]))
            ->assertOk()
            ->assertSee('Back to Voyage Records')
            ->assertSee('VISIBLE-VOYAGE')
            ->assertSee('COMPLETED-HISTORY')
            ->assertSee(route('vessels.show', $this->assigned->id), false);
    }

    public function test_voyage_accepts_typed_map_locations_without_port_dropdown_records(): void
    {
        $manager = $this->user('Technical Manager', 'technical-manager', 'company-approver');

        $this->actingAs($manager)->post(route('voyages.store'), [
            'vessel_id' => $this->assigned->id,
            'cargo_type' => 'Construction Materials',
            'cargo_volume' => 80,
            'cargo_unit' => 'MT',
            'crew_on_board' => 16,
            'origin_location_name' => 'Cebu International Port',
            'destination_location_name' => 'Iloilo Commercial Port',
            'current_location_name' => 'Cebu Strait',
            'origin_latitude' => 10.3077,
            'origin_longitude' => 123.9185,
            'destination_latitude' => 10.6950,
            'destination_longitude' => 122.5813,
            'current_latitude' => 10.4200,
            'current_longitude' => 123.7000,
            'current_position_source' => 'map_pin',
            'voyage_no' => 'VS-MAP-001',
            'fuel_rob' => 4200,
        ])->assertRedirect();

        $this->assertDatabaseHas('voyage_logs_header', [
            'vessel_id' => $this->assigned->id,
            'port_id' => null,
            'port_destination_id' => null,
            'current_location_id' => null,
            'port_location' => 'Cebu International Port',
            'port_destination' => 'Iloilo Commercial Port',
            'current_location' => 'Cebu Strait',
        ]);
        $this->assertDatabaseHas('vessel_position_logs', [
            'location_name' => 'Cebu Strait',
            'source' => 'map_pin',
        ]);
    }

    public function test_voyage_map_keeps_saved_track_sequence_when_operational_times_are_backdated(): void
    {
        $engineer = $this->user('Chief Engineer', 'chief-engineer', 'standard-user');
        UserVesselAssignment::create([
            'user_id' => $engineer->id,
            'vessel_id' => $this->assigned->id,
            'assigned_by' => $engineer->id,
            'is_active' => true,
            'effective_from' => today(),
        ]);
        $voyage = VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'BACKDATED-TRACK',
            'status' => 'COMPLETED',
            'origin_latitude' => 10,
            'origin_longitude' => 120,
            'destination_latitude' => 12,
            'destination_longitude' => 122,
            'current_latitude' => 12,
            'current_longitude' => 122,
        ]);

        $savedIds = collect([
            ['latitude' => 10, 'longitude' => 120, 'recorded_at' => '2026-09-16 10:00:00'],
            ['latitude' => 11, 'longitude' => 121, 'recorded_at' => '2026-07-08 08:00:00'],
            ['latitude' => 12, 'longitude' => 122, 'recorded_at' => '2026-07-08 09:00:00'],
        ])->map(function (array $point) use ($voyage, $engineer): int {
            return VesselPositionLog::create([
                'vessel_id' => $this->assigned->id,
                'voyage_id' => $voyage->voyage_id,
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
                'location_name' => 'Saved route point',
                'source' => 'map_pin',
                'recorded_by' => $engineer->id,
                'recorded_at' => $point['recorded_at'],
            ])->id;
        })->all();

        $response = $this->actingAs($engineer)
            ->get(route('voyage-logs.fleet-map', ['voyage' => $voyage->voyage_id]))
            ->assertOk();

        $renderedIds = $response->viewData('voyages')->first()?->positionLogs->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame($savedIds, $renderedIds);
    }

    public function test_starting_activity_from_map_updates_current_position_and_tracking_history(): void
    {
        $manager = $this->user('Technical Manager', 'technical-manager', 'company-approver');
        $status = ActivityStatusVoyage::create(['name' => 'Sailing', 'status' => true]);
        $activityDefinition = ActivityVoyage::create([
            'activity_status_voyage_id' => $status->id,
            'name' => 'Underway Check',
            'status' => true,
        ]);
        $voyage = VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'MAP-ACTIVITY-001',
            'status' => 'OPEN',
            'current_location' => 'Old Position',
            'current_latitude' => 10.0,
            'current_longitude' => 123.0,
        ]);
        $detail = VoyageLogDetail::create([
            'voyage_id' => $voyage->voyage_id,
            'vessel_id' => $this->assigned->id,
            'status' => $status->id,
            'main_status' => 'ONGOING',
        ]);

        $this->actingAs($manager)->post(route('voyage.addActivity', $detail->dtl_id), [
            'voyage_detail_id' => $detail->dtl_id,
            'activity_id' => $activityDefinition->id,
            'activity_location_name' => 'Cebu Strait, Philippines',
            'activity_latitude' => 10.4212345,
            'activity_longitude' => 123.7012345,
            'remarks' => 'Position reported from the activity map.',
        ])->assertRedirect();

        $this->assertDatabaseHas('voyage_logs_header', [
            'voyage_id' => $voyage->voyage_id,
            'current_location' => 'Cebu Strait, Philippines',
            'current_latitude' => 10.4212345,
            'current_longitude' => 123.7012345,
        ]);
        $this->assertDatabaseHas('vessel_position_logs', [
            'voyage_id' => $voyage->voyage_id,
            'location_name' => 'Cebu Strait, Philippines',
            'source' => 'map_pin',
            'recorded_by' => $manager->id,
        ]);

        $this->actingAs($manager)->get(route('voyages.show', $voyage->voyage_id))
            ->assertOk()
            ->assertSee('Pin Current Vessel Location')
            ->assertSee('You may type the latitude and longitude manually')
            ->assertSee('Cebu Strait, Philippines');
    }

    public function test_first_activity_of_new_voyage_continues_from_vessels_last_activity_datetime(): void
    {
        $manager = $this->user('Technical Manager', 'technical-manager', 'company-approver');
        $status = ActivityStatusVoyage::create(['name' => 'Sailing', 'status' => true]);
        $activityDefinition = ActivityVoyage::create([
            'activity_status_voyage_id' => $status->id,
            'name' => 'Underway Check',
            'status' => true,
        ]);
        $previousVoyage = VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'PREVIOUS-VOYAGE',
            'status' => 'COMPLETED',
        ]);
        $previousDetail = VoyageLogDetail::create([
            'voyage_id' => $previousVoyage->voyage_id,
            'vessel_id' => $this->assigned->id,
            'status' => $status->id,
            'main_status' => 'COMPLETED',
        ]);
        VoyageActivity::create([
            'voyage_id' => $previousVoyage->voyage_id,
            'voyage_detail_id' => $previousDetail->dtl_id,
            'vessel_id' => $this->assigned->id,
            'status_id' => $status->id,
            'status_activity_id' => $activityDefinition->id,
            'port_location' => 'Previous Port',
            'start_date_time' => '2026-09-13 08:00:00',
            'end_date_time' => '2026-09-13 11:45:00',
            'main_status' => 'COMPLETED',
        ]);
        $newVoyage = VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'NEW-VOYAGE',
            'status' => 'OPEN',
        ]);
        $newDetail = VoyageLogDetail::create([
            'voyage_id' => $newVoyage->voyage_id,
            'vessel_id' => $this->assigned->id,
            'status' => $status->id,
            'main_status' => 'ONGOING',
        ]);

        $this->actingAs($manager)->post(route('voyage.addActivity', $newDetail->dtl_id), [
            'voyage_detail_id' => $newDetail->dtl_id,
            'activity_id' => $activityDefinition->id,
            'activity_location_name' => 'New Voyage Position',
            'activity_latitude' => 10.5,
            'activity_longitude' => 123.5,
        ])->assertRedirect();

        $this->assertDatabaseHas('voyage_activities', [
            'voyage_id' => $newVoyage->voyage_id,
            'vessel_id' => $this->assigned->id,
            'start_date_time' => '2026-09-13 11:45:00',
        ]);
    }

    public function test_current_location_can_be_updated_without_starting_an_activity(): void
    {
        $manager = $this->user('Technical Manager', 'technical-manager', 'company-approver');
        $voyage = VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'MANUAL-LOCATION-001',
            'status' => 'OPEN',
            'current_location' => 'Previous Position',
            'current_latitude' => 10.1,
            'current_longitude' => 123.1,
        ]);

        $this->actingAs($manager)->post(route('voyage.current-location.update', $voyage->voyage_id), [
            'location_name' => 'Updated Vessel Position',
            'latitude' => 11.2345678,
            'longitude' => 124.3456789,
        ])->assertRedirect();

        $this->assertDatabaseHas('voyage_logs_header', [
            'voyage_id' => $voyage->voyage_id,
            'current_location' => 'Updated Vessel Position',
            'current_latitude' => 11.2345678,
            'current_longitude' => 124.3456789,
        ]);
        $this->assertDatabaseHas('vessel_position_logs', [
            'voyage_id' => $voyage->voyage_id,
            'location_name' => 'Updated Vessel Position',
            'source' => 'manual_update',
            'recorded_by' => $manager->id,
        ]);

        $voyage->update(['status' => 'COMPLETED']);
        $this->actingAs($manager)->post(route('voyage.current-location.update', $voyage->voyage_id), [
            'location_name' => 'Should Not Replace Final Position',
            'latitude' => 12.0,
            'longitude' => 125.0,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('vessel_position_logs', [
            'voyage_id' => $voyage->voyage_id,
            'location_name' => 'Should Not Replace Final Position',
        ]);
    }

    public function test_completing_voyage_records_its_final_arrival_location(): void
    {
        $manager = $this->user('Technical Manager', 'technical-manager', 'company-approver');
        $status = ActivityStatusVoyage::create(['name' => 'Arrival', 'status' => true]);
        $voyage = VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'ARRIVAL-001',
            'status' => 'OPEN',
            'port_destination' => 'Manila Port',
            'destination_latitude' => 14.5995,
            'destination_longitude' => 120.9842,
            'current_location' => 'Manila Bay',
            'current_latitude' => 14.55,
            'current_longitude' => 120.90,
        ]);
        VoyageLogDetail::create([
            'voyage_id' => $voyage->voyage_id,
            'vessel_id' => $this->assigned->id,
            'status' => $status->id,
            'main_status' => 'COMPLETED',
        ]);

        $this->actingAs($manager)->post(route('voyage.complete', $voyage->voyage_id), [])
            ->assertSessionHasErrors(['final_location_name', 'final_latitude', 'final_longitude']);
        $this->assertDatabaseHas('voyage_logs_header', [
            'voyage_id' => $voyage->voyage_id,
            'status' => 'OPEN',
        ]);

        $this->actingAs($manager)->post(route('voyage.complete', $voyage->voyage_id), [
            'final_location_name' => 'Manila Port Arrival Berth',
            'final_latitude' => 14.5995,
            'final_longitude' => 120.9842,
        ])->assertRedirect();

        $this->assertDatabaseHas('voyage_logs_header', [
            'voyage_id' => $voyage->voyage_id,
            'status' => 'COMPLETED',
            'current_location' => 'Manila Port Arrival Berth',
            'current_latitude' => 14.5995,
            'current_longitude' => 120.9842,
        ]);
        $this->assertDatabaseHas('vessel_position_logs', [
            'voyage_id' => $voyage->voyage_id,
            'location_name' => 'Manila Port Arrival Berth',
            'source' => 'voyage_completion',
            'recorded_by' => $manager->id,
        ]);
    }

    public function test_completing_status_requires_and_records_current_map_location(): void
    {
        $manager = $this->user('Technical Manager', 'technical-manager', 'company-approver');
        $status = ActivityStatusVoyage::create(['name' => 'Sailing', 'status' => true]);
        $activityDefinition = ActivityVoyage::create([
            'activity_status_voyage_id' => $status->id,
            'name' => 'Position Check',
            'status' => true,
        ]);
        $voyage = VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'STATUS-MAP-001',
            'status' => 'OPEN',
            'current_location' => 'Old Status Position',
            'current_latitude' => 10.1,
            'current_longitude' => 123.1,
        ]);
        $detail = VoyageLogDetail::create([
            'voyage_id' => $voyage->voyage_id,
            'vessel_id' => $this->assigned->id,
            'status' => $status->id,
            'main_status' => 'ONGOING',
        ]);
        VoyageActivity::create([
            'voyage_id' => $voyage->voyage_id,
            'voyage_detail_id' => $detail->dtl_id,
            'vessel_id' => $this->assigned->id,
            'status_id' => $status->id,
            'status_activity_id' => $activityDefinition->id,
            'port_location' => 'Old Status Position',
            'start_date_time' => '2026-09-15 08:00:00',
            'end_date_time' => '2026-09-15 09:00:00',
            'main_status' => 'ONGOING',
        ]);

        $this->actingAs($manager)->post(route('voyage.status.complete', $detail->dtl_id), [])
            ->assertSessionHasErrors(['completion_location_name', 'completion_latitude', 'completion_longitude']);
        $this->assertDatabaseHas('voyage_logs_details', ['dtl_id' => $detail->dtl_id, 'main_status' => 'ONGOING']);

        $this->actingAs($manager)->post(route('voyage.status.complete', $detail->dtl_id), [
            'completion_location_name' => 'Status Completion Point',
            'completion_latitude' => 10.6123456,
            'completion_longitude' => 123.7123456,
        ])->assertRedirect();

        $this->assertDatabaseHas('voyage_logs_details', ['dtl_id' => $detail->dtl_id, 'main_status' => 'COMPLETED']);
        $this->assertDatabaseHas('voyage_logs_header', [
            'voyage_id' => $voyage->voyage_id,
            'current_location' => 'Status Completion Point',
            'current_latitude' => 10.6123456,
            'current_longitude' => 123.7123456,
        ]);
        $this->assertDatabaseHas('vessel_position_logs', [
            'voyage_id' => $voyage->voyage_id,
            'location_name' => 'Status Completion Point',
            'source' => 'status_completion',
            'recorded_by' => $manager->id,
        ]);
    }

    public function test_secure_crew_link_shows_only_its_vessel_ongoing_voyage_and_accepts_guest_location_update(): void
    {
        $token = CrewLocationPortal::current()->token;
        $voyage = VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'CREW-LINK-001',
            'status' => 'OPEN',
            'port_location' => 'Cebu Port',
            'port_destination' => 'Manila Port',
            'origin_latitude' => 10.3157,
            'origin_longitude' => 123.8854,
            'destination_latitude' => 14.5995,
            'destination_longitude' => 120.9842,
            'current_location' => 'Cebu Anchorage',
            'current_latitude' => 10.4,
            'current_longitude' => 123.8,
        ]);
        VoyageLogHeader::create([
            'vessel_id' => $this->unassigned->id,
            'voyage_no' => 'OTHER-VESSEL-001',
            'status' => 'OPEN',
        ]);

        $this->get(route('crew-location.show', $token))
            ->assertOk()
            ->assertSee('MV Assigned')
            ->assertSee('MV Restricted')
            ->assertSee('Choose an ongoing vessel');

        $this->get(route('crew-location.show', ['token' => $token, 'vessel' => $this->assigned->id]))
            ->assertOk()
            ->assertSee('CREW-LINK-001')
            ->assertSee('30-minute automatic GPS')
            ->assertSee('Start Tracking')
            ->assertDontSee('OTHER-VESSEL-001');

        $this->post(route('crew-location.update', $token), [
            'vessel_id' => $this->assigned->id,
            'reporter_name' => 'Captain Test Crew',
            'location_name' => 'Mindoro Strait',
            'latitude' => 12.3456789,
            'longitude' => 121.2345678,
            'accuracy_meters' => 15,
        ])->assertRedirect(route('crew-location.show', ['token' => $token, 'vessel' => $this->assigned->id]));

        $this->assertDatabaseHas('voyage_logs_header', [
            'voyage_id' => $voyage->voyage_id,
            'current_location' => 'Mindoro Strait',
            'current_latitude' => 12.3456789,
            'current_longitude' => 121.2345678,
        ]);
        $this->assertDatabaseHas('vessel_position_logs', [
            'vessel_id' => $this->assigned->id,
            'voyage_id' => $voyage->voyage_id,
            'source' => 'crew_link',
            'reporter_name' => 'Captain Test Crew',
            'recorded_by' => null,
        ]);
    }

    public function test_automatic_crew_gps_records_capture_time_and_stops_accepting_points_after_completion(): void
    {
        $token = CrewLocationPortal::current()->token;
        $voyage = VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'AUTO-GPS-001',
            'status' => 'OPEN',
            'current_location' => 'Starting Position',
            'current_latitude' => 10,
            'current_longitude' => 123,
        ]);
        $capturedAt = now()->subMinutes(2)->startOfSecond();
        $payload = [
            'vessel_id' => $this->assigned->id,
            'reporter_name' => 'Duty Officer',
            'location_name' => 'Automatic GPS position',
            'latitude' => 10.1234567,
            'longitude' => 123.7654321,
            'accuracy_meters' => 12,
            'captured_at' => $capturedAt->toIso8601String(),
        ];

        $this->postJson(route('crew-location.automatic', $token), $payload)
            ->assertOk()
            ->assertJsonPath('voyage_id', $voyage->voyage_id);
        $this->assertDatabaseHas('vessel_position_logs', [
            'voyage_id' => $voyage->voyage_id,
            'source' => 'crew_auto_gps',
            'reporter_name' => 'Duty Officer',
            'recorded_at' => $capturedAt->format('Y-m-d H:i:s'),
        ]);

        $voyage->update(['status' => 'COMPLETED', 'date_completed' => today()]);
        $this->postJson(route('crew-location.automatic', $token), [
            ...$payload,
            'location_name' => 'Must Not Save After Completion',
            'captured_at' => now()->toIso8601String(),
        ])->assertStatus(422);
        $this->assertDatabaseMissing('vessel_position_logs', ['location_name' => 'Must Not Save After Completion']);
    }

    public function test_crew_link_rejects_updates_without_an_ongoing_voyage_and_invalid_tokens(): void
    {
        $token = CrewLocationPortal::current()->token;
        VoyageLogHeader::create([
            'vessel_id' => $this->assigned->id,
            'voyage_no' => 'COMPLETED-001',
            'status' => 'COMPLETED',
            'date_completed' => today(),
        ]);

        $this->get(route('crew-location.show', $token))
            ->assertOk()
            ->assertSee('There are no ongoing voyages');
        $this->post(route('crew-location.update', $token), [
            'vessel_id' => $this->assigned->id,
            'reporter_name' => 'Unauthorized Update',
            'location_name' => 'Should Not Save',
            'latitude' => 10,
            'longitude' => 120,
        ])->assertStatus(422);
        $this->assertDatabaseMissing('vessel_position_logs', ['location_name' => 'Should Not Save']);
        $this->get(route('crew-location.show', str_repeat('x', 64)))->assertNotFound();
    }

    public function test_authorized_manager_can_regenerate_a_crew_location_link(): void
    {
        $manager = $this->user('Technical Manager', 'technical-manager', 'company-approver');
        $oldToken = CrewLocationPortal::current()->token;

        $this->actingAs($manager)
            ->post(route('vessels.location-portal.regenerate'))
            ->assertRedirect(route('vessels.index'));

        $newToken = CrewLocationPortal::current()->fresh()->token;
        $this->assertNotSame($oldToken, $newToken);
        $this->get(route('crew-location.show', $oldToken))->assertNotFound();
        $this->get(route('crew-location.show', $newToken))->assertOk();
    }

    private function user(string $name, string $positionCode, string $accessRole, bool $admin = false): User
    {
        static $number = 0;
        $number++;
        $position = Position::firstOrCreate(
            ['division_id' => $this->company->id, 'department_id' => $this->technical->id, 'code' => $positionCode],
            ['name' => $name, 'legacy_role' => $positionCode === 'technical-manager' ? 'manager' : 'staff', 'is_active' => true]
        );
        $position->permissions()->syncWithoutDetaching(Permission::whereIn('slug', [
            'shipping.operations.access', 'shipping.vessel_management.access', 'shipping.voyages.access',
            'shipping.technical_defects.access', 'shipping.certificates.access', 'shipping.dry_docking.access',
        ])->pluck('id'));

        return User::create([
            'name' => $name, 'lastname' => 'User', 'username' => 'vessel-scope-'.$number,
            'email' => 'vessel-scope-'.$number.'@example.test', 'cell_number' => '09170000000',
            'password' => Hash::make('password-for-tests'), 'role' => $admin ? 'admin' : ($accessRole === 'company-approver' ? 'manager' : 'staff'),
            'is_admin' => $admin, 'division_id' => $this->company->id, 'department_id' => $this->technical->id,
            'position_id' => $position->id, 'access_role_id' => AccessRole::where('slug', $accessRole)->value('id'),
            'must_change_password' => false, 'status' => true,
        ]);
    }
}
