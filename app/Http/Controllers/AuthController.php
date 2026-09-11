<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);

        $throttleKey = Str::lower($credentials['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()->withErrors([
                'username' => 'Too many login attempts. Please try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ])->onlyInput('username');
        }

        $user = User::where('username', $credentials['username'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return back()->with('error', 'Invalid username or password.');
        }

        if ((int) $user->status === 0) {
            RateLimiter::clear($throttleKey);

            return back()->with('error', 'Your account is inactive. Please contact the administrator.');
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ((int) $user->must_change_password === 1) {
            return redirect('/change-password');
        }

        return redirect()->route($user->isAdmin() ? 'dashboard' : 'companies');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'password' => 'required|string|min:12|confirmed',
        ]);

        $user = Auth::user();
        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => 0,
        ]);

        return redirect()->route($user->isAdmin() ? 'dashboard' : 'companies')
            ->with('success', 'Password updated successfully.');
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.Auth::id(),
            'cell_number' => 'nullable|string|max:30',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $user = Auth::user();
        $oldAvatar = $user->avatar_path;
        unset($data['avatar']);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'local');
        }

        try {
            $user->update($data);
        } catch (\Throwable $exception) {
            if (isset($data['avatar_path'])) {
                Storage::disk('local')->delete($data['avatar_path']);
            }

            throw $exception;
        }

        if (isset($data['avatar_path']) && $oldAvatar) {
            Storage::disk('local')->delete($oldAvatar);
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    public function avatar(): BinaryFileResponse
    {
        $path = (string) Auth::user()->avatar_path;

        abort_unless(str_starts_with($path, 'avatars/') && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
