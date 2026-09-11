@extends('layouts.app')

@section('content')
<section class="min-h-[calc(100svh-74px)] bg-slate-50 px-4 py-6 sm:px-7 lg:px-12">
<div class="card-box mx-auto max-w-2xl">
    <h3 class="mb-3">Change Password</h3>

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ url('/change-password') }}">
        @csrf

        <input type="password" name="password" minlength="12" placeholder="New Password (minimum 12 characters)" class="form-control mb-3" required>
        <input type="password" name="password_confirmation" minlength="12" placeholder="Retype Password" class="form-control mb-3" required>

        <button class="btn btn-primary w-100">Update Password</button>
    </form>
</div>
</section>
@endsection
