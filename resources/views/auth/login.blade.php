<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#050912">
    <title>Masuk — AMCS {{ config('amcs.airport.icao') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/amcs.css') }}?v={{ filemtime(public_path('css/amcs.css')) }}">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-visual" aria-hidden="true">
            <div class="login-visual-content">
                <div class="login-brand">
                    <div class="login-brand-logo">@include('partials.logo')</div>
                    <div class="login-brand-text">
                        <b>AMCS</b>
                        <span>Apron Movement Control System</span>
                    </div>
                </div>

                <div class="login-eyebrow"><span class="dot"></span> Operasional Apron Bandara</div>

                <h2>Kendali pergerakan apron yang cepat, akurat, dan tercatat.</h2>
                <p>
                    Pantau parking stand, status penerbangan, dan histori pergerakan
                    pesawat dalam satu platform operasional yang terhubung secara realtime.
                </p>

                <div class="login-features">
                    <div class="login-feature">
                        <span class="login-feature-icon">✈</span>
                        <div>
                            <b>Papan stand realtime</b>
                            <small>Status ENTERING, ON, DELAY, RON, dan READY.</small>
                        </div>
                    </div>
                    <div class="login-feature">
                        <span class="login-feature-icon">◎</span>
                        <div>
                            <b>Validasi alokasi</b>
                            <small>Kapasitas pesawat dan konflik stand diperiksa sistem.</small>
                        </div>
                    </div>
                    <div class="login-feature">
                        <span class="login-feature-icon">✓</span>
                        <div>
                            <b>Audit trail</b>
                            <small>Setiap perubahan tercatat dengan pengguna dan waktu.</small>
                        </div>
                    </div>
                </div>

                <div class="login-airport">
                    <span class="login-airport-code">{{ config('amcs.airport.icao') }}</span>
                    <span class="login-airport-name">{{ config('amcs.airport.name') }}</span>
                </div>
            </div>

            <div class="login-radar">
                <span class="radar-ring ring-1"></span>
                <span class="radar-ring ring-2"></span>
                <span class="radar-ring ring-3"></span>
                <span class="radar-line line-x"></span>
                <span class="radar-line line-y"></span>
                <span class="radar-sweep"></span>
                <span class="radar-blip blip-1"></span>
                <span class="radar-blip blip-2"></span>
                <span class="radar-blip blip-3"></span>
                <span class="radar-plane">✈</span>
                <span class="radar-code">{{ config('amcs.airport.icao') }}</span>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-card">
                <div class="login-card-brand">
                    <div class="login-card-logo">@include('partials.logo')</div>
                    <div class="login-brand-text">
                        <b>AMCS</b>
                        <span>{{ config('amcs.airport.icao') }} · Apron Movement Control</span>
                    </div>
                </div>

                <div class="login-card-head">
                    <span class="login-card-eyebrow">Akses Operasional</span>
                    <h1>Masuk ke akun Anda</h1>
                    <p>Gunakan akun yang diberikan administrator untuk mengakses sistem.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-info">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="loginForm">
                    @csrf

                    <div class="field login-field">
                        <label for="email">Email</label>
                        <div class="login-input">
                            <svg class="login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2.5"/>
                                <path d="m4 7 8 6 8-6"/>
                            </svg>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="Masukkan email Anda"
                                required
                                autofocus
                                autocomplete="username"
                                autocapitalize="none"
                                spellcheck="false"
                                inputmode="email"
                            >
                        </div>
                    </div>

                    <div class="field login-field">
                        <label for="password">Kata Sandi</label>
                        <div class="login-input">
                            <svg class="login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <rect x="4" y="10" width="16" height="10" rx="2.5"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                <circle cx="12" cy="15" r="1.2" fill="currentColor" stroke="none"/>
                            </svg>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                placeholder="Masukkan kata sandi Anda"
                                required
                                autocomplete="current-password"
                            >
                            <button
                                type="button"
                                class="login-toggle"
                                id="togglePassword"
                                aria-label="Tampilkan kata sandi"
                                title="Tampilkan kata sandi"
                            >
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M3 3l18 18"/>
                                    <path d="M10.6 6.2A9.8 9.8 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-3.2 3.7"/>
                                    <path d="M6.3 6.3A17 17 0 0 0 2.5 12s3.5 6 9.5 6a9.9 9.9 0 0 0 4.2-.9"/>
                                    <path d="M9.9 9.9A3 3 0 0 0 14.1 14"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="login-options">
                        <label class="login-remember">
                            <input type="checkbox" name="remember" value="1">
                            <span>Ingat saya</span>
                        </label>
                        <span class="login-secure">Sesi aman</span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block login-submit" id="loginSubmit">
                        <span class="login-submit-label">Masuk ke Sistem</span>
                        <span class="login-spinner" aria-hidden="true"></span>
                    </button>
                </form>

                <div class="login-help">
                    Butuh akses atau mengalami kendala? Hubungi administrator sistem.
                </div>
            </div>

            <p class="login-copyright">
                © {{ date('Y') }} {{ config('amcs.airport.name') }} · {{ config('amcs.airport.icao') }}
            </p>
        </section>
    </main>

    <script>
        (function () {
            const password = document.getElementById('password');
            const toggle = document.getElementById('togglePassword');
            const form = document.getElementById('loginForm');
            const submit = document.getElementById('loginSubmit');
            const submitLabel = submit.querySelector('.login-submit-label');

            toggle.addEventListener('click', function () {
                const visible = password.type === 'text';
                password.type = visible ? 'password' : 'text';
                toggle.classList.toggle('is-visible', !visible);
                toggle.setAttribute('aria-label', visible ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
                toggle.setAttribute('title', visible ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
                password.focus();
            });

            form.addEventListener('submit', function () {
                submit.disabled = true;
                submit.classList.add('is-loading');
                submitLabel.textContent = 'Memverifikasi...';
            });
        })();
    </script>
</body>
</html>
