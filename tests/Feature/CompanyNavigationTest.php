<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_owner_has_read_only_access_to_all_company_workspaces(): void
    {
        $this->seedCompanies();
        $owner = $this->user(['role' => 'owner', 'is_admin' => true]);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Division dashboards')
            ->assertSee('>Companies</span>', false);

        $this->actingAs($owner)->get(route('companies'))
            ->assertOk()
            ->assertSee('Villa Shipping Lines, Inc.')
            ->assertSee('Yatira Construction, Inc.')
            ->assertSee('JMV Mining &amp; Development', false)
            ->assertSee('HYVE');

        $this->actingAs($owner)->get(route('vessels.index'))
            ->assertOk();

        $this->actingAs($owner)->get(route('division.dashboard', 'Villa shipping Lines'))
            ->assertOk()
            ->assertSee('Voyage Dashboard')
            ->assertSee('Defects Dashboard')
            ->assertSee('Certificates')
            ->assertSee('Open vessel monitoring');

        $this->actingAs($owner)->get(route('voyage-logs.dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('tech-defects.dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('vessel-certificates.dashboard'))->assertOk();
    }

    public function test_executive_viewer_access_profile_has_read_only_company_dashboards(): void
    {
        $this->seedCompanies();
        $executiveRoleId = DB::table('access_roles')->where('slug', 'executive-viewer')->value('id');
        $executive = $this->user([
            'role' => 'staff',
            'is_admin' => false,
            'access_role_id' => $executiveRoleId,
        ]);

        $this->actingAs($executive)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Read-only executive view');

        $this->actingAs($executive)->get(route('companies'))
            ->assertOk()
            ->assertSee('Villa Shipping Lines, Inc.')
            ->assertSee('Yatira Construction, Inc.')
            ->assertSee('JMV Mining &amp; Development', false)
            ->assertSee('HYVE');

        $this->actingAs($executive)->get(route('division.dashboard', 'Villa shipping Lines'))
            ->assertOk()
            ->assertSee('Read-only executive dashboard')
            ->assertSee('Voyage Dashboard')
            ->assertSee('Open vessel monitoring');

        $this->actingAs($executive)->get(route('voyage-logs.dashboard'))->assertOk();
        $this->actingAs($executive)->get(route('tech-defects.dashboard'))->assertOk();
        $this->actingAs($executive)->get(route('vessel-certificates.dashboard'))->assertOk();
        $this->actingAs($executive)->get(route('vessels.index'))->assertOk();
        $this->actingAs($executive)->get(route('shipping.calendar'))->assertOk();
        $this->actingAs($executive)->get(route('tech-defects.index'))->assertOk();
        $this->actingAs($executive)->get(route('vessel-certificates.index'))->assertOk();
        $this->actingAs($executive)->get(route('dry-docking.index'))->assertOk();
        $this->actingAs($executive)->get(route('yatira.applications'))->assertOk()->assertSee('Sales Monitoring');
        $this->actingAs($executive)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($executive)->get(route('yatira.inventory.index'))->assertOk();
        $this->actingAs($executive)->get(route('yatira.sales.index'))->assertOk()->assertDontSee('Register Lead');
        $this->actingAs($executive)->get(route('jmv.applications'))->assertOk()->assertSee('Stock Requests');
        $this->actingAs($executive)->get(route('jmv.inventory.index'))->assertOk();
        $this->actingAs($executive)->get(route('jmv.stockin.index'))->assertOk();
        $this->actingAs($executive)->get(route('jmv.stockout.index'))->assertOk();
        $this->actingAs($executive)->get(route('jmv.requests.index'))->assertOk();
        $this->actingAs($executive)->get(route('users.index'))->assertForbidden();
        $this->actingAs($executive)->get(route('vessels.create'))->assertForbidden();
        $this->actingAs($executive)->post(route('vessels.store'), [])->assertForbidden();
        $this->actingAs($executive)->post(route('jmv.requests.store'), [])->assertForbidden();
        $this->actingAs($executive)->post(route('shipping.calendar.events.store'), [])->assertForbidden();
    }

    public function test_admin_can_see_dashboard_and_all_company_cards(): void
    {
        $this->seedCompanies();
        $admin = $this->user(['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Division dashboards')
            ->assertSee('>Companies</span>', false);

        $this->actingAs($admin)->get(route('companies'))
            ->assertOk()
            ->assertSee('Villa Shipping Lines, Inc.')
            ->assertSee('Yatira Construction, Inc.')
            ->assertSee('JMV Mining &amp; Development', false)
            ->assertSee('Villa Group')
            ->assertSee('HYVE')
            ->assertSee('images/companies/villa-shipping-lines.webp', false)
            ->assertSee('images/companies/yatira.webp', false)
            ->assertSee('images/companies/jmv.webp', false)
            ->assertSee('images/companies/villa-group.webp', false)
            ->assertSee('images/companies/hyve.webp', false);
    }

    public function test_non_owner_is_redirected_from_owner_dashboard_and_only_sees_assigned_company(): void
    {
        $companies = $this->seedCompanies();
        $user = $this->user(['division_id' => $companies['Yatira']]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('companies'));

        $this->actingAs($user)->get(route('companies'))
            ->assertOk()
            ->assertSee('Yatira Construction, Inc.')
            ->assertDontSee('Villa Shipping Lines, Inc.')
            ->assertDontSee('JMV Mining &amp; Development', false)
            ->assertDontSee('HYVE');
    }

    public function test_yatira_applications_has_a_clickable_sales_module(): void
    {
        $companies = $this->seedCompanies();
        $user = $this->user(['division_id' => $companies['Yatira'], 'role' => 'admin', 'is_admin' => true]);

        $this->actingAs($user)->get(route('yatira.applications'))
            ->assertOk()
            ->assertSee('Sales Monitoring')
            ->assertSee(route('yatira.sales.index'), false);

        $this->actingAs($user)->get(route('yatira.sales.index'))
            ->assertOk()
            ->assertSee('Sales Monitoring')
            ->assertSee('Register Lead')
            ->assertSee('Weighted Value');
    }

    public function test_jmv_company_opens_its_application_workspace(): void
    {
        $companies = $this->seedCompanies();
        $user = $this->user([
            'division_id' => $companies['JMV'],
            'is_admin' => true,
        ]);

        $this->actingAs($user)->get(route('companies'))
            ->assertOk()
            ->assertSee(route('jmv.applications'), false);

        $this->actingAs($user)->get(route('jmv.applications'))
            ->assertOk()
            ->assertSee('JMV Mining &amp; Development', false)
            ->assertSee('Company Applications')
            ->assertSee('Inventory')
            ->assertSee('Stock In')
            ->assertSee('Stock Out')
            ->assertSee(route('jmv.inventory.index'), false)
            ->assertSee(route('jmv.stockin.index'), false)
            ->assertSee(route('jmv.stockout.index'), false);
    }

    public function test_shipping_company_opens_applications_and_operations_modules(): void
    {
        $companies = $this->seedCompanies();
        $admin = $this->user([
            'role' => 'admin',
            'is_admin' => true,
            'division_id' => $companies['Villa shipping Lines'],
        ]);

        $this->actingAs($admin)
            ->get(route('division.dashboard', 'Villa shipping Lines'))
            ->assertOk()
            ->assertSee('Villa Shipping Lines Command Center')
            ->assertSee('>Dashboard</span>', false)
            ->assertSee('>Companies</span>', false)
            ->assertDontSee('>Vessel Management</span>', false);

        $this->actingAs($admin)->get(route('shipping.applications'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'" aria-label="Home"', false)
            ->assertSee('Operations')
            ->assertSee('Procurement')
            ->assertSee('Inventory')
            ->assertSee('User Management');

        $this->actingAs($admin)->get(route('shipping.operations'))
            ->assertRedirect(route('vessels.index'));

        $this->actingAs($admin)->get(route('vessels.index'))
            ->assertOk()
            ->assertSee('Vessel Management')
            ->assertSee('Technical &amp; Defect', false)
            ->assertSee('Certificates')
            ->assertSee('Dry Docking');
    }

    public function test_shipping_staff_does_not_see_admin_only_application_cards(): void
    {
        $companies = $this->seedCompanies();
        $staff = $this->user(['division_id' => $companies['Villa shipping Lines']]);

        $this->actingAs($staff)->get(route('shipping.applications'))
            ->assertOk()
            ->assertSee('href="'.route('companies').'" aria-label="Home"', false)
            ->assertDontSee('User Management')
            ->assertDontSee('>Operations</span>', false)
            ->assertSee('No application assigned');

        $this->actingAs($staff)->get(route('shipping.operations'))
            ->assertForbidden();

        $this->actingAs($staff)->get(route('vessels.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('division.dashboard', 'Villa shipping Lines'))
            ->assertForbidden();
    }

    public function test_unknown_division_has_a_safe_dashboard_placeholder(): void
    {
        $this->seedCompanies();
        DB::table('divisions')->insert(['name' => 'New Venture', 'created_at' => now(), 'updated_at' => now()]);
        $admin = $this->user(['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($admin)->get(route('division.dashboard', 'New Venture'))
            ->assertOk()
            ->assertSee('Dashboard preparation')
            ->assertSee('New Venture');
    }

    public function test_yatira_month_metric_does_not_include_previous_year(): void
    {
        $companies = $this->seedCompanies();
        $admin = $this->user(['role' => 'admin', 'is_admin' => true]);

        foreach ([now(), now()->subYear()] as $index => $date) {
            DB::table('suppliers')->insert([
                'division_id' => $companies['Yatira'],
                'name' => 'Supplier '.$index,
                'business_type' => 'Construction',
                'tin' => 'TIN-'.$index,
                'normalized_tin' => 'TIN'.$index,
                'products' => 'Cement',
                'status' => true,
                'added_by' => $admin->id,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        $this->actingAs($admin)->get(route('division.dashboard', 'Yatira'))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['thisMonthSuppliers'] === 1);
    }

    public function test_shipping_dashboard_uses_current_operational_statuses(): void
    {
        $companies = $this->seedCompanies();
        $admin = $this->user(['role' => 'admin', 'is_admin' => true]);
        $vesselId = DB::table('vessels')->insertGetId([
            'vessel_name' => 'MV Metrics', 'vessel_status' => 'Operational',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([['ANCHORED', 12], ['SAILING', 18], ['COMPLETED', 99]] as $index => [$status, $crew]) {
            DB::table('voyage_logs_header')->insert([
                'vessel_id' => $vesselId, 'voyage_no' => 'METRIC-'.$index,
                'date_created' => now()->toDateString(), 'status' => $status,
                'crew_on_board' => $crew, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('division.dashboard', 'Villa shipping Lines'));
        $response->assertOk()
            ->assertViewHas('activeVessels', 1)
            ->assertViewHas('openVoyages', 2)
            ->assertViewHas('completedVoyages', 1)
            ->assertViewHas('anchored', 1)
            ->assertViewHas('sailing', 1)
            ->assertViewHas('totalCrew', 30);
    }

    public function test_shipping_dashboard_filters_period_metrics_by_selected_range(): void
    {
        $this->seedCompanies();
        $admin = $this->user(['role' => 'admin', 'is_admin' => true]);
        $vesselId = DB::table('vessels')->insertGetId([
            'vessel_name' => 'MV Range Test', 'vessel_status' => 'Operational',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([now(), now()->subYear()] as $index => $date) {
            DB::table('voyage_logs_header')->insert([
                'vessel_id' => $vesselId,
                'voyage_no' => 'RANGE-'.$index,
                'date_created' => $date->toDateString(),
                'status' => 'COMPLETED',
                'crew_on_board' => 10,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('division.dashboard', [
                'division' => 'Villa shipping Lines',
                'range' => 'this_year',
            ]))
            ->assertOk()
            ->assertViewHas('totalVoyages', 1)
            ->assertViewHas('dashboardRange', fn (array $range) => $range['key'] === 'this_year');

        $this->actingAs($admin)
            ->get(route('division.dashboard', [
                'division' => 'Villa shipping Lines',
                'range' => 'all_time',
            ]))
            ->assertOk()
            ->assertViewHas('totalVoyages', 2);
    }

    public function test_shipping_dashboard_accepts_custom_range_and_validates_its_dates(): void
    {
        $this->seedCompanies();
        $admin = $this->user(['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('division.dashboard', [
                'division' => 'Villa shipping Lines',
                'range' => 'custom',
                'date_from' => '2026-01-10',
                'date_to' => '2026-01-15',
            ]))
            ->assertOk()
            ->assertViewHas('dashboardRange', fn (array $range) => $range['from'] === '2026-01-10'
                && $range['to'] === '2026-01-15'
                && $range['label'] === 'Jan 10, 2026 - Jan 15, 2026');

        $this->from(route('division.dashboard', 'Villa shipping Lines'))
            ->actingAs($admin)
            ->get(route('division.dashboard', [
                'division' => 'Villa shipping Lines',
                'range' => 'custom',
                'date_from' => '2026-01-15',
                'date_to' => '2026-01-10',
            ]))
            ->assertRedirect(route('division.dashboard', 'Villa shipping Lines'))
            ->assertSessionHasErrors('date_to');
    }

    public function test_shipping_dashboard_uses_real_defect_workflow_statuses(): void
    {
        $this->seedCompanies();
        $admin = $this->user(['role' => 'admin', 'is_admin' => true]);
        $vesselId = DB::table('vessels')->insertGetId([
            'vessel_name' => 'MV Defect Metrics', 'vessel_status' => 'Operational',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([['For Review', 'Critical'], ['Closed', 'Major']] as [$status, $severity]) {
            DB::table('tech_defects')->insert([
                'vessel_id' => $vesselId,
                'status' => $status,
                'date_identified' => now()->toDateString(),
                'defect_description' => $status.' dashboard test',
                'severity_level' => $severity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('division.dashboard', 'Villa shipping Lines'))
            ->assertOk()
            ->assertViewHas('totalDefects', 2)
            ->assertViewHas('criticalDefects', 1)
            ->assertViewHas('activeDefects', 1)
            ->assertViewHas('defectClosureRate', 50.0)
            ->assertViewHas('defectStatusLabels', fn (array $labels) => in_array('For Review', $labels, true)
                && in_array('Closed', $labels, true));
    }

    public function test_shipping_executive_dashboard_exposes_live_map_and_attention_metrics(): void
    {
        $this->seedCompanies();
        $admin = $this->user(['role' => 'admin', 'is_admin' => true]);
        $vesselId = DB::table('vessels')->insertGetId([
            'vessel_name' => 'MV Executive Map',
            'vessel_status' => 'Operational',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $voyageId = DB::table('voyage_logs_header')->insertGetId([
            'vessel_id' => $vesselId,
            'voyage_no' => 'EXEC-001',
            'date_created' => today(),
            'status' => 'SAILING',
            'crew_on_board' => 14,
            'current_location' => 'Manila Bay',
            'port_destination' => 'Cebu Port',
            'current_latitude' => 14.58,
            'current_longitude' => 120.97,
            'arrival_date' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('vessel_position_logs')->insert([
            'vessel_id' => $vesselId,
            'voyage_id' => $voyageId,
            'latitude' => 14.58,
            'longitude' => 120.97,
            'location_name' => 'Manila Bay',
            'source' => 'map_pin',
            'recorded_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $completedVoyageId = DB::table('voyage_logs_header')->insertGetId([
            'vessel_id' => $vesselId,
            'voyage_no' => 'EXEC-HISTORY-001',
            'date_created' => today()->subDays(5),
            'date_completed' => today()->subDays(2),
            'status' => 'COMPLETED',
            'port_location' => 'Cebu Port',
            'port_destination' => 'Manila Port',
            'current_location' => 'Manila Port',
            'origin_latitude' => 10.31,
            'origin_longitude' => 123.89,
            'destination_latitude' => 14.58,
            'destination_longitude' => 120.97,
            'current_latitude' => 14.58,
            'current_longitude' => 120.97,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(2),
        ]);
        DB::table('vessel_position_logs')->insert([
            'vessel_id' => $vesselId,
            'voyage_id' => $completedVoyageId,
            'latitude' => 14.58,
            'longitude' => 120.97,
            'location_name' => 'Manila Port',
            'source' => 'voyage_completion',
            'recorded_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin)
            ->get(route('division.dashboard', 'Villa shipping Lines'))
            ->assertOk()
            ->assertSee('Voyage Tracking Map')
            ->assertSee('Active Voyages')
            ->assertSee('Previous Voyages')
            ->assertSee('Hide details')
            ->assertSee('Full screen')
            ->assertSee('refreshes every 15 seconds')
            ->assertSee('Voyage details')
            ->assertSee('Focus this voyage')
            ->assertSee('Priority action list')
            ->assertViewHas('liveOpenVoyages', 1)
            ->assertViewHas('liveSailingVoyages', 1)
            ->assertViewHas('delayedVoyages', 1)
            ->assertViewHas('staleLocationCount', 1)
            ->assertViewHas('activeVoyageMapPoints', fn ($points) => $points->count() === 1
                && $points->first()['vessel'] === 'MV Executive Map')
            ->assertViewHas('dashboardVoyageTracks', fn ($tracks) => $tracks->count() === 2
                && $tracks->where('completed', false)->count() === 1
                && $tracks->where('completed', true)->count() === 1);

        $liveResponse = $this->actingAs($admin)
            ->getJson(route('division.dashboard.live-fleet', 'Villa shipping Lines'))
            ->assertOk()
            ->assertJsonCount(2, 'tracks')
            ->assertJsonPath('tracks.0.id', $completedVoyageId)
            ->assertJsonPath('tracks.1.id', $voyageId)
            ->assertJsonStructure(['tracks' => [['id', 'vessel', 'status', 'completed', 'current', 'positions', 'last_update']], 'updated_at']);
        $this->assertStringContainsString('no-store', (string) $liveResponse->headers->get('Cache-Control'));
    }

    private function seedCompanies(): array
    {
        return collect(['Yatira', 'Villa shipping Lines', 'JMV', 'Villa Group', 'HYVE'])
            ->mapWithKeys(fn (string $name) => [$name => DB::table('divisions')->insertGetId([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ])])
            ->all();
    }

    private function user(array $overrides = []): User
    {
        static $number = 0;
        $number++;
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Navigation Department '.$number,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::create(array_merge([
            'name' => 'Navigation',
            'lastname' => 'Tester',
            'username' => 'navigation-user-'.$number,
            'email' => 'navigation-user-'.$number.'@example.test',
            'password' => Hash::make('password-for-tests'),
            'is_admin' => false,
            'role' => 'staff',
            'department_id' => $departmentId,
            'division_id' => null,
            'must_change_password' => false,
            'status' => true,
        ], $overrides));
    }
}
