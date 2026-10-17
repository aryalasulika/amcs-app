# AMCS — Apron Movement Control System

Implementasi ulang aplikasi **Apron Movement Control System (AMCS)** dari versi
HTML/JavaScript + Firebase menjadi aplikasi **Laravel + MySQL** sesuai dokumen
`prd.md`.

Aplikasi ini dipakai oleh petugas **AMC (Apron Movement Control)**, **ATC**,
**Supervisor**, dan **Administrator** untuk mengelola alokasi parking stand,
siklus status penerbangan, audit trail, serta pelaporan operasional apron.

- **Backend:** Laravel 12 (PHP 8.2+)
- **Database:** MySQL 8
- **API:** REST API versioned (`/api/v1`) dengan Laravel Sanctum
- **Otorisasi:** Spatie Laravel Permission + Policies/Gates
- **Export:** Maatwebsite Excel (`.xlsx`) dan CSV
- **Realtime:** polling terkontrol (target ≤ 3 detik) pada endpoint papan stand

> Catatan: PRD menyebut Laravel 11; atas persetujuan pemilik produk digunakan
> **Laravel 12** agar tetap menerima patch keamanan.

---

## 1. Prasyarat

- PHP ≥ 8.2 dengan ekstensi `pdo_mysql`, `zip`, `gd`, `mbstring`, `openssl`
- Composer 2
- MySQL 8
- Node tidak diperlukan (frontend memakai CSS/JS vanilla tanpa build step)

## 2. Instalasi

```bash
cd amcs-app

# 1. Dependensi
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Sesuaikan kredensial MySQL pada .env lalu buat database
mysql -u root -e "CREATE DATABASE amcs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Migrasi + data awal (role, master data, akun contoh)
php artisan migrate --seed

# 5. Jalankan
php artisan serve            # http://127.0.0.1:8000
```

### Menjalankan di Laragon

Letakkan proyek di `C:\laragon\www\amcs\amcs-app`. Aplikasi dapat diakses dari
**root domain maupun sub-folder**, pada host/port apa pun:

- Root domain: arahkan document root `amcs.test` ke `amcs-app/public`.
- Sub-folder: `http://amcs.test/amcs-app/public`.

`AppServiceProvider` otomatis menyesuaikan URL aset, tautan, dan endpoint API
dengan host/base yang sedang dipakai, serta mempercayai host tersebut untuk
permintaan API berbasis sesi (Sanctum). Jadi tidak perlu mengubah `APP_URL`
secara manual.

> Di balik reverse proxy/HTTPS, konfigurasikan `TrustProxies` agar skema URL
> yang dihasilkan benar.

### Troubleshooting

- **Popup/notifikasi "Gagal memuat master data"** muncul bila permintaan API
  dari halaman gagal. Pastikan Anda benar-benar sudah login (bukan hanya membuka
  URL), lalu muat ulang halaman. Bila masih terjadi, cek tab Network browser:
  status `401` berarti sesi tidak terkirim, `404` berarti base URL salah.
- **Tidak ada data master** → jalankan `php artisan migrate --seed`.

### Ekstensi PHP `zip`

`.xlsx` memerlukan ekstensi `zip`. Aktifkan pada `php.ini`:

```ini
extension=zip
```

---

## 3. Akun contoh (hasil seeder)

| Role | Email | Kata sandi |
|---|---|---|
| Administrator | `admin@amcs.local` | `Amcs#2026` |
| Supervisor | `supervisor@amcs.local` | `Amcs#2026` |
| AMC Operator | `operator@amcs.local` | `Amcs#2026` |
| ATC Viewer | `atc@amcs.local` | `Amcs#2026` |

Kata sandi dapat diubah melalui `AMCS_SEED_PASSWORD` sebelum `migrate --seed`.

---

## 4. Role & Hak Akses

| Role | Hak utama |
|---|---|
| Administrator | Semua: master data, pengguna, konfigurasi, audit |
| Supervisor | Pergerakan, **override**, reset massal, laporan, audit |
| AMC Operator | Membuat/memperbarui pergerakan, BLOCK ON/OFF, laporan |
| ATC Viewer | Melihat monitor ATC dan papan stand |

Permission granular: `stands.view`, `stands.reset`, `movements.view/create/update/delete/override`,
`reports.view/export`, `history.view`, `audit.view`, `master-data.manage`,
`users.manage`, `notifications.view`.

---

## 5. Fitur utama

- **Autentikasi & sesi** — login/logout web + token API (Sanctum), akun nonaktif ditolak.
- **RBAC** — role & permission pada route, controller, dan policy.
- **Master data** — parking stand (+batasan tipe pesawat), tipe pesawat, maskapai,
  pengguna. Setiap perubahan tercatat pada audit trail.
- **Input pergerakan** dengan validasi server-side (field wajib, format ICAO,
  format waktu, kompatibilitas pesawat–stand).
- **Auto-fill penerbangan** — awalan nomor penerbangan (mis. `IW`) memilih
  maskapai dan tipe pesawat default yang dikonfigurasi pada master maskapai.
- **Tipe pesawat manual** — tipe di luar master data dapat diketik beserta
  kategorinya; sistem menyimpannya ke master data dan tetap memvalidasi
  kapasitas stand berdasarkan kategori.
- **Validasi alokasi** — menolak stand terisi, menolak tipe pesawat melebihi
  kapasitas, memperingatkan tumpang tindih jadwal, menyediakan override supervisor.
- **Rekomendasi stand** informatif (kapasitas, status, jadwal, prioritas pilihan).
- **Siklus status** `VACANT → ENTERING → ON → (DELAY|RON) → OFF` dengan transisi tervalidasi.
- **BLOCK ON / BLOCK OFF** — mencatat ATA/ATD dengan waktu server + konfirmasi.
- **Penanganan delay** — durasi, alasan, catatan; notifikasi bila melewati ambang batas.
- **Realtime** — dashboard & monitor ATC memperbarui papan stand tiap `AMCS_REALTIME_POLL_SECONDS`
  (default 3s) dan menampilkan status koneksi + waktu sinkron terakhir.
- **Audit trail** permanen (pelaku, waktu, data sebelum/sesudah, alasan). Tidak dapat diubah/dihapus.
- **Histori & filter** — tanggal, nomor penerbangan, stand, maskapai, status, jenis, pengguna.
- **Laporan & export** — rekap harian, bulanan, tahunan, penggunaan per stand,
  total delay/RON, rata-rata turnaround; kartu rekap mengikuti filter aktif;
  export `.xlsx` (multi-sheet) dan CSV.
- **Reset massal** terbatas untuk Supervisor/Administrator dengan konfirmasi berlapis + alasan.
- **Notifikasi** database (konflik/kapasitas, delay panjang, override, reset).
- **Backup & retensi** terjadwal, serta arsip log aktivitas lama.

### Peta kebutuhan PRD → implementasi

| Kode | Ringkasan | Implementasi |
|---|---|---|
| FR-01 | Autentikasi | `Auth\LoginController`, `Api\V1\AuthController`, middleware `active` |
| FR-02 | Role & hak akses | Spatie Permission, Policies, `RoleAndPermissionSeeder` |
| FR-03 | Master stand | `Api\V1\ParkingStandController`, `admin/stands.blade.php` |
| FR-04 | Master pesawat | `Api\V1\AircraftTypeController`, `admin/aircraft-types.blade.php` |
| FR-05 | Input pergerakan | `StoreMovementRequest`, `MovementService@create` |
| FR-06 | Validasi alokasi | `StandAllocationService`, `MovementService@enforceRules` |
| FR-07 | Rekomendasi stand | `StandAllocationService@recommend` |
| FR-08 | Transisi status | `MovementState@allowedTransitions`, `MovementService@transition` |
| FR-09 | BLOCK ON/OFF | `MovementController@blockOn/blockOff` |
| FR-10 | Delay | `DelayRequest`, transisi `DELAY`, notifikasi ambang batas |
| FR-11 | Realtime | `StandBoardService`, polling dashboard & ATC + indikator koneksi |
| FR-12 | Audit trail | `AuditService`, model `AuditLog` (imutabel) |
| FR-13 | Pencarian/filter | `ReportService@historyQuery` + halaman laporan |
| FR-14 | Laporan & export | `ReportService`, `Exports\*`, `ReportController@export` |
| FR-15 | Reset & koreksi | `MovementService@resetAll/archive`, `ResetStandsRequest` |
| FR-16 | Notifikasi | `NotificationService`, `OperationalAlert` |
| NFR-01 | Keamanan | Validasi server-side, RBAC, secret via `.env` |
| NFR-03 | Performa | Pagination histori, endpoint papan stand ringan |
| NFR-04 | Konsistensi | Transaksi MySQL + `lockForUpdate` pada stand |
| NFR-07 | Pemulihan | `amcs:backup-database`, `amcs:archive-activity` |

---

## 6. Endpoint API (ringkas, prefix `/api/v1`)

Autentikasi: **Bearer token** (klien eksternal) atau **cookie sesi** (dashbord/monitor).

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/auth/login` | Login, mengembalikan token |
| GET | `/auth/me` | Profil pengguna aktif |
| POST | `/auth/logout` | Logout / cabut token |
| GET | `/options` | Data referensi untuk form |
| GET | `/stands` | Papan stand realtime + hitungan |
| GET | `/stands/available` | Stand aktif & kosong |
| POST | `/stands/recommend` | Rekomendasi alokasi |
| POST | `/stands/reset` | Reset massal (Supervisor/Admin) |
| GET/POST | `/movements` | Daftar (filter+paginasi) / buat pergerakan |
| GET/PUT/DELETE | `/movements/{id}` | Detail / koreksi / arsip |
| PATCH | `/movements/{id}/state` | Transisi status |
| POST | `/movements/{id}/block-on` \| `/block-off` \| `/delay` | Aksi cepat |
| GET | `/movements/{id}/logs` | Histori per pergerakan |
| GET | `/reports/daily` \| `/monthly` \| `/summary` \| `/history` \| `/activity` | Laporan |
| GET | `/reports/export?format=xlsx\|csv` | Export laporan |
| GET | `/audit-logs` | Audit trail |
| GET | `/notifications` (+ `read-all`) | Notifikasi |
| CRUD | `/parking-stands`, `/airlines`, `/aircraft-types`, `/users` | Master data |

---

## 7. Pengujian

```bash
php artisan test
```

Mencakup 46 tes untuk kriteria MVP: login & akun nonaktif, penolakan stand terisi,
penolakan tipe pesawat, tipe pesawat manual, auto-fill maskapai, override
supervisor, siklus BLOCK ON/OFF, transisi tidak valid, audit trail immutabel,
reset ber-otorisasi, isolasi role, laporan terfilter, dan export.

Konfigurasi tes memakai database `amcs_testing` (lihat `phpunit.xml`).

---

## 8. Perintah pemeliharaan

```bash
php artisan amcs:backup-database --keep=30        # backup MySQL + retensi
php artisan amcs:archive-activity --days=1095     # arsip log lama ke storage/app/archive
php artisan amcs:import-firebase data.json        # impor export Firebase (apron_stands + logs)
```

Penjadwalan (butuh scheduler `php artisan schedule:work` atau cron):
backup harian 02:00 dan arsip log bulanan.

---

## 9. Struktur penting

```
app/
  Enums/                  MovementState, AircraftCategory, FlightType
  Exceptions/             OperationException (pelanggaran aturan operasional)
  Http/Controllers/Api/V1 REST API
  Http/Controllers/Web/   Halaman dashboard, ATC, laporan, admin
  Http/Requests/          Validasi server-side
  Http/Resources/         Bentuk keluaran JSON
  Models/                 Eloquent
  Policies/               Otorisasi
  Services/               Audit, alokasi stand, pergerakan, papan stand, laporan, notifikasi
  Console/Commands/       backup, archive, import Firebase
database/migrations|seeders|factories/
resources/views/          Blade (dark theme apron)
public/css|js/            Aset frontend tanpa build step
```

---

## 10. Keputusan produk (per PRD §15)

Beberapa hal di PRD masih terbuka; implementasi memilih default yang aman:

1. **Satu bandara** (Luwuk/WAMW), namun skema siap dikembangkan.
2. **Override kapasitas** hanya oleh Supervisor/Administrator.
3. **Retensi** default 3 tahun untuk log aktivitas (dapat dikonfigurasi).
4. **RON** tidak otomatis terbawa ke hari berikutnya (operator memutuskan).
5. **Ambang delay** default 30 menit (`AMCS_DELAY_THRESHOLD_MINUTES`).
6. **Rekomendasi stand** bersifat informatif; operator tetap pengambil keputusan.
7. **Integrasi eksternal** (flight info/radar) belum termasuk (Fase 4).
8. **Realtime** memakai polling terkontrol; dapat ditingkatkan ke Laravel Reverb/WebSocket.
