@php($user = auth()->user())
<div class="topbar">
    <div class="brand">
        @include('partials.logo')
        <div>
            <h1>Apron Movement Control System</h1>
            <div class="sub">{{ config('amcs.airport.name') }}</div>
        </div>
    </div>

    <nav class="nav">
        @can('stands.view')
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard AMC</a>
        @endcan
        @can('stands.view')
            <a href="{{ route('atc') }}" class="{{ request()->routeIs('atc') ? 'active' : '' }}">Monitor ATC</a>
        @endcan
        @can('reports.view')
            <a href="{{ route('reports') }}" class="{{ request()->routeIs('reports') ? 'active' : '' }}">Laporan</a>
        @endcan
        @can('master-data.manage')
            <a href="{{ route('admin.stands') }}" class="{{ request()->routeIs('admin.stands') || request()->routeIs('admin.aircraft-types') || request()->routeIs('admin.airlines') ? 'active' : '' }}">Master Data</a>
        @endcan
        @can('users.manage')
            <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'active' : '' }}">Pengguna</a>
        @endcan
        @can('audit.view')
            <a href="{{ route('admin.audit-logs') }}" class="{{ request()->routeIs('admin.audit-logs') ? 'active' : '' }}">Audit Trail</a>
        @endcan
    </nav>

    <div class="topbar-right">
        <span class="connection offline" id="connStatus" title="Status koneksi API">
            <span class="dot"></span><span id="connText">Menghubungkan</span>
        </span>
        <div class="clock" id="liveClock">--</div>
        <div class="userbox">
            <div class="who">
                <b>{{ $user->name }}</b>
                <span>{{ config('amcs.roles.' . $user->primaryRole(), $user->primaryRole()) }}</span>
            </div>
            <div class="avatar">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm">Keluar</button>
            </form>
        </div>
    </div>
</div>
