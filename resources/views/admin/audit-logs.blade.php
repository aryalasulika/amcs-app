@extends('layouts.app')

@section('title', 'Audit Trail')

@section('content')
    <div class="page-head">
        <div><h2>Audit Trail</h2><p>Jejak perubahan permanen: pelaku, waktu server, data sebelum dan sesudah. Tidak dapat dihapus.</p></div>
    </div>

    <div class="panel mb">
        <div class="form-grid">
            <div class="field"><label for="fAction">Aksi</label><input id="fAction" placeholder="movement.created"></div>
            <div class="field"><label for="fEntity">Entitas</label><input id="fEntity" placeholder="Movement"></div>
            <div class="field"><label for="fFrom">Dari Tanggal</label><input type="date" id="fFrom"></div>
            <div class="field"><label for="fTo">Sampai Tanggal</label><input type="date" id="fTo"></div>
        </div>
        <div class="btn-row mt">
            <button class="btn btn-primary btn-sm" id="apply">Terapkan</button>
            <button class="btn btn-sm" id="reset">Bersihkan</button>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h3>Riwayat Audit</h3><span class="muted" id="count"></span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th>Alasan</th><th>Perubahan</th></tr></thead>
                <tbody id="rows"><tr><td colspan="6" class="empty">Memuat...</td></tr></tbody>
            </table>
        </div>
        <div class="btn-row mt">
            <button class="btn btn-sm" id="prev">‹ Sebelumnya</button>
            <span class="muted" id="pageInfo" style="align-self:center;"></span>
            <button class="btn btn-sm" id="next">Berikutnya ›</button>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    let page = 1, lastPage = 1;

    async function load() {
        const query = {
            action: $('fAction').value.trim() || null,
            entity_type: $('fEntity').value.trim() || null,
            date_from: $('fFrom').value || null,
            date_to: $('fTo').value || null,
            page: page,
            per_page: 25,
        };
        try {
            const res = await AMCS.api('/audit-logs', { query });
            const rows = res.data || [];
            const meta = res.meta || {};
            lastPage = meta.last_page || 1;
            $('count').innerText = (meta.total || 0) + ' catatan';
            $('pageInfo').innerText = 'Halaman ' + page + ' dari ' + lastPage;

            $('rows').innerHTML = rows.map(l => `<tr>
                <td class="mono" style="white-space:nowrap;">${l.created_at ? new Date(l.created_at).toLocaleString('id-ID') : '-'}</td>
                <td>${AMCS.escapeHtml(l.user_name || '-')}</td>
                <td><span class="badge blue">${AMCS.escapeHtml(l.action)}</span></td>
                <td>${AMCS.escapeHtml(l.entity_type || '-')}${l.entity_id ? ' #' + AMCS.escapeHtml(l.entity_id) : ''}</td>
                <td>${AMCS.escapeHtml(l.reason || '-')}</td>
                <td><button class="btn btn-sm" data-detail="${AMCS.escapeHtml(encodeURIComponent(JSON.stringify({ before: l.before_data, after: l.after_data })))}">Lihat</button></td>
            </tr>`).join('') || '<tr><td colspan="6" class="empty">Tidak ada catatan audit.</td></tr>';
        } catch (error) { AMCS.errorToast(error, 'Gagal memuat audit'); }
    }

    $('rows').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-detail]');
        if (!btn) return;
        const data = JSON.parse(decodeURIComponent(btn.dataset.detail));
        alert('SEBELUM:\n' + JSON.stringify(data.before, null, 2) + '\n\nSESUDAH:\n' + JSON.stringify(data.after, null, 2));
    });

    $('apply').addEventListener('click', () => { page = 1; load(); });
    $('reset').addEventListener('click', () => { ['fAction', 'fEntity', 'fFrom', 'fTo'].forEach(id => $(id).value = ''); page = 1; load(); });
    $('prev').addEventListener('click', () => { if (page > 1) { page--; load(); } });
    $('next').addEventListener('click', () => { if (page < lastPage) { page++; load(); } });

    load();
})();
</script>
@endpush
