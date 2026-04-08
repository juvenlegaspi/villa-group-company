<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $user = \App\Models\User::where('username', $request->username)->first();
        if ($user && Hash::check($request->password, $user->password)) {
            //  BLOCK IF INACTIVE
            if ($user->status == 0) {
                return back()->with('error', 'Your account is inactive. Please contact admin.');
            }
            Auth::login($user);
            $request->session()->regenerate();
            // force change password
            if ($user->must_change_password == 1) {
                return redirect('/change-password');
            }
            return redirect('/dashboard');
        }
        return back()->with('error', 'Invalid username or password');
    }
    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:6|confirmed'
        ]);
        $user = Auth::user();
        $user->password = Hash::make($request->password);
        $user->must_change_password = 0; // ✅ remove restriction
        $user->save();
        return redirect('/dashboard')->with('success', 'Password updated!');
    }
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $user->name = $request->name;
        $user->lastname = $request->lastname;
        $user->email = $request->email;

        $user->save();

        return back();
    }
}
