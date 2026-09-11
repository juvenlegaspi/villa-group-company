@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error" role="alert">{{ session('error') }}</div>
    @endif

    <form class="auth-form" method="POST" action="{{ route('login') }}">
        @csrf

        <div class="field">
            <label class="field-label" for="username">Username</label>
            <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>
                </svg>
                <input class="form-input @error('username') is-invalid @enderror" id="username" name="username" type="text"
                       value="{{ old('username') }}" placeholder="Username" autocomplete="username" maxlength="255" autofocus required>
            </div>
            @error('username')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="field-label" for="password">Password</label>
            <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                </svg>
                <input class="form-input @error('password') is-invalid @enderror" id="password" name="password" type="password"
                       placeholder="Password" autocomplete="current-password" required>
                <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                    <svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.7"/></svg>
                    <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A11.5 11.5 0 0 1 12 6c6.5 0 10 6 10 6a18 18 0 0 1-2.1 2.8M6.4 6.4C3.5 8.2 2 12 2 12s3.5 6 10 6a10.7 10.7 0 0 0 4-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                </button>
            </div>
            @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-options">
            <label class="check-label" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                <span>Remember me</span>
            </label>
            <a class="auth-link" href="{{ route('password.request') }}">Forgot password?</a>
        </div>

        <button class="primary-button" type="submit">Login</button>
    </form>
@endsection
