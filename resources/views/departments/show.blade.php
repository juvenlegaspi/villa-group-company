@extends('layouts.app')

@section('content')

<h3 class="mb-4">{{ $department->name }} Department</h3>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="row mb-4">

    <div class="col-md-4">
        <div class="card shadow-sm text-center p-3">
            <h6>Total Users</h6>
            <h3>{{ $totalUsers }}</h3>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm text-center p-3">
            <h6>Admins</h6>
            <h3>{{ $totalAdmins }}</h3>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm text-center p-3">
            <h6>Staff</h6>
            <h3>{{ $totalStaff }}</h3>
        </div>
    </div>

</div>
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }} {{ $user->lastname }}</td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ ucfirst($user->role) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">No users in this department</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection