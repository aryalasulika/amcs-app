@extends('layouts.app')

@section('title', 'Manajemen Pengguna')

@section('content')
    <div class="page-head">
        <div><h2>Manajemen Pengguna & Role</h2><p>Kelola akun, status aktif, dan penugasan role operasional.</p></div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h3 id="formTitle">Tambah Pengguna</h3></div>
            <form id="userForm">
                <input type="hidden" id="userId">
                <div class="form-grid">
                    <div class="field full"><label for="name">Nama</label><input id="name" required></div>
                    <div class="field full"><label for="email">Email</label><input id="email" type="email" required></div>
                    <div class="field"><label for="password">Kata Sandi</label><input id="password" type="password" placeholder="min. 8 karakter"></div>
                    <div class="field"><label for="passwordConfirm">Konfirmasi Sandi</label><input id="passwordConfirm" type="password"></div>
                    <div class="field full"><label for="roles">Role</label><select id="roles" multiple size="4"></select></div>
                    <div class="field full">
                        <label style="display:flex;gap:8px;text-transform:none;"><input type="checkbox" id="active" style="width:auto;" checked> Akun aktif</label>
                    </div>
                </div>
                <div class="btn-row mt">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn" id="cancel">Batal</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head"><h3>Daftar Pengguna</h3><span class="muted" id="count"></span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
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
    let roles = [];

    async function loadRoles() {
        const res = await AMCS.api('/roles');
        roles = res.data || [];
        $('roles').innerHTML = roles.map(r => `<option value="${AMCS.escapeHtml(r.name)}">${AMCS.escapeHtml(r.label || r.name)}</option>`).join('');
    }

    async function load() {
        const res = await AMCS.api('/users', { query: { per_page: 100 } });
        items = res.data || [];
        $('count').innerText = items.length + ' pengguna';
        $('rows').innerHTML = items.map(u => `<tr>
            <td><b>${AMCS.escapeHtml(u.name)}</b></td>
            <td>${AMCS.escapeHtml(u.email)}</td>
            <td>${AMCS.escapeHtml((u.roles || []).join(', ') || '-')}</td>
            <td><span class="badge ${u.active ? 'green' : 'gray'}">${u.active ? 'Aktif' : 'Nonaktif'}</span></td>
            <td class="text-right"><button class="btn btn-sm" data-edit="${u.id}">Ubah</button></td>
        </tr>`).join('') || '<tr><td colspan="5" class="empty">Belum ada pengguna.</td></tr>';
    }

    function reset() { $('userForm').reset(); $('userId').value = ''; $('active').checked = true; $('formTitle').innerText = 'Tambah Pengguna'; $('password').required = true; }

    $('userForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = $('userId').value;
        const body = {
            name: $('name').value.trim(),
            email: $('email').value.trim(),
            active: $('active').checked,
            roles: Array.from($('roles').selectedOptions).map(o => o.value),
        };
        if ($('password').value) {
            body.password = $('password').value;
            body.password_confirmation = $('passwordConfirm').value;
        }
        try {
            await AMCS.api(id ? '/users/' + id : '/users', { method: id ? 'PUT' : 'POST', body });
            AMCS.toast('Tersimpan', 'Data pengguna disimpan.', 'success');
            reset(); load();
        } catch (error) { AMCS.errorToast(error, 'Gagal menyimpan'); }
    });

    $('rows').addEventListener('click', (e) => {
        const edit = e.target.closest('[data-edit]');
        if (!edit) return;
        const u = items.find(x => String(x.id) === edit.dataset.edit);
        $('userId').value = u.id; $('name').value = u.name; $('email').value = u.email;
        $('active').checked = !!u.active; $('password').required = false; $('password').value = ''; $('passwordConfirm').value = '';
        Array.from($('roles').options).forEach(o => o.selected = (u.roles || []).includes(o.value));
        $('formTitle').innerText = 'Ubah Pengguna — ' + u.name;
    });

    $('cancel').addEventListener('click', reset);
    (async function () { await loadRoles(); await load(); reset(); })();
})();
</script>
@endpush
