<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_and_upload_a_private_avatar(): void
    {
        Storage::fake('local');
        $user = $this->user();

        $response = $this->actingAs($user)->post(route('profile.update'), [
            'name' => 'Updated',
            'lastname' => 'Profile',
            'email' => 'updated.profile@example.test',
            'cell_number' => '09171234567',
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 320, 320)->size(250),
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $user->refresh();

        $this->assertSame('Updated', $user->name);
        $this->assertSame('09171234567', $user->cell_number);
        $this->assertNotNull($user->avatar_path);
        Storage::disk('local')->assertExists($user->avatar_path);
        $this->actingAs($user)->get(route('profile.avatar'))->assertOk();
    }

    public function test_avatar_requires_authentication(): void
    {
        $this->get(route('profile.avatar'))->assertRedirect(route('login'));
    }

    private function user(): User
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Profile Department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::create([
            'name' => 'Profile',
            'lastname' => 'Tester',
            'username' => 'profile-tester',
            'email' => 'profile@example.test',
            'cell_number' => null,
            'password' => Hash::make('password-for-tests'),
            'role' => 'staff',
            'department_id' => $departmentId,
            'must_change_password' => false,
            'status' => true,
        ]);
    }
}
