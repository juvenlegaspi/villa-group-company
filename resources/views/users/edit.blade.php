@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center mb-3">
    <a href="{{ url('/users') }}" class="btn btn-outline-secondary me-2">Back</a>
    <h3 class="mb-0">Edit User</h3>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ url('/users/' . $user->id . '/update') }}">
    @csrf

    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
            <strong>User Information</strong>
        </div>

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="fw-bold">First Name</label>
                    <input type="text" name="name" value="{{ $user->name }}" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="fw-bold">Last Name</label>
                    <input type="text" name="lastname" value="{{ $user->lastname }}" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="fw-bold">Username</label>
                    <input type="text" name="username" value="{{ $user->username }}" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="fw-bold">Cell Number</label>
                    <input type="text" name="cell_number" value="{{ $user->cell_number }}" class="form-control" required>
                </div>

                <div class="col-md-12">
                    <label class="fw-bold">Email Address</label>
                    <input type="email" name="email" value="{{ $user->email }}" class="form-control" required>
                </div>

                @if(auth()->user()->is_admin == 1)
                    <div class="col-md-4">
                        <label class="fw-bold">Status</label>
                        <select name="status" class="form-control">
                            <option value="1" {{ $user->status == 1 ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ $user->status == 0 ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">Role</label>
                        <select name="role" class="form-control">
                            <option value="it" {{ $user->role == 'it' ? 'selected' : '' }}>IT</option>
                            <option value="manager" {{ $user->role == 'manager' ? 'selected' : '' }}>Manager</option>
                            <option value="captain" {{ $user->role == 'captain' ? 'selected' : '' }}>Captain</option>
                            <option value="staff" {{ $user->role == 'staff' ? 'selected' : '' }}>Staff</option>
                            <option value="r&d" {{ $user->role == 'r&d' ? 'selected' : '' }}>R & D</option>
                            <option value="hr" {{ $user->role == 'hr' ? 'selected' : '' }}>HR</option>
                            <option value="owner" {{ $user->role == 'owner' ? 'selected' : '' }}>Owner</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">Department</label>
                        <select name="department_id" class="form-control">
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ $user->department_id == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin" value="1" {{ $user->is_admin ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_admin">Grant Admin Access</label>
                    </div>
                @endif

                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    Change Password
                </button>
            </div>
        </div>

        <div class="card-footer text-end">
            <button class="btn btn-success px-4">Update User</button>
        </div>
    </div>
</form>

<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ url('/users/' . $user->id . '/change-password') }}">
                @csrf

                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="fw-bold">New Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
