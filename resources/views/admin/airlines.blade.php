@extends('layouts.app')

@section('title', 'Master Data Maskapai')

@section('content')
    <div class="page-head">
        <div><h2>Master Data Maskapai</h2><p>Kode maskapai dan awalan nomor penerbangan.</p></div>
        <div class="nav">
            <a href="{{ route('admin.stands') }}">Stand</a>
            <a href="{{ route('admin.aircraft-types') }}">Tipe Pesawat</a>
            <a href="{{ route('admin.airlines') }}" class="active">Maskapai</a>
        </div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h3 id="formTitle">Tambah Maskapai</h3></div>
            <form id="airlineForm">
                <input type="hidden" id="airlineId">
                <div class="form-grid">
                    <div class="field"><label for="code">Kode</label><input id="code" placeholder="WON" required></div>
                    <div class="field"><label for="name">Nama Maskapai</label><input id="name" placeholder="Wings Air" required></div>
                    <div class="field"><label for="prefix">Awalan Nomor</label><input id="prefix" placeholder="IW"></div>
                    <div class="field full">
                        <label for="defaultAircraft">Tipe Pesawat Default (auto-fill)</label>
                        <select id="defaultAircraft"></select>
                        <span class="hint">Dipakai otomatis saat nomor penerbangan diawali kode maskapai ini.</span>
                    </div>
                    <div class="field">
                        <label style="display:flex;gap:8px;text-transform:none;margin-top:20px;"><input type="checkbox" id="active" style="width:auto;" checked> Aktif</label>
                    </div>
                </div>
                <div class="btn-row mt">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn" id="cancel">Batal</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head"><h3>Daftar Maskapai</h3><span class="muted" id="count"></span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Kode</th><th>Nama</th><th>Awalan</th><th>Tipe Default</th><th>Status</th><th></th></tr></thead>
                    <tbody id="rows"><tr><td colspan="5" class="empty">Memuat...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    let items = [];

    async function loadOptions() {
        const data = await AMCS.api('/options');
        $('defaultAircraft').innerHTML = '<option value="">-- tanpa tipe default --</option>'
            + data.aircraft_types.map(t => `<option value="${t.id}">${AMCS.escapeHtml(t.code)} — ${AMCS.escapeHtml(t.name)}</option>`).join('');
    }

    async function load() {
        const res = await AMCS.api('/airlines', { query: { per_page: 200 } });
        items = res.data || [];
        $('count').innerText = items.length + ' maskapai';
        $('rows').innerHTML = items.map(a => `<tr>
            <td><b>${AMCS.escapeHtml(a.code)}</b></td>
            <td>${AMCS.escapeHtml(a.name)}</td>
            <td>${AMCS.escapeHtml(a.flight_prefix || '-')}</td>
            <td>${AMCS.escapeHtml(a.default_aircraft_type?.code || '-')}</td>
            <td><span class="badge ${a.active ? 'green' : 'gray'}">${a.active ? 'Aktif' : 'Nonaktif'}</span></td>
            <td class="text-right">
                <button class="btn btn-sm" data-edit="${a.id}">Ubah</button>
                <button class="btn btn-sm btn-red" data-del="${a.id}">Hapus</button>
            </td>
        </tr>`).join('') || '<tr><td colspan="6" class="empty">Belum ada data.</td></tr>';
    }

    function reset() { $('airlineForm').reset(); $('airlineId').value = ''; $('defaultAircraft').value = ''; $('active').checked = true; $('formTitle').innerText = 'Tambah Maskapai'; }

    $('airlineForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = $('airlineId').value;
        const body = {
            code: $('code').value.trim().toUpperCase(),
            name: $('name').value.trim(),
            flight_prefix: $('prefix').value.trim().toUpperCase() || null,
            default_aircraft_type_id: $('defaultAircraft').value ? parseInt($('defaultAircraft').value, 10) : null,
            active: $('active').checked,
        };
        try {
            await AMCS.api(id ? '/airlines/' + id : '/airlines', { method: id ? 'PUT' : 'POST', body });
            AMCS.toast('Tersimpan', 'Data maskapai disimpan.', 'success');
            reset(); load();
        } catch (error) { AMCS.errorToast(error, 'Gagal menyimpan'); }
    });

    $('rows').addEventListener('click', async (e) => {
        const edit = e.target.closest('[data-edit]');
        const del = e.target.closest('[data-del]');
        if (edit) {
            const a = items.find(x => String(x.id) === edit.dataset.edit);
            $('airlineId').value = a.id; $('code').value = a.code; $('name').value = a.name;
            $('prefix').value = a.flight_prefix || ''; $('active').checked = !!a.active;
            $('defaultAircraft').value = a.default_aircraft_type_id || '';
            $('formTitle').innerText = 'Ubah Maskapai';
        }
        if (del) {
            if (!confirm('Hapus maskapai ini?')) return;
            try { await AMCS.api('/airlines/' + del.dataset.del, { method: 'DELETE' }); AMCS.toast('Terhapus', 'Data dihapus.', 'success'); load(); }
            catch (error) { AMCS.errorToast(error, 'Gagal menghapus'); }
        }
    });

    $('cancel').addEventListener('click', reset);
    (async function () {
        try { await loadOptions(); } catch (error) { AMCS.errorToast(error, 'Gagal memuat opsi'); }
        load();
    })();
})();
</script>
@endpush
