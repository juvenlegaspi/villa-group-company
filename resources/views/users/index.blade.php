@extends('layouts.app')

@section('content')

<div class="d-flex justify-content-between mb-3">
    <h3>Users</h3>
    @if(auth()->user()->is_admin == 1)
        <a href="/users/create" class="btn btn-primary">
            <i class="bi bi-plus"></i> Add User
        </a>
    @endif
</div>
<form method="GET" action="{{ url('/users') }}" class="mb-3 d-flex gap-2">
    <input type="text" name="search" value="{{ $search ?? '' }}"
           class="form-control"
           placeholder="Search name, username, email...">
    <button class="btn btn-primary">
        <i class="bi bi-search"></i>
    </button>
</form>
<div class="card-box">
    <table class="table">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Username</th>
            <th>Department</th>
            <th>Role</th>
            <th>Cell Number</th>
            <th>Email</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        @foreach($users as $user)
        <tr>
            <td>{{ $user->id }}</td>
            <td>{{ $user->name }} {{ $user->lastname }}</td>
            <td>{{ $user->username }}</td>
            <td>{{ optional($user->department)->name ?? 'NO DEPT' }}</td>
            <td>{{ $user->role}}</td>
            <td>{{ $user->cell_number }}</td>
            <td>{{ $user->email }}</td>
            <td>
                <span class="badge {{ $user->status ? 'bg-success' : 'bg-danger' }}">
                    {{ $user->status ? 'Active' : 'Inactive' }}
                </span>
            </td>
            <td>
                {{-- EDIT BUTTON --}}
                @if(auth()->user()->is_admin == 1 || auth()->user()->id == $user->id)
                    <a href="/users/{{ $user->id }}/edit" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil"></i>
                    </a>
                @endif
                {{-- DELETE BUTTON (ADMIN ONLY) --}}
                @if(auth()->user()->is_admin == 1)
                    <form method="POST" action="/users/{{ $user->id }}/delete" style="display:inline;" class="delete-form">
                        @csrf
                        <button type="button" class="btn btn-danger btn-sm btn-delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                @endif
                {{-- RESET PASSWORD (ADMIN ONLY) --}}
                @if(auth()->user()->is_admin == 1)
                    <form method="POST" action="/users/{{ $user->id }}/reset-password" style="display:inline;" class="reset-form">
                        @csrf
                        <button type="button" class="btn btn-secondary btn-sm btn-reset">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </form>
                @endif
            </td>
        </tr>
        @endforeach
    </table>
    {{ $users->links() }}
</div>
<script>
document.querySelectorAll('.btn-delete').forEach(button => {
    button.addEventListener('click', function () {
        let form = this.closest('.delete-form');

        Swal.fire({
            title: 'Are you sure?',
            text: "This user will be deleted!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});

document.querySelectorAll('.btn-reset').forEach(button => {
    button.addEventListener('click', function () {
        let form = this.closest('.reset-form');

        Swal.fire({
            title: 'Reset Password?',
            text: "Password will be reset to default!",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, reset it!'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>
@endsection