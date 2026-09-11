<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_forgot_password_pages_render(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('VILLA GROUP')
            ->assertSee('Forgot password?');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Send reset link');
    }

    public function test_active_user_can_request_and_complete_a_password_reset(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $token = Password::createToken($user);
        $newPassword = 'a-new-secure-password';

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse((bool) $user->must_change_password);
    }

    public function test_forgot_password_response_does_not_disclose_unknown_email(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_inactive_user_does_not_receive_a_reset_link(): void
    {
        Notification::fake();
        $user = $this->user(['status' => false]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_remember_me_login_creates_a_persistent_login_token(): void
    {
        $user = $this->user();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password-for-tests',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('companies'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    private function user(array $overrides = []): User
    {
        static $number = 0;
        $number++;
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Password Test Department '.$number,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::create(array_merge([
            'name' => 'Password',
            'lastname' => 'Tester',
            'username' => 'password-user-'.$number,
            'email' => 'password-user-'.$number.'@example.test',
            'cell_number' => '09000000000',
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
