@extends('layouts.app')

@section('content')

<div class="card-box col-md-6 mx-auto">
    <h3 class="mb-3">Change Password</h3>

    {{-- ERROR DISPLAY --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="/change-password">
        @csrf

        <input type="password" name="password" placeholder="New Password" class="form-control mb-3" required>

        <input type="password" name="password_confirmation" placeholder="Retype Password" class="form-control mb-3" required>

        <button class="btn btn-primary w-100">
            Update Password
        </button>
    </form>
</div>

@endsection