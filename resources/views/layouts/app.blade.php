<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="amcs-poll" content="{{ config('amcs.realtime.poll_seconds') }}">
    <meta name="amcs-api-base" content="{{ url('api/v1') }}">
    <title>@yield('title', 'AMCS') — {{ config('amcs.airport.icao') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/amcs.css') }}">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%231e40af'/><text x='50' y='65' font-size='48' text-anchor='middle' fill='%23f59e0b'>A</text></svg>">
</head>
<body class="@yield('body-class')">
    @include('partials.topbar')

    <main class="page">
        @yield('content')
    </main>

    <div id="toasts"></div>

    <script src="{{ asset('js/amcs-core.js') }}"></script>
    <script>
        AMCS.startClock('liveClock');
        AMCS.initConnection = function () {
            const box = document.getElementById('connStatus');
            const text = document.getElementById('connText');
            if (!box) return;
            return {
                online() { box.className = 'connection online'; text.innerText = 'Tersambung'; },
                offline() { box.className = 'connection offline'; text.innerText = 'Terputus'; },
            };
        };
    </script>
    @stack('scripts')
</body>
</html>
