@extends('layouts.app')

@section('content')
<div class="card-box col-md-6">
    <h3>My Profile</h3>

    <form method="POST" action="{{ url('/profile') }}">
        @csrf

        <input type="text" name="name" value="{{ Auth::user()->name }}" class="form-control mb-2">
        <input type="text" name="lastname" value="{{ Auth::user()->lastname }}" class="form-control mb-2">
        <input type="email" name="email" value="{{ Auth::user()->email }}" class="form-control mb-2">

        <button class="btn btn-success">Update Profile</button>
    </form>

    <hr>

    <a href="{{ url('/change-password') }}" class="btn btn-warning">
        Change Password
    </a>
</div>
@endsection
