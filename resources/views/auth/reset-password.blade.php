@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
    <h2 class="auth-heading">Create a new password</h2>
    <p class="auth-copy">Use at least 12 characters. Choose a password you do not use on another account.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label class="field-label" for="email">Email address</label>
            <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                <input class="form-input @error('email') is-invalid @enderror" id="email" name="email" type="email"
                       value="{{ old('email', $email) }}" autocomplete="email" maxlength="255" required>
            </div>
            @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="field-label" for="password">New password</label>
            <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <input class="form-input @error('password') is-invalid @enderror" id="password" name="password" type="password"
                       placeholder="At least 12 characters" autocomplete="new-password" minlength="12" required>
                <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                    <svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.7"/></svg>
                    <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A11.5 11.5 0 0 1 12 6c6.5 0 10 6 10 6a18 18 0 0 1-2.1 2.8M6.4 6.4C3.5 8.2 2 12 2 12s3.5 6 10 6a10.7 10.7 0 0 0 4-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                </button>
            </div>
            @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="field-label" for="password_confirmation">Confirm new password</label>
            <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <input class="form-input" id="password_confirmation" name="password_confirmation" type="password"
                       placeholder="Repeat new password" autocomplete="new-password" minlength="12" required>
                <button class="password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">
                    <svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.7"/></svg>
                    <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A11.5 11.5 0 0 1 12 6c6.5 0 10 6 10 6s-3.5 6-10 6a10.7 10.7 0 0 1-4-.8M6.4 6.4C3.5 8.2 2 12 2 12s3.5 6 10 6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                </button>
            </div>
        </div>

        <button class="primary-button" type="submit">Reset password</button>
    </form>

    <div class="back-row"><a class="auth-link" href="{{ route('login') }}">Back to login</a></div>
@endsection
