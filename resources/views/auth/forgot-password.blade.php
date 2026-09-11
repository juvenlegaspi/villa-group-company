@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    <h2 class="auth-heading">Forgot your password?</h2>
    <p class="auth-copy">Enter the email address registered to your account. We will send you a secure password reset link.</p>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="field">
            <label class="field-label" for="email">Email address</label>
            <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                <input class="form-input @error('email') is-invalid @enderror" id="email" name="email" type="email"
                       value="{{ old('email') }}" placeholder="name@company.com" autocomplete="email" maxlength="255" autofocus required>
            </div>
            @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <button class="primary-button" type="submit">Send reset link</button>
    </form>

    <div class="back-row"><a class="auth-link" href="{{ route('login') }}">Back to login</a></div>
@endsection
