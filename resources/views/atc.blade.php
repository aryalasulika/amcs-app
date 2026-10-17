@extends('layouts.app')

@section('title', 'Monitor ATC Tower View')
@section('body-class', 'atc')
@section('theme-color', '#02040a')

@section('content')
    <div class="atc-head">
        <div class="atc-head-main">
            <a href="{{ route('dashboard') }}" class="atc-back">‹ Dashboard AMC</a>
            <h1>ATC MOVEMENT MONITOR BOARD <span class="atc-tag">TOWER VIEW</span></h1>
            <p class="muted atc-sync-line">
                {{ config('amcs.airport.name') }} · sinkron: <span id="lastSync">-</span>
                · <span id="atcClock" class="atc-clock">--:--:--</span>
            </p>
        </div>
        <div class="atc-tools">
            <button type="button" class="btn btn-sm" id="atcRefresh" title="Muat ulang papan stand">↻ Refresh</button>
            <button type="button" class="btn btn-sm" id="atcFullscreen" title="Mode layar penuh">⛶ Fullscreen</button>
        </div>
    </div>

    <div class="atc-summary" id="atcSummary"></div>

    <div class="atc-grid" id="atcGrid"></div>
@endsection

@push('scripts')
<script>
(function () {
    const pollMs = (parseInt(document.querySelector('meta[name="amcs-poll"]').content, 10) || 3) * 1000;
    const fallbackPollMs = (parseInt(document.querySelector('meta[name="amcs-poll-fallback"]').content, 10) || 15) * 1000;
    const conn = AMCS.initConnection();
    let pollTimer = null;
    let realtimeConnected = false;

    function statusClass(state) {
        switch (state) {
            case 'VACANT': return 'atc-vacant';
            case 'ENTERING': return 'atc-app';
            case 'RON': return 'atc-ron';
            case 'DELAY': return 'atc-delay';
            default: return 'atc-occ';
        }
    }

    function card(stand) {
        const m = stand.movement;
        const state = stand.state;
        const flight = m ? m.flight_number : 'STANDBY';
        const flightColor = state === 'VACANT' ? '#10b981' : '#f59e0b';
        const routing = m
            ? AMCS.escapeHtml(m.runway || '-') + ' | ' + AMCS.escapeHtml(m.taxiway || '-')
            : '';

        return `<div class="atc-card state-${state.toLowerCase()} ${m ? '' : 'is-vacant'}">
            <div class="atc-card-head">
                <div class="atc-stand">
                    <div class="title">${AMCS.escapeHtml(stand.name)}</div>
                    ${stand.allowed_aircraft && stand.allowed_aircraft.length <= 4 ? `<div class="stand-limit">MAX: ${stand.allowed_aircraft.join('/')}</div>` : ''}
                </div>
                <span class="status ${statusClass(state)}">${AMCS.escapeHtml(stand.atc_label)}</span>
            </div>
            <div class="atc-card-body">
                <div class="flight" style="color:${flightColor}">${AMCS.escapeHtml(flight)}</div>
                <div class="ac">${m ? AMCS.escapeHtml(m.aircraft_type || 'NONE') : 'STANDBY'}</div>
                ${m ? `<div class="meta">${AMCS.escapeHtml(m.airline || '-')} · ${AMCS.escapeHtml(m.registration || '-')}</div>` : '<div class="meta">Stand siap digunakan</div>'}
                ${m ? `<div class="route">${AMCS.escapeHtml(m.route || '-')}</div>` : ''}
            </div>
            ${m ? `<div class="info">
                <div class="row"><span class="l">ETA</span><span class="v">${AMCS.escapeHtml(m.eta || '-')}</span></div>
                <div class="row"><span class="l">ATA</span><span class="v">${AMCS.escapeHtml(m.ata || '-')}</span></div>
                <div class="row"><span class="l">ETD</span><span class="v">${AMCS.escapeHtml(m.etd || '-')}</span></div>
                <div class="row"><span class="l">ATD</span><span class="v">${AMCS.escapeHtml(m.atd || '-')}</span></div>
                ${state === 'DELAY' ? `<div class="row is-delay"><span class="l">DELAY</span><span class="v" style="color:#f87171;">${m.delay_minutes}m ${AMCS.escapeHtml(m.delay_reason || '')}</span></div>` : ''}
            </div>
            <div class="routing">${routing}</div>` : ''}
        </div>`;
    }

    async function refresh() {
        try {
            const board = await AMCS.api('/stands');
            const counts = board.counts || {};

            document.getElementById('atcGrid').innerHTML = board.stands.map(card).join('');
            document.getElementById('atcSummary').innerHTML = `
                <span class="atc-summary-item is-active">Terisi <b>${counts.active || 0}</b></span>
                <span class="atc-summary-item is-delay">Delay <b>${counts.delay || 0}</b></span>
                <span class="atc-summary-item is-ron">RON <b>${counts.ron || 0}</b></span>
                <span class="atc-summary-item is-vacant">Kosong <b>${counts.vacant || 0}</b></span>
            `;
            document.getElementById('lastSync').innerText = AMCS.shortClock();
            conn.online(realtimeConnected ? 'Realtime' : 'Polling');
        } catch (error) {
            conn.offline('API terputus');
        }
    }

    function setPollInterval(ms) {
        clearInterval(pollTimer);
        pollTimer = setInterval(refresh, ms);
    }

    const realtimeStarted = window.AMCSRealtime && AMCSRealtime.connect(
        () => refresh(),
        (online) => {
            realtimeConnected = online;
            if (online) {
                conn.online('Realtime');
            } else {
                conn.online('Polling');
            }
            setPollInterval(online ? fallbackPollMs : pollMs);
        },
    );

    setPollInterval(realtimeStarted ? fallbackPollMs : pollMs);

    AMCS.startClock('atcClock', AMCS.shortClock);

    const refreshButton = document.getElementById('atcRefresh');
    refreshButton.addEventListener('click', async () => {
        refreshButton.disabled = true;
        await refresh();
        refreshButton.disabled = false;
    });

    const fullscreenButton = document.getElementById('atcFullscreen');
    fullscreenButton.addEventListener('click', async () => {
        try {
            if (!document.documentElement.requestFullscreen) {
                AMCS.toast('Fullscreen', 'Browser ini tidak mendukung mode layar penuh.', 'warning');
                return;
            }

            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else {
                await document.documentElement.requestFullscreen();
            }
        } catch (error) {
            AMCS.toast('Fullscreen', 'Mode layar penuh tidak dapat diaktifkan.', 'warning');
        }
    });

    document.addEventListener('fullscreenchange', () => {
        fullscreenButton.innerText = document.fullscreenElement ? '⤢ Keluar Fullscreen' : '⛶ Fullscreen';
    });

    refresh();
})();
</script>
@endpush
