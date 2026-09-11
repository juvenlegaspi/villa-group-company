<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') | Villa Group</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="auth-brand">
            <h1 class="brand" id="auth-brand">VILLA GROUP</h1>
            @yield('content')
        </section>
    </main>
</body>
</html>
