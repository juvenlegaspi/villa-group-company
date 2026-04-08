<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;
        $users = \App\Models\User::when($search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('lastname', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        })->paginate(10);
        return view('users.index', compact('users', 'search'));
    }
    public function create()
    {
        if (!auth()->user()->is_admin) {
            abort(403);
        }

        $departments = \App\Models\Department::all();
        return view('users.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'lastname' => 'required',
            'username' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'cell_number' => 'required',
            'department_id' => 'required|exists:departments,id',
            'role' => 'required|in:admin,manager,staff',
        ]);

        User::create([
            'name' => $request->name,
            'lastname' => $request->lastname,
            'username' => $request->username,
            'email' => $request->email,
            'cell_number' => $request->cell_number,
            'password' => Hash::make('villa@2026'),
            'role' => $request->role,
            'department_id' => $request->department_id,
            'is_admin' => $request->has('is_admin') ? 1 : 0,
            'must_change_password' => 1
        ]);
        return redirect('/users')->with('success', 'User created successfully');
    }
    // edit form
    public function edit($id)
    {
        $user = User::findOrFail($id);

        if (!auth()->user()->is_admin && auth()->id() != $id) {
            abort(403);
        }
        $departments = \App\Models\Department::all();
        return view('users.edit', compact('user', 'departments'));
    }

    // update user
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        //  SECURITY CHECK
        if (!auth()->user()->is_admin && auth()->id() != $id) {
            abort(403);
        }
        $request->validate([
            'name' => 'required',
            'lastname' => 'required',
            'username' => 'required|unique:users,username,' . $id,
            'email' => 'required|email|unique:users,email,' . $id,
            'cell_number' => 'required',
        ]);
        // BASIC UPDATE (ALL USERS)
        $user->name = $request->name;
        $user->lastname = $request->lastname;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->cell_number = $request->cell_number;
        //  ADMIN ONLY
        if (auth()->user()->is_admin) {
            $user->status = $request->status;
            $user->role = $request->role;
            $user->department_id = $request->department_id;
            $user->is_admin = $request->has('is_admin') ? 1 : 0;
        }
        $user->save();
        return redirect('/users')->with('success', 'User updated successfully');
    }
    // delete user
    public function destroy($id)
    {
        User::findOrFail($id)->delete();
        return redirect('/users');
    }
    public function resetPassword($id)
    {
        // only admin
        if (!auth()->user()->is_admin) {
         abort(403);
        }
        $user = \App\Models\User::findOrFail($id);
        $user->password = Hash::make('villa@2026');
        $user->must_change_password = 1; // force change
        $user->save();
        return back()->with('success', 'Password reset to default!');
    }
    public function changePassword(Request $request, $id)
{
    $user = User::findOrFail($id);

    if (auth()->user()->is_admin != 1) {
        abort(403);
    }

    $request->validate([
        'password' => 'required|confirmed|min:6'
    ]);

    $user->password = bcrypt($request->password);
    $user->must_change_password = 0;
    $user->save();

    return back()->with('success', 'Password updated successfully');
}
}