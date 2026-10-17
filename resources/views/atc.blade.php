@extends('layouts.app')

@section('title', 'Monitor ATC Tower View')
@section('body-class', 'atc')

@section('content')
    <div class="atc-head">
        <div>
            <h1>ATC MOVEMENT MONITOR BOARD <span class="atc-tag">TOWER VIEW</span></h1>
            <p class="muted" style="font-size:12px;margin-top:6px;">
                {{ config('amcs.airport.name') }} · sinkron terakhir: <span id="lastSync">-</span>
            </p>
        </div>
        <div class="connection offline" id="connStatus"><span class="dot"></span><span id="connText">Menghubungkan</span></div>
    </div>

    <div class="atc-grid" id="atcGrid"></div>
@endsection

@push('scripts')
<script>
(function () {
    const pollMs = (parseInt(document.querySelector('meta[name="amcs-poll"]').content, 10) || 3) * 1000;
    const conn = AMCS.initConnection();

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
        const flight = state === 'VACANT' ? 'READY' : (m ? m.flight_number : '-');
        const flightColor = state === 'VACANT' ? '#10b981' : '#f59e0b';
        const routing = state === 'VACANT' ? 'NAV ROUTING: N/A' : (AMCS.escapeHtml(m?.runway || '-') + ' | ' + AMCS.escapeHtml(m?.taxiway || '-'));

        return `<div class="atc-card">
            <div>
                <div class="title">${AMCS.escapeHtml(stand.name)}</div>
                ${stand.allowed_aircraft && stand.allowed_aircraft.length <= 4 ? `<div class="stand-limit">MAX: ${stand.allowed_aircraft.join('/')}</div>` : ''}
            </div>
            <div>
                <div class="flight" style="color:${flightColor}">${AMCS.escapeHtml(flight)}</div>
                <div class="ac">${m ? AMCS.escapeHtml(m.aircraft_type || 'NONE') : 'NONE'}</div>
                ${m ? `<div class="muted" style="font-size:12px;margin-top:6px;">${AMCS.escapeHtml(m.airline || '-')} · ${AMCS.escapeHtml(m.registration || '-')}</div>` : ''}
                ${m ? `<div class="route" style="margin-top:8px;">${AMCS.escapeHtml(m.route || '-')}</div>` : ''}
            </div>
            <div class="info">
                <div class="row"><span class="l">ETA</span><span class="v">${m ? AMCS.escapeHtml(m.eta || '-') : '-'}</span></div>
                <div class="row"><span class="l">ATA</span><span class="v">${m ? AMCS.escapeHtml(m.ata || '-') : '-'}</span></div>
                <div class="row"><span class="l">ETD</span><span class="v">${m ? AMCS.escapeHtml(m.etd || '-') : '-'}</span></div>
                <div class="row"><span class="l">ATD</span><span class="v">${m ? AMCS.escapeHtml(m.atd || '-') : '-'}</span></div>
                ${m && state === 'DELAY' ? `<div class="row"><span class="l">DELAY</span><span class="v" style="color:#f87171;">${m.delay_minutes}m ${AMCS.escapeHtml(m.delay_reason || '')}</span></div>` : ''}
            </div>
            <div class="routing">${routing}</div>
            <span class="status ${statusClass(state)}">${AMCS.escapeHtml(stand.atc_label)}</span>
        </div>`;
    }

    async function refresh() {
        try {
            const board = await AMCS.api('/stands');
            document.getElementById('atcGrid').innerHTML = board.stands.map(card).join('');
            document.getElementById('lastSync').innerText = new Date().toLocaleTimeString('id-ID', { hour12: false });
            conn.online();
        } catch (error) {
            conn.offline();
        }
    }

    refresh();
    setInterval(refresh, pollMs);
})();
</script>
@endpush
