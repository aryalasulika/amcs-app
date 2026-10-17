@extends('layouts.app')

@section('title', 'Laporan Operasional')

@section('content')
    <div class="page-head">
        <div>
            <h2>Laporan & Histori Operasional</h2>
            <p>Rekap harian, bulanan, penggunaan stand, dan export laporan terpadu.</p>
        </div>
        @if ($canExport)
            <div class="btn-row">
                <button class="btn btn-green btn-sm" id="exportXlsx">📊 Export Excel (.xlsx)</button>
                <button class="btn btn-sm" id="exportCsv">📄 Export CSV</button>
            </div>
        @endif
    </div>

    <div class="panel mb">
        <div class="panel-head"><h3>Filter</h3></div>
        <div class="form-grid">
            <div class="field"><label for="fDateFrom">Dari Tanggal</label><input type="date" id="fDateFrom"></div>
            <div class="field"><label for="fDateTo">Sampai Tanggal</label><input type="date" id="fDateTo"></div>
            <div class="field"><label for="fFlight">Nomor Penerbangan</label><input type="text" id="fFlight" placeholder="IW-114"></div>
            <div class="field"><label for="fStand">Stand</label><select id="fStand"></select></div>
            <div class="field"><label for="fState">Status</label><select id="fState"></select></div>
            <div class="field"><label for="fType">Jenis Penerbangan</label><select id="fType"></select></div>
        </div>
        <div class="btn-row mt">
            <button class="btn btn-primary btn-sm" id="applyFilter">Terapkan Filter</button>
            <button class="btn btn-sm" id="resetFilter">Bersihkan</button>
        </div>
    </div>

    <div class="stats-bar" id="recapCards">
        <div class="stat-card green"><span class="stat-label">Total Pergerakan</span><div class="stat-number" id="rMov">0</div></div>
        <div class="stat-card red"><span class="stat-label">Total Delay</span><div class="stat-number" id="rDelay">0</div></div>
        <div class="stat-card blue"><span class="stat-label">Total RON</span><div class="stat-number" id="rRon">0</div></div>
        <div class="stat-card yellow"><span class="stat-label">Total Menit Delay</span><div class="stat-number" id="rMin">0</div></div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h3>Histori Pergerakan</h3>
            <span class="muted" id="listMeta"></span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Tanggal</th><th>Stand</th><th>Flight</th><th>Maskapai</th><th>Rute</th><th>Tipe</th><th>Reg</th><th>Status</th><th>Delay</th><th>Diinput</th></tr>
                </thead>
                <tbody id="rows"><tr><td colspan="10" class="empty">Memuat data...</td></tr></tbody>
            </table>
        </div>
        <div class="btn-row mt">
            <button class="btn btn-sm" id="prevPage">‹ Sebelumnya</button>
            <span class="muted" id="pageInfo" style="align-self:center;"></span>
            <button class="btn btn-sm" id="nextPage">Berikutnya ›</button>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    let page = 1;
    let lastPage = 1;

    function query() {
        return {
            date_from: $('fDateFrom').value || null,
            date_to: $('fDateTo').value || null,
            flight_number: $('fFlight').value.trim() || null,
            parking_stand_id: $('fStand').value || null,
            state: $('fState').value || null,
            flight_type: $('fType').value || null,
        };
    }

    async function loadOptions() {
        const data = await AMCS.api('/options');
        $('fStand').innerHTML = '<option value="">Semua stand</option>' + data.parking_stands.map(s => `<option value="${s.id}">${AMCS.escapeHtml(s.name)}</option>`).join('');
        $('fState').innerHTML = '<option value="">Semua status</option>' + data.states.map(s => `<option value="${s.value}">${AMCS.escapeHtml(s.label)}</option>`).join('');
        $('fType').innerHTML = '<option value="">Semua jenis</option>' + data.flight_types.map(s => `<option value="${s.value}">${AMCS.escapeHtml(s.label)}</option>`).join('');
    }

    async function load() {
        const q = query();
        q.per_page = 20;
        q.page = page;
        try {
            const res = await AMCS.api('/reports/history', { query: q });
            const rows = res.data || [];
            $('listMeta').innerText = (res.meta?.total || 0) + ' catatan';
            lastPage = res.meta?.last_page || 1;
            $('pageInfo').innerText = 'Halaman ' + page + ' dari ' + lastPage;

            if (!rows.length) {
                $('rows').innerHTML = '<tr><td colspan="10" class="empty">Tidak ada data sesuai filter.</td></tr>';
                return;
            }

            $('rows').innerHTML = rows.map(m => `<tr>
                <td>${AMCS.escapeHtml(m.report_date || '-')}</td>
                <td><b>${AMCS.escapeHtml(m.parking_stand?.name || '-')}</b></td>
                <td>${AMCS.escapeHtml(m.flight_number || '-')}</td>
                <td>${AMCS.escapeHtml(m.airline?.name || '-')}</td>
                <td style="color:#60a5fa;font-weight:700;">${AMCS.escapeHtml(m.route || '-')}</td>
                <td>${AMCS.escapeHtml(m.aircraft_type?.code || '-')}</td>
                <td style="color:#f59e0b;font-weight:700;">${AMCS.escapeHtml(m.registration || '-')}</td>
                <td><span class="badge ${m.state === 'DELAY' ? 'red' : (m.state === 'OFF' ? 'gray' : 'amber')}">${AMCS.escapeHtml(m.state_label || m.state)}</span></td>
                <td>${m.delay_minutes ? '<span class="badge red">' + m.delay_minutes + 'm</span>' : '-'}</td>
                <td>${AMCS.escapeHtml(m.created_by?.name || '-')}</td>
            </tr>`).join('');
        } catch (error) { AMCS.errorToast(error, 'Gagal memuat histori'); }
    }

    async function loadRecap() {
        try {
            const res = await AMCS.api('/reports/summary', { query: query() });
            const totals = res.totals || {};
            $('rMov').innerText = totals.movements || 0;
            $('rDelay').innerText = totals.delays || 0;
            $('rRon').innerText = totals.ron || 0;
            $('rMin').innerText = totals.total_delay_minutes || 0;
        } catch (error) { /* ignore */ }
    }

    $('applyFilter').addEventListener('click', () => { page = 1; load(); loadRecap(); });
    $('resetFilter').addEventListener('click', () => {
        ['fDateFrom', 'fDateTo', 'fFlight'].forEach(id => $(id).value = '');
        ['fStand', 'fState', 'fType'].forEach(id => $(id).value = '');
        page = 1; load(); loadRecap();
    });
    $('prevPage').addEventListener('click', () => { if (page > 1) { page--; load(); } });
    $('nextPage').addEventListener('click', () => { if (page < lastPage) { page++; load(); } });

    @if ($canExport)
    function exportAs(format) {
        const q = query();
        q.format = format;
        const params = new URLSearchParams();
        Object.entries(q).forEach(([k, v]) => { if (v) params.append(k, v); });
        window.location = AMCS.apiUrl('/reports/export') + '?' + params.toString();
    }
    $('exportXlsx').addEventListener('click', () => exportAs('xlsx'));
    $('exportCsv').addEventListener('click', () => exportAs('csv'));
    @endif

    (async function init() {
        try { await loadOptions(); } catch (e) { AMCS.errorToast(e, 'Gagal memuat opsi'); }
        await load();
        loadRecap();
    })();
})();
</script>
@endpush
