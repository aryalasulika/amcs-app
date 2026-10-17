<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — AMCS {{ config('amcs.airport.icao') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/amcs.css') }}">
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div style="width:74px;height:74px;margin:0 auto 14px;">
                @include('partials.logo')
            </div>
            <h1>Apron Movement Control System</h1>
            <div class="sub">{{ config('amcs.airport.name') }}</div>

            @if ($errors->any())
                <div class="alert alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert-info">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@instansi.go.id">
                </div>
                <div class="field">
                    <label for="password">Kata Sandi</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                </div>
                <div class="row" style="justify-content:space-between;margin:4px 0 16px;">
                    <label style="display:flex;align-items:center;gap:7px;font-size:12px;color:var(--text-sub);text-transform:none;letter-spacing:0;">
                        <input type="checkbox" name="remember" value="1" style="width:auto;">
                        Ingat saya
                    </label>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Masuk ke Sistem</button>
            </form>

            <div class="credentials">
                Akun contoh hasil seeder:<br>
                <code>admin@amcs.local</code> · <code>supervisor@amcs.local</code><br>
                <code>operator@amcs.local</code> · <code>atc@amcs.local</code><br>
                Kata sandi: <code>Amcs#2026</code>
            </div>
        </div>
    </div>
</body>
</html>
