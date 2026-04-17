<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->with('error', 'Invalid username or password.');
        }

        if ((int) $user->status === 0) {
            return back()->with('error', 'Your account is inactive. Please contact the administrator.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ((int) $user->must_change_password === 1) {
            return redirect('/change-password');
        }

        return redirect('/dashboard');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'password' => 'required|min:6|confirmed',
        ]);

        $user = Auth::user();
        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => 0,
        ]);

        return redirect('/dashboard')->with('success', 'Password updated successfully.');
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . Auth::id(),
        ]);

        Auth::user()->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }
}
