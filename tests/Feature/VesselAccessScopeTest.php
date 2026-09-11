<?php

namespace Tests\Feature;

use App\Models\AccessRole;
use App\Models\Department;
use App\Models\Division;
use App\Models\DryDockingHeader;
use App\Models\Position;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserVesselAssignment;
use App\Models\Vessel;
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
