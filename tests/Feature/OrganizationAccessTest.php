<?php

namespace Tests\Feature;

use App\Models\AccessRole;
use App\Models\Department;
use App\Models\Division;
use App\Models\Position;
use App\Models\TechDefect;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_separates_company_department_position_and_access_profile(): void
    {
        [$company, $department] = $this->organization('Villa shipping Lines');
        $position = Position::create(['division_id' => $company->id, 'department_id' => $department->id, 'name' => 'Chief Engineer', 'code' => 'chief-engineer', 'legacy_role' => 'staff']);
        $position->permissions()->syncWithoutDetaching(\App\Models\Permission::where('slug', 'shipping.technical_defects.access')->pluck('id'));
        $admin = $this->user($company, $department, ['role' => 'admin', 'is_admin' => true]);
        $standard = AccessRole::where('slug', 'standard-user')->firstOrFail();

        $this->actingAs($admin)->get(route('users.create'))->assertOk()
            ->assertSee('name="position_id"', false)->assertSee('name="access_role_id"', false)->assertSee('Approval Authority');

        $this->actingAs($admin)->post(route('users.store'), $this->payload($company, $department, $position, $standard))->assertRedirect('/users');
        $created = User::where('username', 'organized-user')->firstOrFail();
        $this->assertSame('staff', $created->role);
        $this->assertFalse($created->is_admin);
        $this->assertSame($position->id, $created->position_id);
        $this->assertSame($standard->id, $created->access_role_id);
        $this->assertDatabaseHas('company_user_assignments', ['user_id' => $created->id, 'division_id' => $company->id, 'is_primary' => 1]);
    }

    public function test_company_approver_requires_and_records_an_explicit_approval_module(): void
    {
        [$company, $department] = $this->organization('Villa shipping Lines');
        $position = Position::create(['division_id' => $company->id, 'department_id' => $department->id, 'name' => 'Technical Manager', 'code' => 'technical-manager', 'legacy_role' => 'manager']);
        $admin = $this->user($company, $department, ['role' => 'admin', 'is_admin' => true]);
        $approver = AccessRole::where('slug', 'company-approver')->firstOrFail();

        $this->actingAs($admin)->post(route('users.store'), $this->payload($company, $department, $position, $approver))->assertSessionHasErrors('approval_modules');
        $this->actingAs($admin)->post(route('users.store'), $this->payload($company, $department, $position, $approver, ['approval_modules' => ['technical_defects']]))->assertRedirect('/users');
        $created = User::where('username', 'organized-user')->firstOrFail();
        $this->assertSame('manager', $created->role);
        $this->assertDatabaseHas('approval_authorities', ['user_id' => $created->id, 'division_id' => $company->id, 'module' => 'technical_defects', 'is_active' => 1]);
    }

    public function test_yatira_cannot_receive_shipping_approval_authority(): void
    {
        [$company, $department] = $this->organization('Yatira');
        $position = Position::create(['division_id' => $company->id, 'department_id' => $department->id, 'name' => 'Sales Manager', 'code' => 'sales-manager', 'legacy_role' => 'manager']);
        $admin = $this->user($company, $department, ['role' => 'admin', 'is_admin' => true]);
        $approver = AccessRole::where('slug', 'company-approver')->firstOrFail();

        $this->actingAs($admin)->post(route('users.store'), $this->payload($company, $department, $position, $approver, [
            'approval_modules' => ['technical_defects'],
        ]))->assertSessionHasErrors('approval_modules');

        $this->assertDatabaseMissing('users', ['username' => 'organized-user']);
        $this->assertDatabaseCount('approval_authorities', 0);
    }

    public function test_yatira_can_use_company_approver_profile_without_a_module_authority(): void
    {
        [$company, $department] = $this->organization('Yatira');
        $position = Position::create(['division_id' => $company->id, 'department_id' => $department->id, 'name' => 'Sales Manager', 'code' => 'sales-manager', 'legacy_role' => 'manager']);
        $admin = $this->user($company, $department, ['role' => 'admin', 'is_admin' => true]);
        $approver = AccessRole::where('slug', 'company-approver')->firstOrFail();

        $this->actingAs($admin)->post(route('users.store'), $this->payload($company, $department, $position, $approver))
            ->assertRedirect('/users');

        $created = User::where('username', 'organized-user')->firstOrFail();
        $this->assertSame($approver->id, $created->access_role_id);
        $this->assertDatabaseMissing('approval_authorities', ['user_id' => $created->id, 'is_active' => 1]);
    }

    public function test_approval_modules_require_company_approver_access_profile(): void
    {
        [$company, $department] = $this->organization('Villa shipping Lines');
        $position = Position::create(['division_id' => $company->id, 'department_id' => $department->id, 'name' => 'Technical Manager', 'code' => 'technical-manager', 'legacy_role' => 'manager']);
        $admin = $this->user($company, $department, ['role' => 'admin', 'is_admin' => true]);
        $standard = AccessRole::where('slug', 'standard-user')->firstOrFail();

        $this->actingAs($admin)->post(route('users.store'), $this->payload($company, $department, $position, $standard, [
            'approval_modules' => ['technical_defects'],
        ]))->assertSessionHasErrors('approval_modules');

        $this->assertDatabaseMissing('users', ['username' => 'organized-user']);
    }

    public function test_assigned_standard_user_can_work_on_a_defect_without_manager_verification_authority(): void
    {
        [$company, $department] = $this->organization('Villa shipping Lines');
        $position = Position::create(['division_id' => $company->id, 'department_id' => $department->id, 'name' => 'Chief Engineer', 'code' => 'chief-engineer', 'legacy_role' => 'staff']);
        $position->permissions()->syncWithoutDetaching(\App\Models\Permission::whereIn('slug', [
            'shipping.technical_defects.access',
            'tech_defects.view_assigned',
            'tech_defects.update_closeout',
        ])->pluck('id'));
        $standard = AccessRole::where('slug', 'standard-user')->firstOrFail();
        $captain = $this->user($company, $department, ['role' => 'captain']);
        $engineer = $this->user($company, $department, ['role' => 'staff', 'position_id' => $position->id, 'access_role_id' => $standard->id]);
        $vessel = Vessel::create(['vessel_name' => 'MV Access Test', 'captain_id' => $captain->id]);
        $report = TechDefect::create(['vessel_id' => $vessel->id, 'status' => 'Ongoing', 'progress_percent' => 100, 'date_identified' => today(), 'reported_by' => 'CAPTAIN', 'reported_by_user_id' => $captain->id, 'assigned_to_user_id' => $engineer->id, 'target_completion_date' => today()->addDays(3), 'system_affected' => 'Main Engine', 'defect_description' => 'Test defect', 'severity_level' => 'Major', 'operational_impact' => 'Limited', 'temporary_repair' => 'No']);

        $this->actingAs($engineer)->get(route('tech-defects.show', $report))->assertOk()->assertSee('Repair close-out');
        $this->actingAs($engineer)->put(route('tech-defects.update', $report), ['action' => 'update_closeout', 'root_cause' => 'Bearing wear', 'corrective_action' => 'Bearing replaced', 'preventive_action' => 'Scheduled inspection'])->assertRedirect(route('tech-defects.show', $report));
        $report->update(['status' => 'For Verification']);
        $this->actingAs($engineer)->put(route('tech-defects.update', $report), ['action' => 'verify_completion'])->assertForbidden();
        $this->actingAs($engineer)->delete(route('tech-defects.destroy', $report))->assertForbidden();
    }

    public function test_administrator_can_add_company_departments_and_positions(): void
    {
        [$company, $department] = $this->organization('Villa shipping Lines');
        $admin = $this->user($company, $department, ['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($admin)->get(route('users.organization.index'))->assertOk()->assertSee('Organization Setup');
        $this->actingAs($admin)->post(route('users.organization.departments.store'), ['division_id' => $company->id, 'name' => 'Safety and Compliance'])->assertRedirect();
        $newDepartment = Department::where('division_id', $company->id)->where('name', 'Safety and Compliance')->firstOrFail();
        $this->actingAs($admin)->post(route('users.organization.positions.store'), ['division_id' => $company->id, 'department_id' => $newDepartment->id, 'name' => 'Safety Officer', 'operational_category' => 'staff'])->assertRedirect();
        $createdPosition = Position::where(['division_id' => $company->id, 'department_id' => $newDepartment->id, 'name' => 'Safety Officer'])->firstOrFail();
        $this->assertTrue($createdPosition->permissions()->where('slug', 'shipping.technical_defects.access')->exists());
        $this->assertTrue($createdPosition->permissions()->where('slug', 'shipping.certificates.access')->exists());
    }

    private function organization(string $name): array
    {
        $company = Division::create(['name' => $name]);
        $department = Department::create(['name' => 'Technical Department', 'division_id' => $company->id]);

        return [$company, $department];
    }

    private function user(Division $company, Department $department, array $overrides = []): User
    {
        static $number = 0;
        $number++;

        return User::create(array_merge(['name' => 'User', 'lastname' => (string) $number, 'username' => 'org-user-'.$number, 'email' => 'org-user-'.$number.'@example.test', 'cell_number' => '09170000000', 'password' => Hash::make('password-for-tests'), 'role' => 'staff', 'is_admin' => false, 'division_id' => $company->id, 'department_id' => $department->id, 'must_change_password' => false, 'status' => true], $overrides));
    }

    private function payload(Division $company, Department $department, Position $position, AccessRole $role, array $overrides = []): array
    {
        return array_merge(['name' => 'Organized', 'lastname' => 'User', 'username' => 'organized-user', 'email' => 'organized@example.test', 'cell_number' => '09171234567', 'division_id' => $company->id, 'department_id' => $department->id, 'position_id' => $position->id, 'access_role_id' => $role->id], $overrides);
    }
}
