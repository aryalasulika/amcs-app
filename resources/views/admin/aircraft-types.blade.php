@extends('layouts.app')

@section('title', 'Master Data Tipe Pesawat')

@section('content')
    <div class="page-head">
        <div><h2>Master Data Tipe Pesawat</h2><p>Kategori, wingspan, dan batas berat pesawat.</p></div>
        <div class="nav">
            <a href="{{ route('admin.stands') }}">Stand</a>
            <a href="{{ route('admin.aircraft-types') }}" class="active">Tipe Pesawat</a>
            <a href="{{ route('admin.airlines') }}">Maskapai</a>
        </div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h3 id="formTitle">Tambah Tipe Pesawat</h3></div>
            <form id="typeForm">
                <input type="hidden" id="typeId">
                <div class="form-grid">
                    <div class="field"><label for="code">Kode</label><input id="code" placeholder="ATR72" required></div>
                    <div class="field"><label for="name">Nama</label><input id="name" placeholder="ATR 72-600" required></div>
                    <div class="field"><label for="category">Kategori</label><select id="category"></select></div>
                    <div class="field"><label for="wingspan">Wingspan (m)</label><input id="wingspan" type="number" step="0.01"></div>
                    <div class="field"><label for="maxWeight">Berat Maks (kg)</label><input id="maxWeight" type="number" step="0.01"></div>
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
            <div class="panel-head"><h3>Daftar Tipe Pesawat</h3><span class="muted" id="count"></span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Wingspan</th><th>Berat</th><th>Status</th><th></th></tr></thead>
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
    let items = [];

    async function loadOptions() {
        const data = await AMCS.api('/options');
        $('category').innerHTML = data.aircraft_categories.map(c => `<option value="${c.value}">${AMCS.escapeHtml(c.label)}</option>`).join('');
    }

    async function load() {
        const res = await AMCS.api('/aircraft-types', { query: { per_page: 200 } });
        items = res.data || [];
        $('count').innerText = items.length + ' tipe';
        $('rows').innerHTML = items.map(t => `<tr>
            <td><b>${AMCS.escapeHtml(t.code)}</b></td>
            <td>${AMCS.escapeHtml(t.name)}</td>
            <td>${AMCS.escapeHtml(t.category_label || t.category || '-')}</td>
            <td>${t.wingspan ?? '-'}</td>
            <td>${t.max_weight ?? '-'}</td>
            <td><span class="badge ${t.active ? 'green' : 'gray'}">${t.active ? 'Aktif' : 'Nonaktif'}</span></td>
            <td class="text-right">
                <button class="btn btn-sm" data-edit="${t.id}">Ubah</button>
                <button class="btn btn-sm btn-red" data-del="${t.id}">Hapus</button>
            </td>
        </tr>`).join('') || '<tr><td colspan="7" class="empty">Belum ada data.</td></tr>';
    }

    function reset() { $('typeForm').reset(); $('typeId').value = ''; $('active').checked = true; $('formTitle').innerText = 'Tambah Tipe Pesawat'; }

    $('typeForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = $('typeId').value;
        const body = {
            code: $('code').value.trim().toUpperCase(),
            name: $('name').value.trim(),
            category: $('category').value,
            wingspan: $('wingspan').value || null,
            max_weight: $('maxWeight').value || null,
            active: $('active').checked,
        };
        try {
            await AMCS.api(id ? '/aircraft-types/' + id : '/aircraft-types', { method: id ? 'PUT' : 'POST', body });
            AMCS.toast('Tersimpan', 'Data tipe pesawat disimpan.', 'success');
            reset(); load();
        } catch (error) { AMCS.errorToast(error, 'Gagal menyimpan'); }
    });

    $('rows').addEventListener('click', async (e) => {
        const edit = e.target.closest('[data-edit]');
        const del = e.target.closest('[data-del]');
        if (edit) {
            const t = items.find(x => String(x.id) === edit.dataset.edit);
            $('typeId').value = t.id; $('code').value = t.code; $('name').value = t.name;
            $('category').value = t.category; $('wingspan').value = t.wingspan ?? ''; $('maxWeight').value = t.max_weight ?? '';
            $('active').checked = !!t.active; $('formTitle').innerText = 'Ubah Tipe Pesawat';
        }
        if (del) {
            if (!confirm('Hapus tipe pesawat ini?')) return;
            try { await AMCS.api('/aircraft-types/' + del.dataset.del, { method: 'DELETE' }); AMCS.toast('Terhapus', 'Data dihapus.', 'success'); load(); }
            catch (error) { AMCS.errorToast(error, 'Gagal menghapus'); }
        }
    });

    $('cancel').addEventListener('click', reset);
    (async function () { await loadOptions(); await load(); })();
})();
</script>
@endpush
