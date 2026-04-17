<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected const DEFAULT_PASSWORD = 'villa@2026';

    public function index(Request $request)
    {
        $search = $request->search;

        $users = User::query()
            ->with('department')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($userQuery) use ($searchTerm) {
                    $userQuery->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('lastname', 'like', "%{$searchTerm}%")
                        ->orWhere('username', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%");
                });
            })
            ->paginate(10)
            ->withQueryString();

        return view('users.index', compact('users', 'search'));
    }

    public function create()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $departments = Department::all();

        return view('users.create', compact('departments'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'cell_number' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'role' => 'required|in:it,manager,captain,staff,r&d,hr,owner',
        ]);

        User::create([
            ...$data,
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'is_admin' => $request->boolean('is_admin'),
            'must_change_password' => 1,
        ]);

        return redirect('/users')->with('success', 'User created successfully.');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        abort_unless(auth()->user()->isAdmin() || auth()->id() == $id, 403);

        $departments = Department::all();

        return view('users.edit', compact('user', 'departments'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        abort_unless(auth()->user()->isAdmin() || auth()->id() == $id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'cell_number' => 'required|string|max:255',
            'status' => 'nullable',
            'role' => 'nullable|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $user->fill([
            'name' => $data['name'],
            'lastname' => $data['lastname'],
            'username' => $data['username'],
            'email' => $data['email'],
            'cell_number' => $data['cell_number'],
        ]);

        if (auth()->user()->isAdmin()) {
            $user->fill([
                'status' => $request->status,
                'role' => $request->role,
                'department_id' => $request->department_id,
                'is_admin' => $request->boolean('is_admin'),
            ]);
        }

        $user->save();

        return redirect('/users')->with('success', 'User updated successfully.');
    }

    public function destroy($id)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        User::findOrFail($id)->delete();

        return redirect('/users')->with('success', 'User deleted successfully.');
    }

    public function resetPassword($id)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'must_change_password' => 1,
        ]);

        return back()->with('success', 'Password reset to default successfully.');
    }

    public function changePassword(Request $request, $id)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'password' => 'required|confirmed|min:6',
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => 0,
        ]);

        return back()->with('success', 'Password updated successfully.');
    }
}
