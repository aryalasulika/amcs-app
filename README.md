# AMCS — Apron Movement Control System

Aplikasi web untuk mengelola **alokasi parking stand**, **siklus status penerbangan**,
**histori/audit**, dan **pelaporan operasional apron** di bandara.

AMCS digunakan oleh:

- **AMC Operator** — mencatat dan memperbarui pergerakan pesawat.
- **ATC Viewer** — memantau kondisi parking stand dari tower.
- **Supervisor** — melakukan koreksi, override, reset, dan meninjau laporan.
- **Administrator** — mengelola pengguna, role, dan master data.

Aplikasi ini merupakan implementasi ulang dari versi HTML/JavaScript + Firebase
menjadi **Laravel + MySQL**, sesuai dokumen [`prd.md`](prd.md).

> **Status:** MVP operasional. 51 automated tests lulus (179 assertions).
> Panduan deploy produksi: [`DEPLOYMENT_UBUNTU_24.md`](DEPLOYMENT_UBUNTU_24.md).

---

## Daftar Isi

1. [Ringkasan Fitur](#1-ringkasan-fitur)
2. [Stack Teknologi](#2-stack-teknologi)
3. [Arsitektur](#3-arsitektur)
4. [Halaman Aplikasi](#4-halaman-aplikasi)
5. [Role & Hak Akses](#5-role--hak-akses)
6. [Aturan Operasional](#6-aturan-operasional)
7. [Model Data](#7-model-data)
8. [Realtime](#8-realtime)
9. [API](#9-api)
10. [Instalasi Lokal](#10-instalasi-lokal)
11. [Konfigurasi Environment](#11-konfigurasi-environment)
12. [Testing](#12-testing)
13. [Operasional & Pemeliharaan](#13-operasional--pemeliharaan)
14. [Struktur Proyek](#14-struktur-proyek)
15. [Keamanan](#15-keamanan)
16. [Batasan & Roadmap](#16-batasan--roadmap)
17. [Troubleshooting](#17-troubleshooting)
18. [Dokumentasi Terkait](#18-dokumentasi-terkait)

---

## 1. Ringkasan Fitur

- **Autentikasi & sesi** — login/logout web dan token API (Sanctum); akun nonaktif ditolak.
- **RBAC** — role dan permission pada route, controller, Form Request, dan Policy.
- **Dashboard AMC** — papan parking stand (5 stand pada data awal), form
  input/koreksi pergerakan, aksi cepat, rekomendasi stand, statistik harian,
  histori, notifikasi, reset, dan export.
- **Monitor ATC** — tampilan besar dan ringkas untuk tower, label `READY` /
  `APPROACHING`, terhubung realtime.
- **Master data** — parking stand, batasan tipe pesawat per stand, tipe pesawat,
  maskapai (termasuk awalan penerbangan dan tipe default), dan pengguna.
- **Input pergerakan** — validasi server-side untuk field wajib, format ICAO,
  format waktu, kapasitas stand, dan konflik jadwal.
- **Auto-fill penerbangan** — awalan nomor penerbangan (mis. `IW`) otomatis memilih
  maskapai dan tipe pesawat default yang dikonfigurasi pada master maskapai.
- **Tipe pesawat manual** — tipe di luar master data dapat diketik beserta
  kategorinya; sistem menyimpannya ke master data dan tetap memvalidasi kapasitas.
- **Alokasi dengan validasi** — menolak stand terisi, menolak tipe pesawat melebihi
  kapasitas, memberi peringatan tumpang tindih jadwal, dan menyediakan override
  supervisor dengan alasan.
- **Rekomendasi stand** — peringkat informatif berdasarkan kapasitas, status,
  jadwal, overlap, dan pilihan operator. Keputusan akhir tetap pada operator.
- **Siklus status** tervalidasi: `VACANT → ENTERING → ON → (DELAY | RON) → OFF`.
- **BLOCK ON / BLOCK OFF** — mencatat ATA/ATD dengan waktu server dan konfirmasi.
- **Delay** — durasi, alasan, catatan; notifikasi supervisor bila melewati ambang batas.
- **Realtime** — WebSocket Laravel Reverb + fallback polling terkontrol.
- **Audit trail permanen** — pelaku, waktu server, data sebelum/sesudah, alasan,
  dan IP. Tidak dapat diubah/dihapus dari aplikasi.
- **Histori & filter** — tanggal/rentang, nomor penerbangan, stand, maskapai,
  status, jenis penerbangan, dan pengguna.
- **Koreksi/hapus histori Dashboard AMC** — tombol edit dan hapus pada tabel
  riwayat pergerakan hanya tersedia untuk role **Administrator**. Edit memakai
  alur koreksi movement, sedangkan hapus melakukan arsip (soft delete) dengan
  alasan wajib dan audit trail tetap tersimpan.
- **Laporan** — rekap harian, bulanan, tahunan, penggunaan per stand, delay, RON,
  rata-rata turnaround; kartu rekap mengikuti filter aktif.
- **Export** — Excel multi-sheet dan CSV.
- **Reset massal** — hanya Supervisor/Administrator, dengan konfirmasi berlapis
  dan alasan wajib.
- **Notifikasi in-app** — konflik/kapasitas, delay panjang, override, dan reset.
- **Backup & retensi** — backup MySQL terjadwal dan arsip log aktivitas lama.
- **Import Firebase** — perintah satu kali untuk memindahkan data lama.
- **UI responsif** — topbar/navigasi, kartu stand, form, modal, statistik, dan
  monitor ATC menyesuaikan layar tablet/ponsel; tabel lebar dapat digeser
  horizontal tanpa merusak layout halaman.
- **Monitor ATC mobile** — topbar ringkas, tombol refresh/fullscreen, jam dan
  waktu sinkron, ringkasan status (terisi/delay/RON/kosong), status stand
  ditampilkan penuh di bagian atas kartu, serta informasi waktu dalam grid 2
  kolom agar mudah dipindai dari ponsel.

---

## 2. Stack Teknologi

| Komponen | Teknologi |
|---|---|
| Backend | Laravel 12 (PHP 8.2+, produksi disarankan PHP 8.3) |
| Database | MySQL 8 (`utf8mb4`) |
| Autentikasi | Session web + Laravel Sanctum (bearer token) |
| Otorisasi | Spatie Laravel Permission + Policies |
| Realtime | Laravel Reverb (WebSocket) + polling fallback |
| Export | Maatwebsite Excel (`xlsx`) + CSV |
| Frontend | Blade + CSS/JS vanilla, tanpa build step |
| Testing | PHPUnit 11 |
| Timezone / locale | `Asia/Makassar` / `id` |

> PRD menyebut Laravel 11; implementasi memakai **Laravel 12** agar tetap
> menerima patch keamanan.

---

## 3. Arsitektur

```text
Browser (Dashboard / ATC)
   |        |
   |        +---- WebSocket (Reverb) ----+
   |                                     |
   +---- HTTPS REST API /api/v1 -----> Laravel
                                          |
                                          +---- MySQL 8
                                          |
                                          +---- Queue worker (Supervisor)
                                          |
                                          +---- Scheduler (Cron)
```

### Alur request

1. Nginx meneruskan request PHP ke PHP-FPM.
2. Middleware `auth` + `active` memvalidasi sesi/pengguna.
3. Form Request memvalidasi input.
4. Policy/authorize memeriksa permission.
5. Service menjalankan use-case dalam **transaksi MySQL** dengan `lockForUpdate`
   pada stand/movement yang relevan.
6. Perubahan movement, reservasi, log, dan audit disimpan.
7. Setelah commit, event `StandBoardUpdated` dikirim ke Reverb.
8. Dashboard/ATC menerima event dan mengambil papan stand terbaru dari API.

### Konsistensi data

- Perubahan stand dan log berjalan dalam satu transaksi.
- Row lock pada parking stand mencegah dua operator memakai stand yang sama.
- Waktu operasional menggunakan waktu server aplikasi.
- Audit trail bersifat append-only pada level model.

---

## 4. Halaman Aplikasi

### 4.1 Dashboard AMC (`/dashboard`)

- Papan visual parking stand (5 stand pada data awal) beserta status, penerbangan,
  maskapai, tipe pesawat, registrasi, rute ICAO, ETA/ATA/ETD/ATD, runway,
  taxiway, dan delay.
- Form input pergerakan baru dan mode koreksi.
- Auto-fill maskapai/tipe pesawat dari awalan flight number.
- Pilihan tipe pesawat manual + kategori.
- Rekomendasi stand dalam modal.
- Aksi cepat **BLOCK ON**, **BLOCK OFF**, **DELAY**, **RON** dengan konfirmasi modal.
- Statistik harian: total pergerakan, pesawat aktif, RON, dan delay.
- Histori pergerakan berdasarkan tanggal.
- Notifikasi in-app.
- Export laporan.
- Reset semua stand (role berwenang).

### 4.2 Monitor ATC (`/atc-monitor`)

- Tampilan besar untuk layar tower.
- Satu kartu per stand aktif (5 kartu pada data awal) berisi flight number,
  tipe pesawat, maskapai, registrasi, rute, waktu, runway/taxiway, dan status.
- Label ATC:
  - `VACANT` → **READY**
  - `ENTERING` → **APPROACHING**
  - Status lain mengikuti label operasional (`BLOCK ON`, `DELAYED`, `RON`, `BLOCK OFF`).
- Indikator koneksi `Realtime` / `Polling` dan waktu sinkron terakhir.

### 4.3 Laporan (`/reports`)

- Filter rentang tanggal, nomor penerbangan, stand, status, dan jenis penerbangan.
- Kartu rekap mengikuti filter aktif:
  total pergerakan, total delay, total RON, total menit delay.
- Tabel histori dengan pagination.
- Export Excel (`.xlsx`) dan CSV.

### 4.4 Admin

| Halaman | Fungsi |
|---|---|
| `/admin/stands` | Master parking stand, kategori kapasitas, area, posisi, whitelist tipe pesawat |
| `/admin/aircraft-types` | Master tipe pesawat: kode, nama, kategori, wingspan, berat |
| `/admin/airlines` | Master maskapai: kode, nama, awalan penerbangan, tipe pesawat default |
| `/admin/users` | Pengguna, status aktif, role |
| `/admin/audit-logs` | Audit trail read-only dengan filter |

---

## 5. Role & Hak Akses

| Role | Hak utama |
|---|---|
| **Administrator** | Semua fitur, master data, pengguna, audit, konfigurasi |
| **Supervisor** | Pergerakan, override, reset massal, koreksi, laporan, audit |
| **AMC Operator** | Membuat/memperbarui pergerakan, BLOCK ON/OFF, delay, laporan |
| **ATC Viewer** | Melihat papan stand dan monitor ATC, histori/laporan read-only |

Permission granular:

```text
stands.view              movements.view           reports.view
stands.reset             movements.create         reports.export
movements.update         movements.delete         history.view
movements.override       movements.history.manage audit.view
master-data.manage       users.manage             notifications.view
```

`movements.history.manage` hanya diberikan kepada role **Administrator** dan
digunakan untuk edit/hapus pada tabel riwayat Dashboard AMC.

Aturan penting:

- Operator biasa **tidak dapat** melakukan override, reset massal, atau menghapus audit.
- Override hanya untuk Supervisor/Administrator dan wajib alasan.
- Reset massal hanya untuk Supervisor/Administrator dengan `confirm` + alasan.
- Akun nonaktif tidak dapat login dan sesi aktifnya ditolak middleware `active`.

---

## 6. Aturan Operasional

### 6.1 Status penerbangan

```text
VACANT
   |
   v
ENTERING
   |
   v
ON / BLOCK ON
   |
   +--> DELAY
   |
   +--> RON
   |
   v
OFF / BLOCK OFF
   |
   v
VACANT
```

Transisi yang diizinkan:

| Dari | Ke |
|---|---|
| `ENTERING` | `ON`, `OFF` |
| `ON` | `DELAY`, `RON`, `OFF` |
| `DELAY` | `ON`, `RON`, `OFF` |
| `RON` | `ON`, `DELAY`, `OFF` |
| `OFF` | — (final) |

- Movement baru dapat dimulai sebagai `ENTERING` atau langsung `ON`.
- `BLOCK ON` mencatat **ATA** dengan waktu server.
- `BLOCK OFF` mencatat **ATD**, menutup movement, dan mengosongkan stand.
- Delay mencatat durasi, alasan, dan catatan. Delay ≥ ambang batas
  (`AMCS_DELAY_THRESHOLD_MINUTES`, default 30 menit) memicu notifikasi supervisor.
- Transisi tidak valid ditolak kecuali override supervisor.

### 6.2 Kapasitas parking stand

Data awal (seeder):

| Stand | Kapasitas | Aturan |
|---|---|---|
| `STAND-1` | Turboprop | Hanya `ATR72`, `ATR42`, `C208`, `DHC6` |
| `STAND-2` — `STAND-5` | Narrow-body | Mengikuti kategori kapasitas, bukan whitelist eksplisit |

- Stand 1 menolak `A320`, `A321`, `B737-800`, `B737-900ER`, dan tipe yang tidak ada
  dalam whitelist.
- Stand 2–5 menerima tipe dengan kategori ≤ `NARROW_BODY`
  (`LIGHT`, `TURBOPROP`, `PRIVATE_JET`, `NARROW_BODY`).
- Tipe `WIDE_BODY` dan `OTHER` ditolak pada stand narrow-body kecuali override.
- Master data dapat diubah administrator; perubahan tercatat pada audit trail.

### 6.3 Alokasi & override

- Satu stand hanya boleh dipakai satu movement aktif.
- Sistem memeriksa benturan jadwal (`stand_reservations`) dan menampilkan
  peringatan bila overlap.
- Rekomendasi stand bersifat informatif, bukan keputusan otomatis.
- Override aturan stand memerlukan:
  - permission `movements.override`;
  - alasan override;
  - konfirmasi berlapis pada UI (mengetik `OVERRIDE`).

### 6.4 Reset, arsip, dan audit

- **Reset massal** menutup semua movement aktif menjadi `OFF`, mencatat ATD,
  membatalkan reservasi, dan menulis audit.
- **Hapus movement** dilakukan sebagai **soft delete/arsip**; audit dan histori
  tetap tersimpan.
- **Audit trail** tidak dapat diubah atau dihapus melalui aplikasi.
- **Movement logs** dapat diarsipkan ke JSON setelah masa retensi; audit logs
  tidak ikut diarsipkan oleh perintah tersebut.

### 6.5 Waktu dan tanggal

- `report_date` menentukan tanggal operasional laporan.
- Waktu ATA/ATD menggunakan waktu server, bukan waktu perangkat operator.
- Timezone aplikasi: `Asia/Makassar` (WITA).

---

## 7. Model Data

```text
users
  id, name, email, password, active, last_login_at, timestamps

roles / permissions / model_has_roles / role_has_permissions   (Spatie)

airlines
  id, code, name, flight_prefix, default_aircraft_type_id, active, timestamps

aircraft_types
  id, code, name, category, wingspan, max_weight, active, timestamps

parking_stands
  id, code, name, active, capacity_category, gate_area,
  position_x, position_y, notes, timestamps

parking_stand_aircraft_types
  parking_stand_id, aircraft_type_id, timestamps

movements
  id, report_date, flight_number, airline_id, airline_name,
  registration, aircraft_type_id, aircraft_type_name, flight_type,
  origin, destination, parking_stand_id, state,
  eta, ata, etd, atd, runway, taxiway,
  delay_minutes, delay_reason, delay_notes, notes,
  override_reason, created_by, updated_by, closed_at,
  timestamps, deleted_at (soft delete)

stand_reservations
  id, movement_id, parking_stand_id, start_time, end_time, status, timestamps

movement_logs
  id, movement_id, stand_code, flight_number, action, payload, created_by, created_at

audit_logs
  id, user_id, user_name, action, entity_type, entity_id,
  before_data, after_data, reason, ip_address, created_at

notifications
  id, type, notifiable_type, notifiable_id, data, read_at, timestamps
```

Enum penting:

- **Movement state:** `VACANT`, `ENTERING`, `ON`, `DELAY`, `RON`, `OFF`
- **Flight type:** `SCHEDULED`, `CHARTER`, `MILITARY`, `MEDIVAC`, `CARGO`, `TRAINING`
- **Aircraft category:** `NARROW_BODY`, `WIDE_BODY`, `TURBOPROP`, `PRIVATE_JET`,
  `LIGHT`, `OTHER`

---

## 8. Realtime

### 8.1 Mekanisme

- Dashboard dan monitor ATC subscribe ke **private channel** `amcs.stands`.
- Setiap perubahan operasional memicu event `App\Events\StandBoardUpdated`
  dengan `broadcastAs` `stand-board.updated`.
- Payload event kecil (`stand_id`, `movement_id`, `action`, `occurred_at`);
  klien kemudian mengambil papan terbaru melalui `GET /api/v1/stands`.
- Channel hanya dapat diakses pengguna aktif dengan permission `stands.view`.
- Server mem-broadcast langsung ke Reverb lewat loopback (`127.0.0.1:8080`).
- Browser terhubung melalui reverse proxy HTTPS `/app/` di produksi.

### 8.2 Fallback polling

| Kondisi | Interval |
|---|---|
| WebSocket terhubung | `AMCS_REALTIME_FALLBACK_POLL_SECONDS` (default 15 detik) |
| WebSocket terputus | `AMCS_REALTIME_POLL_SECONDS` (default 3 detik) |

Indikator koneksi di topbar menampilkan **`Realtime`** atau **`Polling`**.
Dengan demikian, target pembaruan ≤ 3 detik tetap terpenuhi meski WebSocket
sedang bermasalah.

### 8.3 Menjalankan Reverb

```bash
php artisan reverb:start
```

Di produksi jalankan melalui Supervisor/systemd. Konfigurasi lengkap ada di
[`DEPLOYMENT_UBUNTU_24.md`](DEPLOYMENT_UBUNTU_24.md).

### 8.4 Variabel realtime utama

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=https://amcs.example.com

AMCS_REALTIME_BROADCAST=true
AMCS_REALTIME_POLL_SECONDS=3
AMCS_REALTIME_FALLBACK_POLL_SECONDS=15

# Alamat publik yang dipakai browser
AMCS_REVERB_CLIENT_HOST=amcs.example.com
AMCS_REVERB_CLIENT_PORT=443
AMCS_REVERB_CLIENT_SCHEME=https
```

---

## 9. API

Semua endpoint menggunakan prefix `/api/v1`.

Autentikasi:

- **Cookie sesi** untuk dashboard/monitor first-party.
- **Bearer token** untuk klien eksternal:

```http
Authorization: Bearer <token>
Accept: application/json
```

### 9.1 Autentikasi

| Method | Endpoint | Keterangan |
|---|---|---|
| `POST` | `/auth/login` | Login, mengembalikan token (throttle 10/menit) |
| `GET` | `/auth/me` | Profil pengguna aktif |
| `POST` | `/auth/logout` | Logout / cabut token |

Contoh login:

```bash
curl -s -X POST https://amcs.example.com/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"operator@amcs.local","password":"Amcs#2026"}'
```

### 9.2 Papan stand & alokasi

| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/stands` | Papan stand realtime + hitungan |
| `GET` | `/stands/available` | Stand aktif dan kosong |
| `POST` | `/stands/recommend` | Rekomendasi stand |
| `POST` | `/stands/reset` | Reset massal (Supervisor/Admin) |
| `GET` | `/stands/{stand}` | Detail satu stand |

### 9.3 Movement

| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/movements` | Daftar movement (filter + pagination) |
| `POST` | `/movements` | Buat movement |
| `GET` | `/movements/{movement}` | Detail movement |
| `PUT/PATCH` | `/movements/{movement}` | Koreksi movement |
| `DELETE` | `/movements/{movement}` | Arsipkan movement (soft delete) |
| `PUT/PATCH` | `/movements/{movement}/history` | Koreksi histori (Administrator) |
| `DELETE` | `/movements/{movement}/history` | Hapus/arsip histori (Administrator) |
| `PATCH` | `/movements/{movement}/state` | Transisi status |
| `POST` | `/movements/{movement}/block-on` | BLOCK ON (isi ATA) |
| `POST` | `/movements/{movement}/block-off` | BLOCK OFF (isi ATD) |
| `POST` | `/movements/{movement}/delay` | Catat delay |
| `GET` | `/movements/{movement}/logs` | Log per movement |

Field penting saat membuat movement:

```json
{
  "flight_number": "IW-114",
  "airline_id": 4,
  "registration": "PK-WGO",
  "aircraft_type_id": 1,
  "aircraft_type_name": "ATR72",
  "aircraft_category": "TURBOPROP",
  "flight_type": "SCHEDULED",
  "origin": "WAAA",
  "destination": "WAMW",
  "parking_stand_id": 1,
  "state": "ENTERING",
  "report_date": "2026-10-17",
  "eta": "10:00",
  "etd": "11:00",
  "runway": "RWY 04",
  "taxiway": "TWY A"
}
```

Catatan:

- `aircraft_type_id` untuk tipe dari master data.
- `aircraft_type_name` + `aircraft_category` untuk input manual.
- `override` + `override_reason` hanya untuk Supervisor/Admin.
- Format waktu `HH:MM`, origin/destination 4 huruf ICAO.

### 9.4 Laporan

| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/reports/daily?date=YYYY-MM-DD` | Rekap harian |
| `GET` | `/reports/monthly?year=&month=` | Rekap bulanan |
| `GET` | `/reports/summary` | Totals terfilter + rekap tahunan |
| `GET` | `/reports/history` | Histori terfilter + pagination |
| `GET` | `/reports/activity` | Log aktivitas |
| `GET` | `/reports/export?format=xlsx\|csv` | Export laporan |

Filter yang didukung: `date`, `date_from`, `date_to`, `flight_number`,
`parking_stand_id`, `airline_id`, `state`, `flight_type`, `user_id`.

### 9.5 Master data & pendukung

| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/options` | Data referensi form |
| `GET` | `/audit-logs` | Audit trail read-only |
| `GET` | `/notifications` | Notifikasi pengguna |
| `POST` | `/notifications/read-all` | Tandai semua dibaca |
| `POST` | `/notifications/{id}/read` | Tandai satu dibaca |
| CRUD | `/parking-stands` | Master stand (Admin) |
| CRUD | `/aircraft-types` | Master tipe pesawat (Admin) |
| CRUD | `/airlines` | Master maskapai (Admin) |
| CRUD | `/users` | Pengguna (Admin) |
| `GET` | `/roles` | Daftar role |

Pagination menggunakan `page` dan `per_page` (maksimal 100 untuk sebagian besar
endpoint). Respons error domain menggunakan HTTP `422`/`403` dengan
`message` dan `errors`.

---

## 10. Instalasi Lokal

### 10.1 Prasyarat

- PHP **8.2+** (disarankan 8.3)
- Ekstensi PHP: `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `bcmath`, `intl`
- Composer 2
- MySQL 8
- Node.js **opsional** untuk menjalankan aplikasi; diperlukan jika memakai
  `composer dev` (Vite/concurrently) atau memperbarui aset Echo/Pusher

### 10.2 Langkah instalasi

```bash
cd amcs-app

# 1. Dependency PHP
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate
```

Sesuaikan kredensial MySQL pada `.env`, lalu buat database:

```sql
CREATE DATABASE amcs
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE amcs_testing
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

```bash
# 3. Migrasi + role/master data + akun contoh
php artisan migrate --seed
```

Jalankan aplikasi dan WebSocket:

```bash
php artisan serve            # http://127.0.0.1:8000
php artisan reverb:start     # ws://localhost:8080
```

Atau semua proses sekaligus:

```bash
composer dev
```

`composer dev` menjalankan server Laravel, queue listener, log viewer,
Reverb, dan Vite.

### 10.3 Menjalankan di Laragon

Proyek dapat diakses dari root domain maupun sub-folder:

- Root domain: arahkan document root virtual host ke `amcs-app/public`.
- Sub-folder: mis. `http://amcs.test/amcs-app/public`.

`AppServiceProvider` otomatis menyesuaikan URL aset, route, dan endpoint API
dengan host/base yang sedang dipakai. Untuk realtime, pastikan
`php artisan reverb:start` berjalan dan `REVERB_PORT` sesuai.

### 10.4 Aset frontend

Aplikasi **tidak memerlukan build step** untuk berjalan. CSS/JS berada di
`public/`, sedangkan klien Echo/Pusher di `public/vendor/`.

Jika ingin memperbarui klien realtime:

```bash
npm ci
npm run vendor:realtime
```

### 10.5 Akun contoh (hasil seeder)

| Role | Email | Kata sandi default |
|---|---|---|
| Administrator | `admin@amcs.local` | `Amcs#2026` |
| Supervisor | `supervisor@amcs.local` | `Amcs#2026` |
| AMC Operator | `operator@amcs.local` | `Amcs#2026` |
| ATC Viewer | `atc@amcs.local` | `Amcs#2026` |

Password dapat diubah melalui `AMCS_SEED_PASSWORD` sebelum menjalankan seeder.

> **Jangan gunakan akun/password contoh di produksi.**

---

## 11. Konfigurasi Environment

Variabel penting pada `.env`:

| Variabel | Default | Keterangan |
|---|---|---|
| `APP_ENV` | `local` | Gunakan `production` di server |
| `APP_DEBUG` | `true` | Set `false` di produksi |
| `APP_URL` | `http://amcs.test` | URL aplikasi |
| `APP_TIMEZONE` | `Asia/Makassar` | Timezone server aplikasi |
| `APP_LOCALE` | `id` | Bahasa aplikasi |
| `DB_*` | — | Koneksi MySQL |
| `SESSION_DRIVER` | `database` | Sesi disimpan di database |
| `SESSION_SECURE_COOKIE` | — | Set `true` di HTTPS produksi |
| `SANCTUM_STATEFUL_DOMAINS` | — | Domain first-party untuk cookie API |
| `CACHE_STORE` | `database` | Cache Laravel |
| `QUEUE_CONNECTION` | `database` | Queue worker |
| `BROADCAST_CONNECTION` | `reverb` | Driver broadcasting |
| `REVERB_*` | — | Konfigurasi server Reverb |
| `AMCS_REVERB_CLIENT_*` | — | Alamat publik WebSocket untuk browser |
| `AMCS_REALTIME_BROADCAST` | `true` | Aktif/nonaktifkan broadcast event |
| `AMCS_REALTIME_POLL_SECONDS` | `3` | Polling saat socket terputus |
| `AMCS_REALTIME_FALLBACK_POLL_SECONDS` | `15` | Polling saat socket terhubung |
| `AMCS_AIRPORT_NAME` | Bandara Luwuk | Nama bandara |
| `AMCS_AIRPORT_ICAO` | `WAMW` | Kode ICAO |
| `AMCS_DELAY_THRESHOLD_MINUTES` | `30` | Ambang notifikasi delay |
| `AMCS_SEED_PASSWORD` | `Amcs#2026` | Password akun demo |

Konfigurasi aplikasi berada di `config/amcs.php`, `config/broadcasting.php`,
dan `config/reverb.php`.

---

## 12. Testing

```bash
php artisan test
```

Cakupan saat ini: **51 tes, 179 assertions**.

Area yang diuji:

- Login, logout, akun nonaktif, dan proteksi endpoint.
- Role/permission: operator, supervisor, ATC viewer, administrator.
- Pembuatan movement, stand terisi, kapasitas pesawat, override.
- Tipe pesawat manual + kategori.
- Auto-fill maskapai/tipe default.
- Transisi status, BLOCK ON/OFF, delay, pembatalan ENTERING.
- Audit trail dan immutability.
- Reset massal ber-otorisasi.
- Edit/hapus histori movement khusus Administrator (dan penolakan role lain).
- Realtime event broadcast.
- Laporan harian/bulanan, filter rekap, export CSV/XLSX.
- Papan stand dan hitungan RON/aktif.

Konfigurasi test menggunakan database MySQL `amcs_testing` (lihat `phpunit.xml`).
Pastikan database tersebut sudah dibuat sebelum menjalankan test.

---

## 13. Operasional & Pemeliharaan

### 13.1 Perintah penting

```bash
php artisan optimize:clear        # bersihkan cache config/route/view/event
php artisan optimize              # cache untuk produksi
php artisan queue:restart         # restart queue worker setelah deploy
php artisan schedule:list         # daftar jadwal
php artisan schedule:work         # jalankan scheduler di lokal
```

### 13.2 Backup

```bash
php artisan amcs:backup-database --keep=30
```

- Menyimpan hasil `mysqldump` ke `storage/app/backups`.
- Menghapus backup lebih lama dari 30 hari.
- Dijadwalkan setiap hari pukul **02:00** melalui scheduler.

### 13.3 Arsip log aktivitas

```bash
php artisan amcs:archive-activity --days=1095 --dry-run
php artisan amcs:archive-activity --days=1095
```

- Mengekspor `movement_logs` lama ke `storage/app/archive/*.json`.
- Setelah itu menghapus log tersebut dari database.
- `audit_logs` tidak disentuh.
- Dijadwalkan setiap tanggal 1 pukul **03:00**.

### 13.4 Import Firebase

```bash
php artisan amcs:import-firebase data.json --dry-run
php artisan amcs:import-firebase data.json
```

Membaca export JSON dengan node `apron_stands` dan `logs`.

### 13.5 Scheduler

Produksi membutuhkan cron:

```cron
* * * * * www-data cd /var/www/amcs && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### 13.6 Log

| Log | Lokasi |
|---|---|
| Laravel | `storage/logs/laravel.log` |
| Queue worker | `storage/logs/queue-worker.log` (konfigurasi Supervisor) |
| Reverb | `storage/logs/reverb.log` (konfigurasi Supervisor) |
| Nginx | `/var/log/nginx/amcs.access.log`, `amcs.error.log` |

### 13.7 Deploy produksi

Langkah lengkap tersedia di
[`DEPLOYMENT_UBUNTU_24.md`](DEPLOYMENT_UBUNTU_24.md), termasuk Nginx,
PHP-FPM, MySQL, HTTPS, Supervisor, Reverb, scheduler, firewall, dan
troubleshooting.

---

## 14. Struktur Proyek

```text
app/
  Console/Commands/       backup, archive, import Firebase
  Enums/                  MovementState, AircraftCategory, FlightType
  Events/                 StandBoardUpdated
  Exceptions/             OperationException
  Exports/                export Excel/CSV
  Http/
    Controllers/Api/V1/   REST API
    Controllers/Web/      dashboard, ATC, laporan, admin, home redirect
    Middleware/           EnsureUserIsActive
    Requests/             validasi form
    Resources/            bentuk JSON
  Models/                 Eloquent
  Notifications/          OperationalAlert
  Policies/               otorisasi
  Services/               alokasi stand, movement, audit, laporan, notifikasi
config/
  amcs.php                konfigurasi operasional & realtime
  broadcasting.php        koneksi Reverb
  reverb.php              server & app Reverb
database/
  factories/ seeders/ migrations/
public/
  css/, js/               aset frontend tanpa build step
  js/amcs-realtime.js     klien WebSocket
  vendor/                 Echo + Pusher (disalin dari npm)
resources/views/          Blade
routes/
  api.php                 REST API v1
  web.php                 halaman web
  channels.php            otorisasi channel realtime
  console.php             scheduler
tests/Feature/            test fitur
prd.md                    Product Requirements Document
DEPLOYMENT_UBUNTU_24.md   panduan deploy produksi
```

---

## 15. Keamanan

- Semua data operasional memerlukan autentikasi.
- Otorisasi berlapis: middleware, Form Request, Policy, dan permission.
- Validasi utama dilakukan server-side, bukan hanya JavaScript.
- Password di-hash dengan cast `hashed` Laravel.
- Akun nonaktif ditolak saat login dan saat memakai sesi lama.
- Audit trail tidak dapat diubah/dihapus dari aplikasi.
- Edit/hapus pada riwayat Dashboard AMC dibatasi permission
  `movements.history.manage` yang hanya dimiliki Administrator.
- Secret (DB, Reverb, APP_KEY) disimpan di `.env`, bukan source code.
- Transaksi dan row lock mencegah alokasi ganda.
- Realtime channel bersifat privat dan memeriksa permission.
- Gunakan HTTPS dan `SESSION_SECURE_COOKIE=true` di produksi.
- Batasi `REVERB_ALLOWED_ORIGINS` ke domain aplikasi.
- Jangan membuka port MySQL `3306` atau Reverb `8080` ke publik.

---

## 16. Batasan & Roadmap

### Batasan saat ini

- Alokasi stand masih **manual**; rekomendasi bersifat informatif.
- Aplikasi dirancang untuk **satu bandara** (Luwuk/WAMW).
- Belum ada integrasi FIDS, radar, ADS-B, atau sistem penerbangan eksternal.
- Notifikasi hanya **in-app (database)**, belum email/WhatsApp/push.
- RON tidak otomatis terbawa ke tanggal operasional berikutnya.
- Realtime memakai WebSocket Reverb dengan fallback polling; belum ada
  horizontal scaling Reverb/Redis.
- Histori memakai pagination; belum ada arsip analitik jangka panjang.
- Belum ada alur approval terpisah untuk koreksi; mengandalkan role/permission
  dan audit trail.

### Kandidat pengembangan berikutnya

- Integrasi flight information system.
- Notifikasi eksternal (email/WhatsApp/Telegram).
- Dashboard analitik dan tren delay.
- Multi-bandara / multi-apron.
- Reverb scaling dengan Redis untuk beberapa node.
- Penandatanganan/approval supervisor untuk koreksi kritis.
- Pengujian performa dan observability produksi.

---

## 17. Troubleshooting

### "Gagal memuat master data"

- Pastikan sudah login (bukan hanya membuka URL).
- Cek tab Network: `401` berarti sesi tidak terkirim, `404` berarti base URL salah.
- Muat ulang halaman setelah login.

### Tidak ada data master

```bash
php artisan migrate --seed
```

Atau jalankan seeder master data secara terpisah.

### Indikator koneksi menampilkan "Polling"

- Pastikan `php artisan reverb:start` berjalan.
- Periksa `REVERB_APP_KEY`, `REVERB_HOST`, `REVERB_PORT`, dan
  `AMCS_REVERB_CLIENT_*`.
- Cek `/broadcasting/auth` (harus `200`) dan koneksi WebSocket di tab Network.
- Aplikasi tetap berfungsi dengan polling 3 detik.

### WebSocket gagal di produksi

- Pastikan Nginx memiliki `location /app/` dengan header `Upgrade`.
- Pastikan `REVERB_SERVER_HOST=127.0.0.1` dan Reverb listen di port 8080.
- Pastikan `REVERB_ALLOWED_ORIGINS` memuat domain HTTPS.
- Jika memakai Cloudflare/proxy, aktifkan WebSocket dan teruskan
  `X-Forwarded-Proto`.

### Export Excel gagal

Pastikan ekstensi PHP `zip` aktif:

```bash
php -m | grep zip
```

### Login berhasil tetapi API 401

Periksa:

```dotenv
APP_URL=https://amcs.example.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=amcs.example.com
```

### Backup gagal

- Pastikan `mysqldump` tersedia.
- Periksa kredensial DB di `.env`.
- Pastikan `storage/app/backups` dapat ditulis oleh user web/queue.

### Setelah deploy perubahan tidak muncul

```bash
php artisan optimize:clear
php artisan optimize
sudo systemctl reload php8.3-fpm
```

Jika OPcache menggunakan `validate_timestamps=0`, reload PHP-FPM wajib.

---

## 18. Dokumentasi Terkait

| Dokumen | Isi |
|---|---|
| [`prd.md`](prd.md) | Product Requirements Document, kebutuhan fungsional/nonfungsional |
| [`DEPLOYMENT_UBUNTU_24.md`](DEPLOYMENT_UBUNTU_24.md) | Panduan deploy produksi Ubuntu 24.04 |

---

## Lisensi

Aplikasi internal AMCS. Hak penggunaan mengikuti kebijakan instansi/bandara
terkait.
