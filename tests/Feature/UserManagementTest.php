<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_cannot_access_user_management_even_with_legacy_admin_flag(): void
    {
        [$division, $department] = $this->assignment('Owner');
        $owner = $this->user($division, $department, ['role' => 'owner', 'is_admin' => true]);

        $this->actingAs($owner)->get(route('users.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($owner)->post(route('users.store'), [])->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_create_user_with_creator_and_forced_password_change(): void
    {
        [$division, $department] = $this->assignment('Shipping');
        $admin = $this->user($division, $department, ['role' => 'admin', 'is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New', 'lastname' => 'Employee', 'username' => 'new.employee',
            'email' => 'new.employee@example.test', 'cell_number' => '09171234567',
            'division_id' => $division, 'department_id' => $department,
            'role' => 'staff',
        ]);

        $response->assertRedirect('/users')->assertSessionHas('temporary_password', 'villa@2026');
        $this->assertDatabaseHas('users', [
            'username' => 'new.employee', 'created_by' => $admin->id,
            'must_change_password' => true, 'status' => true,
        ]);
        $this->assertTrue(Hash::check('villa@2026', User::where('username', 'new.employee')->value('password')));
    }

    public function test_department_must_belong_to_selected_division(): void
    {
        [$divisionA, $departmentA] = $this->assignment('A');
        [$divisionB] = $this->assignment('B');
        $admin = $this->user($divisionA, $departmentA, ['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Wrong', 'lastname' => 'Assignment', 'username' => 'wrong.assignment',
            'email' => 'wrong@example.test', 'cell_number' => '09000000000',
            'division_id' => $divisionB, 'department_id' => $departmentA, 'role' => 'staff',
        ])->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('users', ['username' => 'wrong.assignment']);
    }

    public function test_admin_cannot_deactivate_or_demote_self(): void
    {
        [$division, $department] = $this->assignment('Admin');
        $admin = $this->user($division, $department, ['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($admin)->post(route('users.destroy', $admin))->assertSessionHasErrors('user');
        $this->actingAs($admin)->post(route('users.update', $admin), $this->updatePayload($admin, [
            'role' => 'staff', 'is_admin' => null,
        ]))->assertSessionHasErrors('status');

        $admin->refresh();
        $this->assertTrue($admin->status);
        $this->assertTrue($admin->canManageUsers());
    }

    public function test_successful_update_stays_on_the_edit_page(): void
    {
        [$division, $department] = $this->assignment('Update');
        $admin = $this->user($division, $department, ['role' => 'admin', 'is_admin' => true]);
        $employee = $this->user($division, $department);

        $response = $this->actingAs($admin)->post(
            route('users.update', $employee),
            $this->updatePayload($employee, ['name' => 'Updated'])
        );

        $response->assertRedirect(route('users.edit', $employee))
            ->assertSessionHas('success', 'User updated successfully.');
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'name' => 'Updated']);
    }

    public function test_deactivation_preserves_user_and_blocks_existing_authenticated_session(): void
    {
        [$division, $department] = $this->assignment('Operations');
        $admin = $this->user($division, $department, ['role' => 'admin', 'is_admin' => true]);
        $employee = $this->user($division, $department);

        $this->actingAs($admin)->post(route('users.destroy', $employee))->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'status' => false]);

        $this->actingAs($employee->fresh())->get(route('companies'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
        $this->assertGuest();
    }

    private function assignment(string $suffix): array
    {
        $division = DB::table('divisions')->insertGetId(['name' => 'Division '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
        $department = DB::table('departments')->insertGetId(['name' => 'Department '.$suffix, 'division_id' => $division, 'created_at' => now(), 'updated_at' => now()]);

        return [$division, $department];
    }

    private function user(int $division, int $department, array $overrides = []): User
    {
        static $count = 0;
        $count++;

        return User::create(array_merge([
            'name' => 'Test', 'lastname' => 'User', 'username' => 'managed-user-'.$count,
            'email' => 'managed-user-'.$count.'@example.test', 'cell_number' => '09170000000',
            'password' => Hash::make('password-for-tests'), 'role' => 'staff', 'is_admin' => false,
            'department_id' => $department, 'division_id' => $division,
            'must_change_password' => false, 'status' => true,
        ], $overrides));
    }

    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name, 'lastname' => $user->lastname, 'username' => $user->username,
            'email' => $user->email, 'cell_number' => $user->cell_number,
            'division_id' => $user->division_id, 'department_id' => $user->department_id,
            'role' => $user->role, 'status' => 1, 'is_admin' => $user->is_admin ? 1 : null,
        ], $overrides);
    }
}
