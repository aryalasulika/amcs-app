@extends('layouts.app')

@section('title', 'Dashboard AMC')

@section('content')
    <div class="page-head">
        <div>
            <h2>Dashboard Kontrol Apron</h2>
            <p>Alokasi parking stand, siklus status penerbangan, dan histori pergerakan realtime.</p>
        </div>
        <div class="btn-row">
            <button class="btn btn-sm" id="btnNotifications">🔔 Notifikasi <span class="badge red" id="notifCount" style="display:none;">0</span></button>
            @can('reports.export')
                <button class="btn btn-green btn-sm" id="btnExport">📊 Export Laporan (.xlsx)</button>
            @endcan
            @can('stands.reset')
                <button class="btn btn-red btn-sm" id="btnReset">🔴 Reset Semua Stand</button>
            @endcan
        </div>
    </div>

    <div class="stats-bar">
        <div class="stat-card green"><span class="stat-label">Total Pergerakan (Tanggal Laporan)</span><div class="stat-number" id="stat-mov">0</div></div>
        <div class="stat-card yellow"><span class="stat-label">Pesawat Aktif di Stand</span><div class="stat-number" id="stat-active">0</div></div>
        <div class="stat-card blue"><span class="stat-label">Menginap (RON)</span><div class="stat-number" id="stat-ron">0</div></div>
        <div class="stat-card red"><span class="stat-label">Kasus Delay</span><div class="stat-number" id="stat-delay">0</div></div>
    </div>

    <div class="apron">
        <div class="runway-strip">
            <div class="rwy-designator">RWY 04</div>
            <div class="rwy-designator">RWY 22</div>
        </div>
        <div class="taxiway-container">
            <div class="taxi-link"><span class="taxi-name-tag">TWY ALPHA</span></div>
            <div class="taxi-link"><span class="taxi-name-tag">TWY BRAVO</span></div>
        </div>
        <div class="apron-title">Alokasi Parking Stand Realtime</div>
        <div class="apron-grid" id="standGrid"></div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head">
                <h3 id="formTitle">Input Pergerakan Penerbangan</h3>
                <span class="muted" id="formMode">Mode: buat pergerakan baru</span>
            </div>

            <div class="box-dashed mb" id="standAlert" style="display:none;"></div>

            <form id="movementForm" autocomplete="off">
                <input type="hidden" id="editingId">

                <div class="field full box-dashed mb">
                    <label for="reportDate">📅 Tanggal Operasional Laporan</label>
                    <input type="date" id="reportDate">
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="flightNumber">Nomor Penerbangan</label>
                        <input type="text" id="flightNumber" placeholder="IW-114" maxlength="32">
                        <span class="hint" id="flightAutofillHint">Maskapai &amp; tipe pesawat terisi otomatis dari awalan nomor penerbangan.</span>
                    </div>
                    <div class="field">
                        <label for="registration">Registrasi (Tail Number)</label>
                        <input type="text" id="registration" placeholder="PK-WGO" maxlength="32">
                    </div>
                    <div class="field">
                        <label for="origin">Origin (ICAO)</label>
                        <input type="text" id="origin" placeholder="WAAA" maxlength="4" style="text-transform:uppercase;">
                    </div>
                    <div class="field">
                        <label for="destination">Destination (ICAO)</label>
                        <input type="text" id="destination" placeholder="WAMW" maxlength="4" style="text-transform:uppercase;">
                    </div>

                    <div class="field full">
                        <label for="airlineId">Maskapai</label>
                        <select id="airlineId"></select>
                        <div class="field" id="airlineManualWrap" style="display:none;">
                            <input type="text" id="airlineManual" placeholder="Ketik nama maskapai baru...">
                        </div>
                    </div>

                    <div class="field full">
                        <label for="flightType">Jenis Penerbangan</label>
                        <select id="flightType"></select>
                    </div>

                    <div class="field full">
                        <label for="aircraftTypeId">Tipe Pesawat</label>
                        <select id="aircraftTypeId"></select>
                        <div class="field" id="aircraftManualWrap" style="display:none;">
                            <input type="text" id="aircraftManual" placeholder="Ketik tipe pesawat...">
                            <select id="aircraftCategory" style="margin-top:8px;"></select>
                            <span class="hint">Tipe manual disimpan ke master data. Pilih kategori agar kapasitas stand dapat diverifikasi.</span>
                        </div>
                        <span class="hint" id="standCapacityHint"></span>
                    </div>

                    <div class="field full" id="stateWrap">
                        <label for="initialState">Status Awal</label>
                        <select id="initialState">
                            <option value="ENTERING">✈️ ENTERING (Memasuki Stand)</option>
                            <option value="ON">🛑 BLOCK ON (Langsung parkir)</option>
                        </select>
                    </div>

                    <div class="field full box-danger" id="delayBox">
                        <div class="form-grid">
                            <div class="field">
                                <label for="delayMinutes" style="color:#fca5a5;">Menit Delay</label>
                                <input type="number" id="delayMinutes" min="1" placeholder="45">
                            </div>
                            <div class="field">
                                <label for="delayReason" style="color:#fca5a5;">Alasan Delay</label>
                                <select id="delayReason"></select>
                            </div>
                            <div class="field full">
                                <label for="delayNotes" style="color:#fca5a5;">Catatan Delay</label>
                                <input type="text" id="delayNotes" placeholder="Opsional">
                            </div>
                        </div>
                    </div>

                    <div class="field full">
                        <label>Kronologi Waktu</label>
                        <div class="timing">
                            <div class="field"><label for="eta">ETA</label><input type="time" id="eta"></div>
                            <div class="field"><label for="ata">ATA</label><input type="time" id="ata"></div>
                            <div class="field"><label for="etd">ETD</label><input type="time" id="etd"></div>
                            <div class="field"><label for="atd">ATD</label><input type="time" id="atd"></div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="runway">Runway Aktif</label>
                        <select id="runway">
                            <option value="">--- Belum ditentukan ---</option>
                            <option value="RWY 04">RWY 04</option>
                            <option value="RWY 22">RWY 22</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="taxiway">Taxiway</label>
                        <select id="taxiway">
                            <option value="">--- Belum ditentukan ---</option>
                            <option value="TWY A">TWY A</option>
                            <option value="TWY B">TWY B</option>
                        </select>
                    </div>

                    <div class="field full">
                        <label for="parkingStandId">Parking Stand</label>
                        <select id="parkingStandId"></select>
                    </div>

                    <div class="field full">
                        <label for="notes">Catatan Operasional</label>
                        <textarea id="notes" placeholder="Opsional"></textarea>
                    </div>
                </div>

                <div class="btn-row mt">
                    <button type="submit" class="btn btn-primary" id="btnSubmit">Kirim Instruksi Data</button>
                    <button type="button" class="btn" id="btnCancelEdit" style="display:none;">Batal Koreksi</button>
                    <button type="button" class="btn" id="btnRecommend">🎯 Rekomendasi Stand</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h3>Riwayat Pergerakan Apron</h3>
                <div class="row">
                    <input type="date" id="historyDate" style="width:auto;">
                    <span class="muted" id="historyMeta"></span>
                </div>
            </div>
            <div class="table-wrap history-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th><th>Stand</th><th>Flight</th><th>Rute</th><th>Jenis</th>
                            <th>Tipe</th><th>Reg</th><th>Status</th><th>ETA</th><th>ATA</th><th>ETD</th><th>ATD</th>
                            @can('movements.history.manage')
                                <th>Aksi</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody id="historyBody">
                        <tr><td colspan="{{ auth()->user()->can('movements.history.manage') ? 13 : 12 }}" class="empty">Memuat riwayat...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="resetModal">
        <div class="modal">
            <h3>Reset Semua Stand</h3>
            <p>Semua penerbangan aktif akan di-BLOCK OFF, ATD dicatat, dan seluruh stand dikosongkan. Aksi ini dicatat dalam audit trail dan memerlukan alasan.</p>
            <div class="field mb">
                <label for="resetReason">Alasan Reset (wajib)</label>
                <input type="text" id="resetReason" placeholder="Contoh: pergantian shift operasional">
            </div>
            <label style="display:flex;gap:8px;font-size:12px;color:var(--text-sub);text-transform:none;">
                <input type="checkbox" id="resetConfirm" style="width:auto;">
                Saya memahami seluruh stand akan dikosongkan.
            </label>
            <div class="actions">
                <button class="btn btn-sm" id="resetCancel">Batal</button>
                <button class="btn btn-red btn-sm" id="resetSubmit">Reset Sekarang</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="notifModal">
        <div class="modal">
            <h3>Notifikasi Operasional</h3>
            <div id="notifList" style="max-height:340px;overflow:auto;"></div>
            <div class="actions">
                <button class="btn btn-sm" id="notifMarkAll">Tandai semua dibaca</button>
                <button class="btn btn-sm" id="notifClose">Tutup</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="actionModal">
        <div class="modal">
            <h3 id="actionTitle">Konfirmasi Aksi</h3>
            <p id="actionMessage"></p>
            <div class="field mb" id="actionReasonWrap" style="display:none;">
                <label for="actionReason" id="actionReasonLabel">Alasan</label>
                <textarea id="actionReason" placeholder="Isi alasan tindakan ini..."></textarea>
            </div>
            <div class="actions">
                <button class="btn btn-sm" id="actionCancel">Batal</button>
                <button class="btn btn-sm btn-primary" id="actionConfirm">Konfirmasi</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="delayModal">
        <div class="modal">
            <h3>Catat Delay</h3>
            <p id="delayFlightInfo"></p>
            <div class="form-grid">
                <div class="field">
                    <label for="modalDelayMinutes">Durasi Delay (menit)</label>
                    <input type="number" id="modalDelayMinutes" min="1" max="2880" placeholder="45">
                </div>
                <div class="field">
                    <label for="modalDelayReason">Alasan Delay</label>
                    <select id="modalDelayReason"></select>
                </div>
                <div class="field full">
                    <label for="modalDelayNotes">Catatan Tambahan</label>
                    <input type="text" id="modalDelayNotes" placeholder="Opsional">
                </div>
            </div>
            <div class="actions">
                <button class="btn btn-sm" id="delayCancel">Batal</button>
                <button class="btn btn-red btn-sm" id="delaySubmit">Catat Delay</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="overrideModal">
        <div class="modal">
            <h3>Override Aturan Stand</h3>
            <p id="overrideMessage"></p>
            <div class="field mb">
                <label for="overrideReason">Alasan Override (wajib)</label>
                <textarea id="overrideReason" placeholder="Contoh: emergency diversion, tidak ada stand lain."></textarea>
            </div>
            <div class="field mb">
                <label for="overrideConfirm">Ketik <b>OVERRIDE</b> untuk konfirmasi</label>
                <input type="text" id="overrideConfirm" placeholder="OVERRIDE" autocomplete="off">
            </div>
            <div class="actions">
                <button class="btn btn-sm" id="overrideCancel">Batal</button>
                <button class="btn btn-red btn-sm" id="overrideSubmit">Simpan Override</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="recommendModal">
        <div class="modal">
            <h3>Rekomendasi Alokasi Stand</h3>
            <p>Rekomendasi bersifat informatif. Operator tetap menjadi pengambil keputusan akhir.</p>
            <div id="recommendList" style="max-height:360px;overflow:auto;"></div>
            <div class="actions">
                <button class="btn btn-sm" id="recommendClose">Tutup</button>
                <button class="btn btn-primary btn-sm" id="recommendApply">Gunakan Rekomendasi Teratas</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const state = {
        options: null,
        board: null,
        editingId: null,
        canReset: @json(auth()->user()->can('stands.reset')),
        canExport: @json(auth()->user()->can('reports.export')),
        canOverride: @json(auth()->user()->can('movements.override')),
        canManageHistory: @json(auth()->user()->can('movements.history.manage')),
        editSource: null,
        auto: { airline: false, aircraft: false },
        pendingOverride: null,
        bestRecommendation: null,
    };

    const pollMs = (parseInt(document.querySelector('meta[name="amcs-poll"]').content, 10) || 3) * 1000;
    const fallbackPollMs = (parseInt(document.querySelector('meta[name="amcs-poll-fallback"]').content, 10) || 15) * 1000;
    const $ = (id) => document.getElementById(id);
    const conn = AMCS.initConnection();
    let boardPollTimer = null;
    let realtimeConnected = false;

    function markOnline() {
        conn.online(realtimeConnected ? 'Realtime' : 'Polling');
    }

    /* ---------------- Options / form setup ---------------- */
    async function loadOptions() {
        const data = await AMCS.api('/options');
        state.options = data;

        const delayReasons = data.delay_reasons.map(o => `<option value="${o.value}">${AMCS.escapeHtml(o.label)}</option>`).join('');
        $('flightType').innerHTML = data.flight_types.map(o => `<option value="${o.value}">${AMCS.escapeHtml(o.label)}</option>`).join('');
        $('delayReason').innerHTML = delayReasons;
        $('modalDelayReason').innerHTML = delayReasons;
        $('aircraftCategory').innerHTML = data.aircraft_categories
            .filter(c => c.value !== 'OTHER')
            .map(c => `<option value="${c.value}"${c.value === 'NARROW_BODY' ? ' selected' : ''}>${AMCS.escapeHtml(c.label)}</option>`)
            .join('');
        $('airlineId').innerHTML = '<option value="">-- Pilih maskapai --</option>'
            + data.airlines.map(a => `<option value="${a.id}">${AMCS.escapeHtml(a.name)}</option>`).join('')
            + '<option value="MANUAL">Lainnya (ketik manual)...</option>';
        $('aircraftTypeId').innerHTML = '<option value="">-- Pilih tipe pesawat --</option>'
            + data.aircraft_types.map(a => `<option value="${a.id}" data-code="${AMCS.escapeHtml(a.code)}">${AMCS.escapeHtml(a.code)} — ${AMCS.escapeHtml(a.name)}</option>`).join('')
            + '<option value="MANUAL">Lainnya (ketik manual)...</option>';
        $('parkingStandId').innerHTML = data.parking_stands.map(s =>
            `<option value="${s.id}">${AMCS.escapeHtml(s.name)}${s.capacity_category ? ' (' + s.capacity_category + ')' : ''}</option>`
        ).join('');

        updateCapacityHint();

        if ($('flightNumber').value.trim()) {
            applyFlightAutofill();
        }
    }

    $('airlineId').addEventListener('change', () => {
        state.auto.airline = false;
        $('airlineManualWrap').style.display = $('airlineId').value === 'MANUAL' ? 'flex' : 'none';

        // Selecting an airline also offers its default aircraft type.
        const airline = (state.options?.airlines || []).find(a => String(a.id) === String($('airlineId').value));
        if (airline?.default_aircraft_type_id && (!$('aircraftTypeId').value || $('aircraftTypeId').value === 'MANUAL' || state.auto.aircraft)) {
            $('aircraftTypeId').value = String(airline.default_aircraft_type_id);
            $('aircraftManualWrap').style.display = 'none';
            $('aircraftManual').value = '';
            state.auto.aircraft = true;
        }

        updateCapacityHint();
    });
    $('aircraftTypeId').addEventListener('change', () => {
        state.auto.aircraft = false;
        $('aircraftManualWrap').style.display = $('aircraftTypeId').value === 'MANUAL' ? 'flex' : 'none';
        updateCapacityHint();
    });
    $('aircraftManual').addEventListener('input', updateCapacityHint);
    $('aircraftCategory').addEventListener('change', updateCapacityHint);
    $('parkingStandId').addEventListener('change', updateCapacityHint);

    /* Auto-fill maskapai + tipe pesawat dari awalan nomor penerbangan (PRD §2.1). */
    let autofillTimer = null;
    function scheduleFlightAutofill() {
        clearTimeout(autofillTimer);
        autofillTimer = setTimeout(applyFlightAutofill, 250);
    }

    function applyFlightAutofill() {
        if (!state.options) return;

        const raw = $('flightNumber').value.trim().toUpperCase();
        const prefix = raw.match(/^[A-Z]+/)?.[0] || '';

        const airline = prefix
            ? [...state.options.airlines]
                .filter(a => a.flight_prefix && prefix.startsWith(a.flight_prefix))
                .sort((a, b) => b.flight_prefix.length - a.flight_prefix.length)[0]
            : null;

        if (airline) {
            if (!$('airlineId').value || $('airlineId').value === 'MANUAL' || state.auto.airline) {
                $('airlineId').value = String(airline.id);
                $('airlineManualWrap').style.display = 'none';
                $('airlineManual').value = '';
                state.auto.airline = true;
            }

            if (airline.default_aircraft_type_id && (!$('aircraftTypeId').value || $('aircraftTypeId').value === 'MANUAL' || state.auto.aircraft)) {
                $('aircraftTypeId').value = String(airline.default_aircraft_type_id);
                $('aircraftManualWrap').style.display = 'none';
                $('aircraftManual').value = '';
                state.auto.aircraft = true;
            }

            $('flightAutofillHint').innerText = 'Terdeteksi ' + airline.name + (airline.default_aircraft_type ? ' · default ' + airline.default_aircraft_type.code : '') + '.';
            $('flightAutofillHint').style.color = 'var(--vacant-color)';
        } else {
            if (state.auto.airline) {
                $('airlineId').value = '';
                state.auto.airline = false;
            }
            if (state.auto.aircraft) {
                $('aircraftTypeId').value = '';
                state.auto.aircraft = false;
            }
            $('flightAutofillHint').innerText = 'Maskapai & tipe pesawat terisi otomatis dari awalan nomor penerbangan.';
            $('flightAutofillHint').style.color = 'var(--accent-color)';
        }

        updateCapacityHint();
    }

    $('flightNumber').addEventListener('input', scheduleFlightAutofill);

    function selectedAircraftCode() {
        if ($('aircraftTypeId').value === 'MANUAL') return ($('aircraftManual').value || '').toUpperCase();
        const opt = $('aircraftTypeId').selectedOptions[0];
        return opt ? (opt.dataset.code || '') : '';
    }

    function selectedAircraftCategory() {
        if ($('aircraftTypeId').value !== 'MANUAL') {
            const id = $('aircraftTypeId').value;
            return (state.options?.aircraft_types || []).find(t => String(t.id) === String(id))?.category || null;
        }

        return $('aircraftCategory').value || null;
    }

    const categoryRank = { LIGHT: 1, TURBOPROP: 2, PRIVATE_JET: 3, NARROW_BODY: 4, WIDE_BODY: 5, OTHER: 6 };

    function updateCapacityHint() {
        const stand = (state.options?.parking_stands || []).find(s => String(s.id) === String($('parkingStandId').value));
        const code = selectedAircraftCode();
        const category = selectedAircraftCategory();
        const hint = $('standCapacityHint');
        if (!stand) { hint.innerText = ''; return; }

        const allowed = (stand.aircraft_types || []).map(t => t.code);
        const tooLarge = allowed.length
            ? (code && !allowed.includes(code))
            : (category && stand.capacity_category && (categoryRank[category] || 0) > (categoryRank[stand.capacity_category] || 99));

        if (tooLarge) {
            hint.innerText = '⚠️ ' + (code || 'Tipe pesawat') + ' melebihi kapasitas ' + stand.name + '. Diperlukan override supervisor.';
            hint.style.color = '#f87171';
        } else {
            hint.innerText = allowed.length
                ? 'Stand ini menerima: ' + allowed.join(', ')
                : 'Kapasitas stand: ' + (stand.capacity_category || 'tanpa batasan eksplisit');
            hint.style.color = 'var(--accent-color)';
        }
    }

    /* ---------------- Stand board ---------------- */
    function standCard(stand) {
        const s = stand;
        const m = s.movement;
        const stateName = s.state;
        const borderClass = { ENTERING: 'border-approaching', ON: 'border-occupied', RON: 'border-ron', DELAY: 'border-delay' }[stateName] || '';
        const plane = m ? '<div class="plane live">✈</div>' : '<div class="plane">✈</div>';
        const limit = (s.allowed_aircraft || []).length && (s.allowed_aircraft.length <= 4)
            ? '<div class="stand-limit">MAX: ' + s.allowed_aircraft.join(' / ') + '</div>' : '';

        let actionBtns = '';
        if (m) {
            if (stateName === 'ENTERING') {
                actionBtns += `<button class="btn btn-amber btn-sm" data-act="on" data-id="${m.id}">🛑 BLOCK ON</button>`;
            }
            if (['ON', 'RON', 'DELAY'].includes(stateName)) {
                actionBtns += `<button class="btn btn-green btn-sm" data-act="off" data-id="${m.id}">🛫 BLOCK OFF</button>`;
            }
            if (stateName === 'ON') {
                actionBtns += `<button class="btn btn-sm" data-act="delay" data-id="${m.id}">⚠️ DELAY</button>`;
                actionBtns += `<button class="btn btn-sm" data-act="ron" data-id="${m.id}">🛠️ RON</button>`;
            }
            if (['DELAY', 'RON'].includes(stateName)) {
                actionBtns += `<button class="btn btn-sm" data-act="on" data-id="${m.id}">🛑 BLOCK ON</button>`;
            }
            actionBtns += `<button class="btn btn-sm" data-act="edit" data-id="${m.id}">✏️ Koreksi</button>`;
        } else {
            actionBtns += `<button class="btn btn-primary btn-sm" data-act="pick" data-stand="${s.id}">Pilih Stand</button>`;
        }

        const times = `<div class="times">
            <div class="blk"><span class="lbl">Arrival</span><div>ETA: ${m ? AMCS.escapeHtml(m.eta || '-') : '-'}</div><div>ATA: ${m ? AMCS.escapeHtml(m.ata || '-') : '-'}</div></div>
            <div class="blk r"><span class="lbl">Departure</span><div>ETD: ${m ? AMCS.escapeHtml(m.etd || '-') : '-'}</div><div>ATD: ${m ? AMCS.escapeHtml(m.atd || '-') : '-'}</div></div>
        </div>`;

        const delayNote = (m && stateName === 'DELAY')
            ? `<div class="delay-note">⚠️ DELAY ${m.delay_minutes || 0} MENIT — ${AMCS.escapeHtml(m.delay_reason || 'N/A')}</div>` : '';

        return `<div class="stand-card ${stateName === 'VACANT' ? 'vacant' : ''} ${borderClass}" data-stand="${s.id}">
            <div>
                <div class="stand-name">${AMCS.escapeHtml(s.name)}</div>
                ${limit}
                <div class="operator">${m ? AMCS.escapeHtml(m.airline || '-') + ' · ' + AMCS.escapeHtml(m.flight_type || '') : '&nbsp;'}</div>
            </div>
            ${plane}
            <div class="flight">${m ? AMCS.escapeHtml(m.flight_number) : '-'}</div>
            ${m ? `<span class="route">${AMCS.escapeHtml(m.route || '-')}</span>` : ''}
            <div class="ac-row">${m ? `<span class="chip">${AMCS.escapeHtml(m.aircraft_type || '-')}</span><span class="chip">${AMCS.escapeHtml(m.registration || '-')}</span>` : ''}</div>
            ${times}
            <div class="routing">RWY: ${m ? AMCS.escapeHtml(m.runway || '-') : '-'} | TWY: ${m ? AMCS.escapeHtml(m.taxiway || '-') : '-'}</div>
            ${delayNote}
            <div class="actions">${actionBtns}</div>
            <span class="status ${AMCS.statusClass(stateName)}">${AMCS.escapeHtml(s.state_label)}</span>
        </div>`;
    }

    function renderBoard() {
        const board = state.board;
        if (!board) return;
        $('standGrid').innerHTML = board.stands.map(standCard).join('');
        $('stat-active').innerText = board.counts.active;
        $('stat-ron').innerText = board.counts.ron;
    }

    $('standGrid').addEventListener('click', (event) => {
        const btn = event.target.closest('button[data-act]');
        if (btn) {
            const act = btn.dataset.act;
            const id = btn.dataset.id;
            if (act === 'on') transition(id, 'ON', btn);
            if (act === 'off') blockOff(id, btn);
            if (act === 'delay') delayMovement(id, btn);
            if (act === 'ron') transition(id, 'RON', btn);
            if (act === 'edit') loadIntoForm(id);
            if (act === 'pick') { $('parkingStandId').value = btn.dataset.stand; updateCapacityHint(); }
            return;
        }
        const card = event.target.closest('[data-stand]');
        if (card) {
            $('parkingStandId').value = card.dataset.stand;
            updateCapacityHint();
        }
    });

    /* ---------------- Quick actions & modals ---------------- */
    let actionHandler = null;

    function openActionModal(options) {
        actionHandler = options;
        $('actionTitle').innerText = options.title || 'Konfirmasi Aksi';
        $('actionMessage').innerText = options.message || '';
        $('actionConfirm').innerText = options.confirmLabel || 'Konfirmasi';
        $('actionConfirm').className = 'btn btn-sm ' + (options.confirmClass || 'btn-primary');
        $('actionReasonWrap').style.display = options.showReason ? 'flex' : 'none';
        $('actionReasonLabel').innerText = options.reasonLabel || 'Alasan';
        $('actionReason').value = '';
        $('actionReason').placeholder = options.reasonPlaceholder || 'Isi alasan tindakan ini...';
        $('actionModal').classList.add('open');
    }

    function closeActionModal() {
        $('actionModal').classList.remove('open');
        actionHandler = null;
    }

    $('actionCancel').addEventListener('click', closeActionModal);

    $('actionConfirm').addEventListener('click', async () => {
        if (!actionHandler) { closeActionModal(); return; }

        const handler = actionHandler;
        const reason = $('actionReason').value.trim();

        if (handler.requireReason && !reason) {
            AMCS.toast('Ditolak', handler.reasonLabel || 'Alasan wajib diisi.', 'error');
            return;
        }

        const btn = $('actionConfirm');
        btn.disabled = true;
        try {
            await handler.onConfirm({ reason });
            closeActionModal();
        } catch (error) {
            AMCS.errorToast(error, handler.errorTitle || 'Aksi gagal');
        } finally {
            btn.disabled = false;
        }
    });

    function transition(id, targetState) {
        const labels = { ON: 'BLOCK ON', RON: 'RON', DELAY: 'DELAY', OFF: 'BLOCK OFF' };
        const label = labels[targetState] || targetState;
        const messages = {
            ON: 'ATA akan dicatat dengan waktu server dan status menjadi BLOCK ON. Konfirmasi status pesawat.',
            RON: 'Pesawat akan ditandai menginap (RON) di stand ini sampai BLOCK OFF berikutnya.',
            DELAY: 'Status akan diubah menjadi DELAY.',
        };

        openActionModal({
            title: 'Konfirmasi ' + label,
            message: messages[targetState] || 'Lanjutkan perubahan status?',
            confirmLabel: label,
            confirmClass: targetState === 'RON' ? 'btn-blue' : 'btn-amber',
            errorTitle: 'Transisi gagal',
            onConfirm: async () => {
                const res = await AMCS.api('/movements/' + id + '/state', { method: 'PATCH', body: { state: targetState } });
                AMCS.toast('Berhasil', res.message || 'Status diperbarui.', 'success');
                (res.warnings || []).forEach(w => AMCS.toast('Peringatan', w, 'warning'));
                refresh();
            },
        });
    }

    function blockOff(id) {
        openActionModal({
            title: 'Konfirmasi BLOCK OFF',
            message: 'ATD akan dicatat dengan waktu server, stand dikosongkan menjadi VACANT, dan histori final disimpan. Lanjutkan?',
            confirmLabel: 'BLOCK OFF',
            confirmClass: 'btn-green',
            showReason: true,
            requireReason: false,
            reasonLabel: 'Alasan / catatan BLOCK OFF (opsional)',
            errorTitle: 'BLOCK OFF gagal',
            onConfirm: async ({ reason }) => {
                const res = await AMCS.api('/movements/' + id + '/block-off', {
                    method: 'POST',
                    body: { reason: reason || null },
                });
                AMCS.toast('Berhasil', res.message || 'BLOCK OFF tersimpan.', 'success');
                (res.warnings || []).forEach(w => AMCS.toast('Peringatan', w, 'warning'));
                refresh();
            },
        });
    }

    function delayMovement(id) {
        const movement = (state.board?.stands || []).map(s => s.movement).find(m => m && String(m.id) === String(id));
        $('delayFlightInfo').innerText = movement
            ? 'Flight ' + movement.flight_number + ' · ' + (movement.airline || '-') + ' · ' + (movement.aircraft_type || '-')
            : 'Catat durasi dan alasan keterlambatan.';
        $('modalDelayMinutes').value = 30;
        $('modalDelayReason').selectedIndex = 0;
        $('modalDelayNotes').value = '';
        $('delayModal').dataset.movementId = id;
        $('delayModal').classList.add('open');
        $('modalDelayMinutes').focus();
    }

    $('delayCancel').addEventListener('click', () => $('delayModal').classList.remove('open'));

    $('delaySubmit').addEventListener('click', async () => {
        const id = $('delayModal').dataset.movementId;
        const minutes = parseInt($('modalDelayMinutes').value, 10);
        const reason = $('modalDelayReason').value;

        if (!id) return;
        if (!minutes || minutes <= 0) {
            AMCS.toast('Ditolak', 'Durasi delay wajib diisi lebih dari 0 menit.', 'error');
            return;
        }

        const btn = $('delaySubmit');
        btn.disabled = true;
        try {
            const res = await AMCS.api('/movements/' + id + '/delay', {
                method: 'POST',
                body: {
                    delay_minutes: minutes,
                    delay_reason: reason || 'OTHER',
                    delay_notes: $('modalDelayNotes').value.trim() || null,
                },
            });
            AMCS.toast('Berhasil', res.message || 'Delay tercatat.', 'success');
            (res.warnings || []).forEach(w => AMCS.toast('Peringatan', w, 'warning'));
            $('delayModal').classList.remove('open');
            refresh();
        } catch (error) {
            AMCS.errorToast(error, 'Gagal mencatat delay');
        } finally {
            btn.disabled = false;
        }
    });

    /* Override supervisor (FR-06) with an explicit layered confirmation. */
    function openOverrideModal(message, onSubmit) {
        state.pendingOverride = onSubmit;
        $('overrideMessage').innerText = message || 'Alokasi ini melanggar aturan stand. Override hanya dapat dilakukan supervisor dengan alasan.';
        $('overrideReason').value = '';
        $('overrideConfirm').value = '';
        $('overrideModal').classList.add('open');
        $('overrideReason').focus();
    }

    function closeOverrideModal() {
        $('overrideModal').classList.remove('open');
        state.pendingOverride = null;
    }

    $('overrideCancel').addEventListener('click', closeOverrideModal);

    $('overrideSubmit').addEventListener('click', async () => {
        const reason = $('overrideReason').value.trim();
        const confirmation = $('overrideConfirm').value.trim().toUpperCase();

        if (!state.pendingOverride) return;
        if (!reason) {
            AMCS.toast('Ditolak', 'Alasan override wajib diisi.', 'error');
            return;
        }
        if (confirmation !== 'OVERRIDE') {
            AMCS.toast('Ditolak', 'Ketik OVERRIDE pada kolom konfirmasi.', 'error');
            return;
        }

        const handler = state.pendingOverride;
        const btn = $('overrideSubmit');
        btn.disabled = true;
        try {
            await handler(reason);
            closeOverrideModal();
        } catch (error) {
            AMCS.errorToast(error, 'Override gagal');
        } finally {
            btn.disabled = false;
        }
    });

    /* ---------------- Form: create & correction ---------------- */
    function formPayload() {
        const payload = {
            flight_number: $('flightNumber').value.trim().toUpperCase(),
            registration: $('registration').value.trim().toUpperCase() || null,
            origin: $('origin').value.trim().toUpperCase() || null,
            destination: $('destination').value.trim().toUpperCase() || null,
            flight_type: $('flightType').value,
            report_date: $('reportDate').value || null,
            parking_stand_id: $('parkingStandId').value ? parseInt($('parkingStandId').value, 10) : null,
            eta: $('eta').value || null,
            ata: $('ata').value || null,
            etd: $('etd').value || null,
            atd: $('atd').value || null,
            runway: $('runway').value || null,
            taxiway: $('taxiway').value || null,
            notes: $('notes').value || null,
        };

        if ($('airlineId').value === 'MANUAL') {
            if ($('airlineManual').value.trim()) payload.airline_name = $('airlineManual').value.trim();
        } else if ($('airlineId').value) {
            payload.airline_id = parseInt($('airlineId').value, 10);
        }

        if ($('aircraftTypeId').value === 'MANUAL') {
            if ($('aircraftManual').value.trim()) {
                payload.aircraft_type_name = $('aircraftManual').value.trim().toUpperCase();
                payload.aircraft_category = $('aircraftCategory').value || null;
            }
        } else if ($('aircraftTypeId').value) {
            payload.aircraft_type_id = parseInt($('aircraftTypeId').value, 10);
        }

        return payload;
    }

    $('movementForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = formPayload();
        const btn = $('btnSubmit');
        btn.disabled = true;

        try {
            if (state.editingId) {
                const historyDate = $('historyDate').value;
                const editEndpoint = state.editSource === 'history'
                    ? '/movements/' + state.editingId + '/history'
                    : '/movements/' + state.editingId;

                await AMCS.api(editEndpoint, { method: 'PUT', body: payload });
                AMCS.toast(
                    'Tersimpan',
                    state.editSource === 'history'
                        ? 'Koreksi histori pergerakan berhasil disimpan.'
                        : 'Koreksi data pergerakan berhasil disimpan.',
                    'success',
                );
                resetForm();
                $('historyDate').value = historyDate || AMCS.todayISO();
            } else {
                payload.state = $('initialState').value;
                const res = await AMCS.api('/movements', { method: 'POST', body: payload });
                AMCS.toast('Tersimpan', res.message || 'Pergerakan berhasil disimpan.', 'success');
                (res.warnings || []).forEach(w => AMCS.toast('Peringatan', w, 'warning'));
                clearEntryFields();
            }
            refresh();
        } catch (error) {
            if (state.canOverride && error.status === 422 && error.errors && error.errors.parking_stand_id) {
                // Supervisor/admin can override the stand rule through a modal
                // with a mandatory reason and layered confirmation.
                openOverrideModal(
                    error.message,
                    async (reason) => {
                        const overridePayload = Object.assign({}, payload, {
                            override: true,
                            override_reason: reason,
                        });

                        const editEndpoint = state.editingId
                            ? (state.editSource === 'history'
                                ? '/movements/' + state.editingId + '/history'
                                : '/movements/' + state.editingId)
                            : '/movements';

                        const res = await AMCS.api(editEndpoint, {
                            method: state.editingId ? 'PUT' : 'POST',
                            body: overridePayload,
                        });

                        AMCS.toast('Override tersimpan', res.message || 'Override berhasil.', 'warning');
                        (res.warnings || []).forEach(w => AMCS.toast('Peringatan', w, 'warning'));
                        resetForm();
                        refresh();
                    },
                );
            } else {
                AMCS.errorToast(error, 'Gagal menyimpan');
            }
        } finally {
            btn.disabled = false;
        }
    });

    function resetAutoFlags() {
        state.auto.airline = false;
        state.auto.aircraft = false;
        $('flightAutofillHint').innerText = 'Maskapai & tipe pesawat terisi otomatis dari awalan nomor penerbangan.';
        $('flightAutofillHint').style.color = 'var(--accent-color)';
    }

    function clearEntryFields() {
        ['flightNumber', 'registration', 'origin', 'destination', 'eta', 'ata', 'etd', 'atd', 'notes', 'delayMinutes', 'delayNotes'].forEach(id => $(id).value = '');
        $('airlineManual').value = '';
        $('aircraftManual').value = '';
        $('airlineManualWrap').style.display = 'none';
        $('aircraftManualWrap').style.display = 'none';
        $('delayBox').classList.remove('show');
        resetAutoFlags();
    }

    function resetForm() {
        $('editingId').value = '';
        state.editingId = null;
        state.editSource = null;
        $('movementForm').reset();
        const today = AMCS.todayISO();
        $('reportDate').value = today;
        $('historyDate').value = today;
        $('stateWrap').style.display = '';
        $('btnCancelEdit').style.display = 'none';
        $('formMode').innerText = 'Mode: buat pergerakan baru';
        $('formTitle').innerText = 'Input Pergerakan Penerbangan';
        $('standAlert').style.display = 'none';
        $('aircraftManualWrap').style.display = 'none';
        $('airlineManualWrap').style.display = 'none';
        resetAutoFlags();
        updateCapacityHint();
    }

    async function loadIntoForm(movementId, source = 'card') {
        try {
            const res = await AMCS.api('/movements/' + movementId);
            const m = res.data;
            state.editingId = m.id;
            state.editSource = source;
            $('editingId').value = m.id;
            $('flightNumber').value = m.flight_number || '';
            $('registration').value = m.registration || '';
            $('origin').value = m.origin || '';
            $('destination').value = m.destination || '';
            if (m.parking_stand_id) $('parkingStandId').value = m.parking_stand_id;
            $('eta').value = m.eta || '';
            $('ata').value = m.ata || '';
            $('etd').value = m.etd || '';
            $('atd').value = m.atd || '';
            $('runway').value = m.runway || '';
            $('taxiway').value = m.taxiway || '';
            $('notes').value = m.notes || '';
            $('reportDate').value = m.report_date || AMCS.todayISO();
            if (m.flight_type) $('flightType').value = m.flight_type;
            if (m.airline && m.airline.id) $('airlineId').value = m.airline.id;
            if (m.aircraft_type && m.aircraft_type.id) {
                $('aircraftTypeId').value = m.aircraft_type.id;
                $('aircraftManualWrap').style.display = 'none';
            } else if (m.aircraft_type && m.aircraft_type.code) {
                $('aircraftTypeId').value = 'MANUAL';
                $('aircraftManual').value = m.aircraft_type.code;
                $('aircraftCategory').value = m.aircraft_type.category || 'NARROW_BODY';
                $('aircraftManualWrap').style.display = 'flex';
            }
            state.auto.airline = false;
            state.auto.aircraft = false;

            $('stateWrap').style.display = 'none';
            $('btnCancelEdit').style.display = '';
            $('formMode').innerText = source === 'history'
                ? 'Mode: koreksi histori (Administrator)'
                : 'Mode: koreksi data';
            $('formTitle').innerText = (source === 'history' ? 'Koreksi Histori — ' : 'Koreksi Data — ') + m.flight_number;
            updateCapacityHint();
            window.scrollTo({ top: document.getElementById('movementForm').offsetTop - 120, behavior: 'smooth' });
        } catch (error) { AMCS.errorToast(error, 'Gagal memuat data'); }
    }

    $('btnCancelEdit').addEventListener('click', () => {
        const selectedHistoryDate = $('historyDate').value;
        resetForm();
        $('historyDate').value = selectedHistoryDate || AMCS.todayISO();
    });

    /* ---------------- Recommendation ---------------- */
    $('btnRecommend').addEventListener('click', async () => {
        try {
            const body = {
                report_date: $('reportDate').value || null,
                eta: $('eta').value || null,
                etd: $('etd').value || null,
                parking_stand_id: $('parkingStandId').value || null,
            };
            if ($('aircraftTypeId').value && $('aircraftTypeId').value !== 'MANUAL') {
                body.aircraft_type_id = parseInt($('aircraftTypeId').value, 10);
            } else if ($('aircraftManual').value) {
                body.aircraft_type_name = $('aircraftManual').value.trim().toUpperCase();
                body.aircraft_category = $('aircraftCategory').value || null;
            }

            const res = await AMCS.api('/stands/recommend', { method: 'POST', body });
            const ranked = res.data || [];
            const best = ranked.filter(s => s.recommended);
            state.bestRecommendation = best.length ? best[0].stand_id : null;

            $('recommendList').innerHTML = ranked.length
                ? ranked.map(s => `<div class="box-dashed" style="margin-bottom:8px;border-style:solid;${s.recommended ? '' : 'opacity:0.72;'}">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                            <b>${AMCS.escapeHtml(s.stand_name)}</b>
                            <span class="badge ${s.recommended ? 'green' : 'gray'}">${s.recommended ? 'DIREKOMENDASIKAN' : 'TIDAK SESUAI'} · skor ${s.score}</span>
                        </div>
                        <div class="muted" style="margin-top:5px;font-size:11px;">${AMCS.escapeHtml((s.reasons || []).join(' · ') || '-')}</div>
                        ${(s.warnings || []).length ? `<div style="margin-top:5px;font-size:11px;color:#f87171;">${AMCS.escapeHtml(s.warnings.join(' · '))}</div>` : ''}
                    </div>`).join('')
                : '<div class="empty">Tidak ada stand aktif yang dapat dievaluasi.</div>';

            $('recommendApply').disabled = !state.bestRecommendation;
            $('recommendModal').classList.add('open');
        } catch (error) { AMCS.errorToast(error, 'Rekomendasi gagal'); }
    });

    $('recommendClose').addEventListener('click', () => $('recommendModal').classList.remove('open'));

    $('recommendApply').addEventListener('click', () => {
        if (!state.bestRecommendation) return;
        $('parkingStandId').value = state.bestRecommendation;
        updateCapacityHint();
        $('recommendModal').classList.remove('open');
    });

    /* ---------------- History & stats ---------------- */
    async function loadDaily() {
        const date = $('historyDate').value || AMCS.todayISO();
        const historyColspan = state.canManageHistory ? 13 : 12;
        try {
            const res = await AMCS.api('/reports/daily', { query: { date } });
            $('stat-mov').innerText = res.totals.movements;
            $('stat-delay').innerText = res.totals.delays;
            $('historyMeta').innerText = res.totals.movements + ' pergerakan';

            if (!res.movements.length) {
                $('historyBody').innerHTML = `<tr><td colspan="${historyColspan}" class="empty">Belum ada pergerakan pada tanggal ini.</td></tr>`;
                return;
            }

            $('historyBody').innerHTML = res.movements.map(m => {
                const waktu = m.updated_at ? new Date(m.updated_at) : null;
                const timeStr = waktu ? waktu.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
                const stand = m.parking_stand ? m.parking_stand.name : '-';
                const actions = state.canManageHistory
                    ? `<td class="text-right" style="white-space:nowrap;">
                            <button class="btn btn-sm" data-history-edit="${m.id}" title="Koreksi histori">✏️</button>
                            <button class="btn btn-sm btn-red" data-history-delete="${m.id}" data-flight="${AMCS.escapeHtml(m.flight_number || '-')}" title="Hapus/arsipkan histori">🗑️</button>
                        </td>`
                    : '';

                return `<tr>
                    <td class="mono" style="white-space:nowrap;">${AMCS.escapeHtml(timeStr)}</td>
                    <td><b>${AMCS.escapeHtml(stand)}</b></td>
                    <td>${AMCS.escapeHtml(m.flight_number || '-')}</td>
                    <td style="color:#60a5fa;font-weight:700;">${AMCS.escapeHtml(m.route || '-')}</td>
                    <td>${AMCS.escapeHtml(m.flight_type || '-')}</td>
                    <td>${AMCS.escapeHtml(m.aircraft_type ? m.aircraft_type.code : '-')}</td>
                    <td style="color:#f59e0b;font-weight:700;">${AMCS.escapeHtml(m.registration || '-')}</td>
                    <td><span class="badge ${m.state === 'DELAY' ? 'red' : (m.state === 'OFF' ? 'green' : 'amber')}">${AMCS.escapeHtml(m.state_label || m.state || '-')}</span>${m.delay_minutes ? ' <span class="badge red">' + m.delay_minutes + 'm</span>' : ''}</td>
                    <td>${AMCS.escapeHtml(m.eta || '-')}</td>
                    <td>${AMCS.escapeHtml(m.ata || '-')}</td>
                    <td>${AMCS.escapeHtml(m.etd || '-')}</td>
                    <td>${AMCS.escapeHtml(m.atd || '-')}</td>
                    ${actions}
                </tr>`;
            }).join('');
        } catch (error) { AMCS.errorToast(error, 'Gagal memuat laporan harian'); }
    }

    $('historyBody').addEventListener('click', (event) => {
        const edit = event.target.closest('[data-history-edit]');
        if (edit) {
            loadIntoForm(edit.dataset.historyEdit, 'history');
            return;
        }

        const remove = event.target.closest('[data-history-delete]');
        if (remove) {
            deleteHistoryMovement(remove.dataset.historyDelete, remove.dataset.flight);
        }
    });

    function deleteHistoryMovement(id, flightNumber) {
        openActionModal({
            title: 'Hapus Riwayat Pergerakan',
            message: `Movement ${flightNumber || ''} akan diarsipkan (soft delete). Histori dan audit trail tetap tersimpan. Lanjutkan?`,
            confirmLabel: 'Hapus',
            confirmClass: 'btn-red',
            showReason: true,
            requireReason: true,
            reasonLabel: 'Alasan penghapusan (wajib)',
            errorTitle: 'Gagal menghapus riwayat',
            onConfirm: async ({ reason }) => {
                const res = await AMCS.api('/movements/' + id + '/history', {
                    method: 'DELETE',
                    body: { reason },
                });

                AMCS.toast('Terhapus', res.message || 'Riwayat pergerakan diarsipkan.', 'success');
                refresh();
            },
        });
    }

    $('historyDate').addEventListener('change', loadDaily);
    $('reportDate').addEventListener('change', loadDaily);

    /* ---------------- Export ---------------- */
    $('btnExport').addEventListener('click', () => {
        const date = $('historyDate').value || AMCS.todayISO();
        window.location = AMCS.apiUrl('/reports/export') + '?format=xlsx&date=' + encodeURIComponent(date);
    });

    /* ---------------- Reset modal ---------------- */
    if (state.canReset) {
        $('btnReset').addEventListener('click', () => $('resetModal').classList.add('open'));
        $('resetCancel').addEventListener('click', () => $('resetModal').classList.remove('open'));
        $('resetSubmit').addEventListener('click', async () => {
            const reason = $('resetReason').value.trim();
            const confirmFlag = $('resetConfirm').checked;
            if (!reason) { AMCS.toast('Ditolak', 'Alasan reset wajib diisi.', 'error'); return; }
            if (!confirmFlag) { AMCS.toast('Ditolak', 'Centang konfirmasi berlapis terlebih dahulu.', 'error'); return; }
            try {
                const res = await AMCS.api('/stands/reset', { method: 'POST', body: { reason, confirm: true } });
                AMCS.toast('Reset selesai', res.message, 'warning');
                $('resetModal').classList.remove('open');
                $('resetReason').value = '';
                $('resetConfirm').checked = false;
                refresh();
            } catch (error) { AMCS.errorToast(error, 'Reset gagal'); }
        });
    }

    /* ---------------- Notifications ---------------- */
    async function loadNotifications() {
        try {
            const res = await AMCS.api('/notifications');
            const count = res.unread || 0;
            const badge = $('notifCount');
            badge.innerText = count;
            badge.style.display = count > 0 ? '' : 'none';
            $('notifList').innerHTML = res.data.length
                ? res.data.map(n => `<div class="box-dashed" style="margin-bottom:8px;border-style:solid;">
                        <b>${AMCS.escapeHtml(n.title)}</b>
                        <div class="muted" style="margin:4px 0;">${AMCS.escapeHtml(n.message)}</div>
                        <small class="muted">${n.created_at ? new Date(n.created_at).toLocaleString('id-ID') : ''}</small>
                    </div>`).join('')
                : '<div class="empty">Tidak ada notifikasi.</div>';
        } catch (error) { /* silent */ }
    }

    $('btnNotifications').addEventListener('click', () => { $('notifModal').classList.add('open'); loadNotifications(); });
    $('notifClose').addEventListener('click', () => $('notifModal').classList.remove('open'));
    $('notifMarkAll').addEventListener('click', async () => {
        try { await AMCS.api('/notifications/read-all', { method: 'POST', body: {} }); loadNotifications(); } catch (e) {}
    });

    /* ---------------- Realtime + polling fallback ---------------- */
    async function refresh() {
        try {
            const [board] = await Promise.all([
                AMCS.api('/stands'),
                loadDaily(),
            ]);
            state.board = board;
            renderBoard();
            markOnline();
        } catch (error) {
            conn.offline('API terputus');
            AMCS.errorToast(error, 'Sinkronisasi gagal');
        }
    }

    async function pollBoard() {
        try {
            const board = await AMCS.api('/stands');
            state.board = board;
            renderBoard();
            markOnline();
        } catch (error) {
            conn.offline('API terputus');
        }
    }

    function setBoardPollInterval(ms) {
        clearInterval(boardPollTimer);
        boardPollTimer = setInterval(pollBoard, ms);
    }

    function startRealtime() {
        const started = window.AMCSRealtime && AMCSRealtime.connect(
            () => refresh(),
            (online) => {
                realtimeConnected = online;
                if (online) {
                    conn.online('Realtime');
                } else {
                    conn.online('Polling');
                }
                setBoardPollInterval(online ? fallbackPollMs : pollMs);
            },
        );

        setBoardPollInterval(started ? fallbackPollMs : pollMs);
    }

    async function init() {
        resetForm();
        try {
            await loadOptions();
        } catch (error) { AMCS.errorToast(error, 'Gagal memuat master data'); }
        await refresh();
        loadNotifications();
        startRealtime();
        setInterval(loadNotifications, 20000);
    }

    init();
})();
</script>
@endpush
