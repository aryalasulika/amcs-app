@extends('layouts.app')

@section('title', 'Master Data Parking Stand')

@section('content')
    <div class="page-head">
        <div><h2>Master Data Parking Stand</h2><p>Kapasitas, kategori, area, dan batasan tipe pesawat per stand.</p></div>
        <div class="nav" style="gap:6px;">
            <a href="{{ route('admin.stands') }}" class="active">Stand</a>
            <a href="{{ route('admin.aircraft-types') }}">Tipe Pesawat</a>
            <a href="{{ route('admin.airlines') }}">Maskapai</a>
        </div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h3 id="formTitle">Tambah Stand</h3><span class="muted" id="formMode">baru</span></div>
            <form id="standForm">
                <input type="hidden" id="standId">
                <div class="form-grid">
                    <div class="field"><label for="code">Kode Stand</label><input id="code" placeholder="STAND-6" required></div>
                    <div class="field"><label for="name">Nama Stand</label><input id="name" placeholder="Parking Stand 6" required></div>
                    <div class="field"><label for="capacityCategory">Kategori Kapasitas</label><select id="capacityCategory"></select></div>
                    <div class="field"><label for="gateArea">Area / Gate</label><input id="gateArea" placeholder="Apron C"></div>
                    <div class="field"><label for="positionX">Posisi X</label><input id="positionX" type="number" step="0.01"></div>
                    <div class="field"><label for="positionY">Posisi Y</label><input id="positionY" type="number" step="0.01"></div>
                    <div class="field full"><label for="notes">Catatan Operasional</label><textarea id="notes"></textarea></div>
                    <div class="field full">
                        <label for="aircraftTypes">Tipe Pesawat yang Diizinkan</label>
                        <select id="aircraftTypes" multiple size="5"></select>
                        <span class="hint">Kosongkan untuk tanpa batasan eksplisit (memakai kategori kapasitas).</span>
                    </div>
                    <div class="field full">
                        <label style="display:flex;gap:8px;text-transform:none;"><input type="checkbox" id="active" style="width:auto;" checked> Stand aktif</label>
                    </div>
                </div>
                <div class="btn-row mt">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn" id="cancel">Batal</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head"><h3>Daftar Stand</h3><span class="muted" id="count"></span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Area</th><th>Tipe Diizinkan</th><th>Status</th><th></th></tr></thead>
                    <tbody id="rows"><tr><td colspan="7" class="empty">Memuat...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    let types = [];
    let stands = [];

    async function loadOptions() {
        const data = await AMCS.api('/options');
        types = data.aircraft_types;
        $('capacityCategory').innerHTML = '<option value="">-- tanpa kategori --</option>'
            + data.aircraft_categories.map(c => `<option value="${c.value}">${AMCS.escapeHtml(c.label)}</option>`).join('');
        $('aircraftTypes').innerHTML = types.map(t => `<option value="${t.id}">${AMCS.escapeHtml(t.code)} — ${AMCS.escapeHtml(t.name)}</option>`).join('');
    }

    async function load() {
        const res = await AMCS.api('/parking-stands', { query: { per_page: 100 } });
        stands = res.data || [];
        $('count').innerText = stands.length + ' stand';
        $('rows').innerHTML = stands.map(s => `<tr>
            <td><b>${AMCS.escapeHtml(s.code)}</b></td>
            <td>${AMCS.escapeHtml(s.name)}</td>
            <td>${AMCS.escapeHtml(s.capacity_category || '-')}</td>
            <td>${AMCS.escapeHtml(s.gate_area || '-')}</td>
            <td>${AMCS.escapeHtml((s.aircraft_types || []).map(t => t.code).join(', ') || '-')}</td>
            <td><span class="badge ${s.active ? 'green' : 'gray'}">${s.active ? 'Aktif' : 'Nonaktif'}</span></td>
            <td class="text-right">
                <button class="btn btn-sm" data-edit="${s.id}">Ubah</button>
                <button class="btn btn-sm btn-red" data-del="${s.id}">Hapus</button>
            </td>
        </tr>`).join('') || '<tr><td colspan="7" class="empty">Belum ada stand.</td></tr>';
    }

    function reset() {
        $('standForm').reset();
        $('standId').value = '';
        $('active').checked = true;
        $('formTitle').innerText = 'Tambah Stand';
        $('formMode').innerText = 'baru';
        $('aircraftTypes').selectedIndex = -1;
    }

    $('standForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = $('standId').value;
        const body = {
            code: $('code').value.trim().toUpperCase(),
            name: $('name').value.trim(),
            capacity_category: $('capacityCategory').value || null,
            gate_area: $('gateArea').value.trim() || null,
            position_x: $('positionX').value || null,
            position_y: $('positionY').value || null,
            notes: $('notes').value.trim() || null,
            active: $('active').checked,
            aircraft_type_ids: Array.from($('aircraftTypes').selectedOptions).map(o => parseInt(o.value, 10)),
        };
        try {
            await AMCS.api(id ? '/parking-stands/' + id : '/parking-stands', { method: id ? 'PUT' : 'POST', body });
            AMCS.toast('Tersimpan', 'Data stand disimpan.', 'success');
            reset(); load();
        } catch (error) { AMCS.errorToast(error, 'Gagal menyimpan'); }
    });

    $('rows').addEventListener('click', async (e) => {
        const edit = e.target.closest('[data-edit]');
        const del = e.target.closest('[data-del]');
        if (edit) {
            const s = stands.find(x => String(x.id) === edit.dataset.edit);
            $('standId').value = s.id;
            $('code').value = s.code; $('name').value = s.name;
            $('capacityCategory').value = s.capacity_category || '';
            $('gateArea').value = s.gate_area || '';
            $('positionX').value = s.position?.x ?? ''; $('positionY').value = s.position?.y ?? '';
            $('notes').value = s.notes || ''; $('active').checked = !!s.active;
            const ids = (s.aircraft_types || []).map(t => String(t.id));
            Array.from($('aircraftTypes').options).forEach(o => o.selected = ids.includes(o.value));
            $('formTitle').innerText = 'Ubah Stand'; $('formMode').innerText = s.code;
        }
        if (del) {
            if (!confirm('Hapus stand ini? Tindakan dicatat pada audit trail.')) return;
            try { await AMCS.api('/parking-stands/' + del.dataset.del, { method: 'DELETE' }); AMCS.toast('Terhapus', 'Stand dihapus.', 'success'); load(); }
            catch (error) { AMCS.errorToast(error, 'Gagal menghapus'); }
        }
    });

    $('cancel').addEventListener('click', reset);

    (async function () { await loadOptions(); await load(); })();
})();
</script>
@endpush
