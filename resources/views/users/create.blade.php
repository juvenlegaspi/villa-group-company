@extends('layouts.app')

@section('content')

<div class="container">

    <!-- 🔵 HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">👤 Create New User</h4>

        <a href="/users" class="btn btn-outline-secondary">
            ← Back to User List
        </a>
    </div>

    <!-- 🔴 ERROR ALERT -->
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>⚠️ Please fix the following:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 🟢 FORM CARD -->
    <form method="POST" action="/users">
        @csrf

        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <strong>User Information</strong>
            </div>

            <div class="card-body">

                <div class="row g-3">

                    <!-- NAME -->
                    <div class="col-md-4">
                        <label class="fw-bold">First Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">Last Name</label>
                        <input type="text" name="lastname" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>

                    <!-- EMAIL -->
                    <div class="col-md-4">
                        <label class="fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">Cell Number</label>
                        <input type="text" name="cell_number" class="form-control" required>
                    </div>

                    <!-- ROLE -->
                    <div class="col-md-4">
                        <label class="fw-bold">Role</label>
                        <select name="role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="it">IT</option>
                            <option value="manager">Manager</option>
                            <option value="staff">Captain</option>
                            <option value="staff">Staff</option>
                            <option value="r&d">R & D</option>
                            <option value="hr">HR</option>
                        </select>
                    </div>

                    <!-- DEPARTMENT -->
                    <div class="col-md-4">
                        <label class="fw-bold">Department</label>
                        <select name="department_id" class="form-control" required>
                            <option value="">Select Department</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!--  ADMIN CHECKBOX -->
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_admin" value="1">
                            <label class="form-check-label fw-bold">
                                Grant Admin Access
                            </label>
                        </div>
                    </div>

                </div>

            </div>

            <!-- FOOTER -->
            <div class="card-footer text-end">
                <button class="btn btn-success">
                    💾 Save User
                </button>
            </div>
        </div>

    </form>

</div>

@endsection