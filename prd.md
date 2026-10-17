# Product Requirements Document (PRD)

## AMCS — Apron Movement Control System

**Versi dokumen:** 1.0  
**Tanggal:** 17 Oktober 2026  
**Status:** Draft untuk pengembangan  
**Platform saat ini:** Web HTML/JavaScript  
**Penyimpanan saat ini:** Firebase Realtime Database  
**Platform target:** Laravel 11  
**Database target:** MySQL

### Stack Teknologi Target

Pengembangan berikutnya wajib menggunakan:

- **Backend:** Laravel 11 berbasis PHP.
- **Database:** MySQL.
- **API:** Laravel REST API untuk komunikasi antara frontend, dashboard AMC, dan monitor ATC.
- **Autentikasi:** Laravel authentication dengan role dan permission.
- **Realtime:** Laravel broadcasting/WebSocket atau mekanisme polling terkontrol untuk memperbarui monitor ATC.
- **Migrasi database:** Laravel Migrations.
- **Validasi:** Laravel Form Request dan validasi di service/domain layer.
- **Otorisasi:** Laravel Policies/Gates atau package permission yang disetujui.

Firebase digunakan hanya sebagai sumber data pada sistem lama selama masa migrasi dan tidak menjadi database utama pada sistem target.

---

## 1. Ringkasan Produk

AMCS adalah aplikasi web untuk membantu petugas Apron Movement Control (AMC) dan Air Traffic Control (ATC) memantau serta mencatat penggunaan parking stand pesawat di apron bandara.

Versi saat ini terdiri dari dua halaman:

1. **Dashboard AMC/Operator**  
   Digunakan untuk memasukkan data penerbangan, memilih parking stand, mengubah fase operasional, melihat status seluruh stand, membaca histori pergerakan, dan mengekspor laporan ke Excel.

2. **Monitor ATC Tower View**  
   Digunakan untuk menampilkan kondisi lima parking stand secara ringkas dan realtime, termasuk nomor penerbangan, tipe pesawat, waktu operasi, runway, taxiway, dan status stand.

Pada versi saat ini, data aktif dan histori dibagikan melalui Firebase Realtime Database. Pada versi target, seluruh data operasional akan dikelola oleh Laravel 11 dan disimpan di MySQL. Dashboard AMC dan monitor ATC berkomunikasi melalui API Laravel dan kanal realtime yang dikendalikan backend.

---

## 2. Cerita dan Isi Web App Saat Ini

### 2.1 Dashboard AMC

Dashboard menampilkan visualisasi apron dengan lima parking stand. Setiap stand menunjukkan:

- Status stand: `VACANT`, `ENTERING`, `ON`, `DELAY`, atau `RON`
- Nomor penerbangan
- Maskapai
- Jenis penerbangan
- Tipe pesawat
- Registrasi pesawat
- Rute asal dan tujuan dalam kode ICAO
- ETA, ATA, ETD, dan ATD
- Runway aktif
- Taxiway
- Informasi keterlambatan
- Tombol aksi cepat untuk `BLOCK ON` atau `BLOCK OFF`

Operator memasukkan data melalui formulir. Form tersebut juga menyediakan:

- Pilihan maskapai umum
- Pilihan tipe pesawat
- Pilihan jenis penerbangan
- Input manual untuk maskapai dan tipe pesawat yang tidak tersedia
- Pilihan alasan delay
- Pemilihan tanggal laporan
- Pemilihan parking stand

Nomor penerbangan tertentu dapat memicu pengisian otomatis maskapai dan tipe pesawat. Contohnya, awalan `IW` dipetakan ke Wings Air dan ATR72.

### 2.2 Siklus Status Penerbangan

Alur operasional yang didukung saat ini adalah:

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

Makna status:

- `VACANT`: stand kosong.
- `ENTERING`: pesawat sedang memasuki atau mendekati stand.
- `ON`: pesawat sudah parkir dan chocks/block terpasang.
- `DELAY`: pesawat mengalami keterlambatan dengan durasi dan alasan tertentu.
- `RON`: pesawat menginap di stand.
- `OFF`: pesawat pushback atau taxi out dan stand dikosongkan.

### 2.3 Aturan Stand Saat Ini

Alokasi stand masih dilakukan manual. Operator memilih stand dari dropdown.

Satu aturan kapasitas yang sudah diterapkan adalah:

- Parking Stand 1 hanya menerima pesawat sampai ATR72.
- Parking Stand 1 menolak A320, A321, B737-800, dan B737-900ER.

Belum terdapat algoritma otomatis untuk memilih stand berdasarkan ukuran pesawat, konflik jadwal, waktu parkir, prioritas, atau rute.

### 2.4 Histori dan Laporan

Setiap aksi penting dicatat ke histori, antara lain:

- `BLOCK ENTERING`
- `BLOCK ON`
- `DELAY`
- `BLOCK RON`
- `BLOCK OFF`
- `RESET ALL`

Dashboard menampilkan statistik berdasarkan tanggal laporan:

- Jumlah pergerakan
- Jumlah delay
- Jumlah stand aktif
- Jumlah pesawat RON

Histori dapat diekspor ke file `LAPORAN_TERPADU_AMCS.xlsx`, yang berisi:

- Sheet rekap tahunan
- Sheet detail per bulan
- Total pergerakan, delay, dan RON
- Detail waktu dan data penerbangan

### 2.5 Monitor ATC

Monitor ATC menampilkan lima kartu stand dalam format yang lebih sederhana dan mudah dibaca dari layar tower. Monitor hanya membaca data aktif dari Firebase dan tidak menampilkan histori.

Status `VACANT` ditampilkan sebagai `READY`, sedangkan `ENTERING` ditampilkan sebagai `APPROACHING`.

---

## 3. Latar Belakang dan Masalah

Operasional parking stand membutuhkan informasi yang konsisten, cepat, dan dapat dilihat oleh beberapa unit kerja. Versi saat ini sudah menyediakan tampilan realtime, tetapi masih memiliki keterbatasan:

1. Alokasi stand masih manual.
2. Belum ada autentikasi dan pembagian hak akses.
3. Validasi penting masih dilakukan di browser.
4. Belum ada audit trail berbasis pengguna.
5. Belum ada penguncian atau pencegahan konflik antaroperator.
6. Belum ada validasi konflik jadwal atau overlap penggunaan stand.
7. Struktur data belum memiliki master data bandara, stand, maskapai, dan tipe pesawat.
8. Penghapusan histori dapat dilakukan dari dashboard tanpa alur persetujuan.
9. Ketergantungan pada Firebase client-side membuat aturan keamanan database menjadi sangat penting.
10. Belum ada notifikasi untuk kondisi kritis seperti konflik stand, delay panjang, atau perubahan status.

---

## 4. Tujuan Produk

### 4.1 Tujuan Utama

Mengembangkan AMCS menjadi sistem operasional parking stand yang aman, realtime, dapat diaudit, dan membantu operator mengambil keputusan alokasi dengan lebih cepat serta akurat.

### 4.2 Tujuan Terukur

- Seluruh perubahan data aktif memiliki identitas pengguna, waktu, dan alasan perubahan.
- Sistem mencegah dua penerbangan aktif menggunakan stand yang sama.
- Sistem menolak tipe pesawat yang tidak sesuai dengan kapasitas stand.
- Monitor ATC menampilkan perubahan status dalam waktu maksimal 3 detik setelah data tersimpan.
- Laporan harian dan bulanan dapat diunduh tanpa manipulasi manual.
- Aksi berisiko tinggi, seperti reset massal dan penghapusan log, memerlukan hak akses khusus.
- Tidak ada data operasional yang dapat diubah oleh pengguna tanpa autentikasi.

---

## 5. Sasaran Pengguna

### 5.1 Petugas AMC/Apron Controller

Membuat dan memperbarui alokasi stand, mencatat fase penerbangan, serta memantau kondisi apron.

### 5.2 Petugas ATC

Melihat status stand, waktu pergerakan, dan routing runway/taxiway melalui tampilan ringkas.

### 5.3 Supervisor Operasional

Memvalidasi perubahan penting, melihat histori, melakukan koreksi, dan meninjau laporan.

### 5.4 Administrator Sistem

Mengelola pengguna, role, master data stand, kapasitas pesawat, konfigurasi runway/taxiway, dan aturan sistem.

---

## 6. Ruang Lingkup

### 6.1 Termasuk dalam Pengembangan

- Login dan autentikasi pengguna.
- Role-based access control.
- Dashboard AMC yang tetap realtime.
- Monitor ATC yang tetap realtime.
- Master data parking stand.
- Master data tipe pesawat dan batasan stand.
- Alokasi manual dengan validasi sistem.
- Rekomendasi stand otomatis.
- Manajemen siklus status penerbangan.
- Pencegahan konflik stand.
- Audit trail yang tidak dapat dihapus oleh operator biasa.
- Histori pergerakan dan pencarian laporan.
- Export Excel dan CSV.
- Notifikasi kondisi penting.
- Validasi server-side atau rules/backend yang aman.
- Backup dan retensi data.

### 6.2 Tidak Termasuk pada Tahap Awal

- Integrasi langsung dengan radar atau ADS-B.
- Integrasi langsung dengan sistem flight information bandara.
- Kendali otomatis terhadap lampu apron atau fasilitas fisik.
- Penggantian sistem ATC resmi.
- Penentuan runway berdasarkan meteorologi secara otomatis.
- Optimasi jadwal penerbangan lintas bandara.

---

## 7. Kebutuhan Fungsional

### FR-01 — Autentikasi

Sistem harus menyediakan login menggunakan akun terdaftar. Pengguna yang belum login tidak boleh membaca atau mengubah data operasional.

**Kriteria penerimaan:**

- Pengguna dapat login dan logout.
- Sesi kedaluwarsa secara aman.
- Pengguna nonaktif tidak dapat login.
- Semua aksi operasional memiliki `userId` dan nama pengguna.

### FR-02 — Hak Akses Berbasis Role

Minimal role:

| Role | Hak utama |
|---|---|
| AMC Operator | Membuat dan memperbarui pergerakan |
| ATC Viewer | Hanya melihat monitor ATC |
| Supervisor | Koreksi data, validasi, laporan, approval |
| Administrator | Pengguna, master data, konfigurasi, pemulihan |

Operator biasa tidak boleh melakukan reset massal atau menghapus audit trail.

### FR-03 — Master Data Parking Stand

Administrator dapat mengelola:

- Nama stand
- Status aktif/nonaktif
- Kapasitas atau kategori pesawat
- Gate/area
- Koordinat atau informasi visual apron
- Catatan operasional

Perubahan master data harus tercatat dalam audit trail.

### FR-04 — Master Data Pesawat

Administrator dapat mengatur:

- Tipe pesawat
- Kategori: narrow-body, turboprop, private jet, dan lainnya
- Ukuran atau wingspan
- Batasan stand
- Keterangan keselamatan

### FR-05 — Input Pergerakan

Operator dapat membuat pergerakan dengan data:

- Nomor penerbangan
- Maskapai
- Registrasi
- Tipe pesawat
- Jenis penerbangan
- Asal dan tujuan
- ETA, ATA, ETD, ATD
- Stand
- Runway
- Taxiway
- Catatan operasional

Sistem harus memvalidasi field wajib, format ICAO, format waktu, dan kompatibilitas pesawat dengan stand.

### FR-06 — Alokasi Manual dengan Validasi

Operator tetap dapat memilih stand secara manual. Sistem wajib:

- Menolak stand yang sedang aktif digunakan.
- Menolak tipe pesawat yang melampaui kapasitas stand.
- Memperingatkan overlap jadwal.
- Menampilkan alasan penolakan secara jelas.
- Menyediakan opsi meminta override kepada supervisor jika aturan mengizinkan.

### FR-07 — Rekomendasi Alokasi

Sistem harus dapat merekomendasikan stand yang sesuai berdasarkan:

- Kapasitas stand.
- Status stand.
- Perkiraan waktu datang dan berangkat.
- Durasi penggunaan.
- Konflik dengan reservasi lain.
- Prioritas penerbangan.
- Kebutuhan khusus pesawat.

Operator tetap menjadi pengambil keputusan akhir. Sistem tidak boleh mengubah stand otomatis tanpa konfirmasi.

### FR-08 — Manajemen Status

Sistem harus mendukung transisi status yang valid:

```text
VACANT -> ENTERING -> ON
ON -> DELAY
ON -> RON
ON -> OFF -> VACANT
DELAY -> ON
DELAY -> OFF -> VACANT
RON -> OFF -> VACANT
```

Transisi yang tidak valid harus ditolak atau memerlukan override supervisor.

### FR-09 — Block On dan Block Off

Sistem harus menyediakan aksi cepat:

- `BLOCK ON`: mengisi ATA dengan waktu aktual dan mengubah status menjadi `ON`.
- `BLOCK OFF`: mengisi ATD dengan waktu aktual, mencatat histori, lalu mengosongkan stand.

Kedua aksi harus meminta konfirmasi pengguna.

### FR-10 — Penanganan Delay

Operator dapat mencatat:

- Durasi delay.
- Alasan delay.
- Catatan tambahan.

Delay panjang harus dapat dikonfigurasi, misalnya lebih dari 30 menit, untuk memicu notifikasi supervisor.

### FR-11 — Tampilan Realtime

Dashboard AMC dan monitor ATC harus menerima perubahan data secara realtime.

Sistem harus menampilkan:

- Status koneksi.
- Waktu terakhir sinkronisasi.
- Pesan kesalahan jika koneksi terputus.

### FR-12 — Histori dan Audit Trail

Sistem harus menyimpan:

- Data sebelum perubahan.
- Data sesudah perubahan.
- Jenis aksi.
- Pengguna.
- Waktu server.
- Alasan perubahan jika diperlukan.

Audit trail tidak boleh dihapus oleh operator biasa.

### FR-13 — Pencarian dan Filter

Pengguna berwenang dapat memfilter histori berdasarkan:

- Tanggal atau rentang tanggal.
- Nomor penerbangan.
- Stand.
- Maskapai.
- Status.
- Jenis penerbangan.
- Pengguna.

### FR-14 — Laporan

Sistem harus menyediakan:

- Rekap pergerakan harian.
- Rekap per bulan.
- Total penggunaan setiap stand.
- Total delay.
- Total RON.
- Durasi rata-rata penggunaan stand.
- Export Excel dan CSV.

### FR-15 — Reset dan Koreksi

Reset semua stand hanya dapat dilakukan oleh Supervisor atau Administrator.

Sistem harus:

- Meminta konfirmasi berlapis.
- Mewajibkan alasan reset.
- Mencatat seluruh stand yang terdampak.
- Menyimpan audit trail.

Penghapusan histori permanen sebaiknya diganti dengan mekanisme arsip dan retensi.

### FR-16 — Notifikasi

Sistem harus dapat memberi notifikasi untuk:

- Konflik stand.
- Pesawat melebihi kapasitas stand.
- Delay melewati ambang batas.
- Koneksi database terputus.
- Perubahan status penting.
- Permintaan override supervisor.

---

## 8. Kebutuhan Nonfungsional

### NFR-01 — Keamanan

- Semua akses harus melalui autentikasi.
- Laravel Authentication dan authorization harus menerapkan role secara ketat.
- Validasi penting wajib dilakukan di backend Laravel, bukan hanya JavaScript browser.
- Audit trail tidak boleh dapat dimodifikasi oleh operator.
- Tidak menyimpan credential atau secret di source code.
- Gunakan HTTPS dan library yang dipin pada versi yang terkontrol.
- Credential database dan konfigurasi aplikasi harus disimpan melalui environment configuration.

### NFR-02 — Ketersediaan

- Sistem harus tetap dapat digunakan selama aplikasi Laravel dan layanan MySQL aktif.
- Perubahan koneksi harus ditampilkan secara eksplisit.
- Data yang belum tersimpan tidak boleh dianggap berhasil.

### NFR-03 — Performa

- Pembaruan status realtime target maksimal 3 detik.
- Tampilan dashboard awal target maksimal 3 detik pada jaringan operasional normal.
- Query histori menggunakan pagination dan filter, bukan memuat seluruh log ke browser.

### NFR-04 — Konsistensi Data

- Perubahan stand dan log harus menggunakan database transaction MySQL melalui Laravel.
- Sistem harus mencegah dua operator mengalokasikan penerbangan berbeda ke stand yang sama.
- Waktu pencatatan utama harus menggunakan waktu server aplikasi/database.

### NFR-05 — Usability

- Status dibedakan dengan warna dan teks, bukan warna saja.
- Tampilan ATC harus terbaca dari jarak jauh.
- Aksi berisiko harus memiliki konfirmasi yang jelas.
- Form harus mempertahankan input yang tidak bermasalah ketika terjadi error.

### NFR-06 — Kompatibilitas

- Mendukung Chrome dan Edge versi terbaru.
- Tampilan dashboard responsif untuk desktop dan tablet.
- Monitor ATC mendukung resolusi layar besar.

### NFR-07 — Pemulihan Data

- Backup database dilakukan berkala.
- Backup MySQL diuji melalui prosedur restore berkala.
- Tersedia prosedur pemulihan.
- Penghapusan data operasional harus mengikuti retensi dan persetujuan.

---

## 9. Model Data yang Disarankan

Pada sistem target, struktur data menggunakan tabel relasional MySQL yang dikelola melalui Laravel Eloquent dan Laravel Migrations. Struktur ini menggantikan node Firebase pada sistem lama.

```text
users
  id, name, email, password, active, created_at, updated_at

roles
  id, name

user_roles
  user_id, role_id

airlines
  id, code, name, active

aircraft_types
  id, code, name, category, wingspan, max_weight, active

parking_stands
  id, code, name, active, capacity_category, notes

parking_stand_aircraft_types
  parking_stand_id, aircraft_type_id

movements
  id, flight_number, airline_id, registration, aircraft_type_id,
  flight_type, origin, destination, parking_stand_id, state,
  eta, ata, etd, atd, runway, taxiway,
  delay_minutes, delay_reason, created_by, updated_by,
  created_at, updated_at

stand_reservations
  id, movement_id, parking_stand_id, start_time, end_time, status

movement_logs
  id, movement_id, action, payload, created_by, created_at

audit_logs
  id, user_id, action, entity_type, entity_id,
  before_data, after_data, reason, created_at
```

Relasi penting:

- Satu `parking_stand` dapat memiliki banyak `movements`.
- Satu `aircraft_type` dapat diizinkan pada banyak `parking_stands`.
- Satu `movement` memiliki banyak `movement_logs`.
- Setiap perubahan penting pada entitas operasional memiliki `audit_logs`.
- Foreign key dan unique index harus digunakan untuk menjaga integritas data.

Pada tahap migrasi, node Firebase `/apron_stands` dan `/logs` dapat dibaca melalui script migrasi satu kali untuk mengisi tabel MySQL. Setelah verifikasi, Firebase masuk mode read-only dan kemudian dihentikan sebagai sumber operasional.

---

## 10. Alur Utama Pengguna

### 10.1 Alur Alokasi Pesawat

1. Operator login.
2. Operator membuka dashboard AMC.
3. Operator mengisi nomor penerbangan.
4. Sistem menawarkan prediksi maskapai dan tipe pesawat.
5. Operator memeriksa dan melengkapi data.
6. Operator memilih waktu operasi.
7. Sistem menampilkan stand yang tersedia dan sesuai.
8. Operator memilih atau menerima rekomendasi stand.
9. Sistem memvalidasi kapasitas dan konflik.
10. Operator mengonfirmasi.
11. Sistem menyimpan pergerakan dan audit trail.
12. Monitor ATC memperbarui tampilan.

### 10.2 Alur Block On

1. Pesawat berstatus `ENTERING`.
2. Operator memilih `BLOCK ON`.
3. Sistem meminta konfirmasi.
4. Sistem mengambil waktu server.
5. Sistem mengubah status menjadi `ON`.
6. Sistem mengisi ATA.
7. Sistem menyimpan audit trail.

### 10.3 Alur Block Off

1. Operator memilih `BLOCK OFF`.
2. Sistem meminta konfirmasi dan alasan jika diperlukan.
3. Sistem mengambil waktu server.
4. Sistem mencatat ATD.
5. Sistem menyimpan histori final.
6. Sistem melepaskan stand menjadi `VACANT`.
7. Monitor ATC memperbarui tampilan.

---

## 11. Kriteria Penerimaan MVP

MVP dianggap siap apabila:

1. Pengguna dapat login dengan role yang benar.
2. Operator dapat membuat pergerakan baru.
3. Sistem menolak stand yang sedang terisi.
4. Sistem menolak tipe pesawat yang tidak sesuai.
5. Operator dapat mengubah status melalui transisi yang valid.
6. `BLOCK ON` mencatat ATA dan `BLOCK OFF` mencatat ATD.
7. Monitor ATC memperbarui status realtime.
8. Setiap perubahan memiliki user, waktu server, dan audit trail.
9. Operator tidak dapat menghapus audit trail.
10. Supervisor dapat melakukan override dengan alasan.
11. Laporan harian dapat difilter dan diekspor.
12. Sistem menampilkan status koneksi dan error penyimpanan.
13. Reset massal dibatasi untuk role yang berwenang.
14. Data tetap konsisten ketika dua operator mencoba memakai stand yang sama.

---

## 12. Tahapan Pengembangan

### Fase 1 — Fondasi Keamanan dan Stabilitas

- Membuat project Laravel 11.
- Mengonfigurasi koneksi MySQL melalui environment configuration.
- Membuat Laravel Migrations, Models, Factories, dan Seeders.
- Menambahkan autentikasi Laravel.
- Menambahkan role dan permission.
- Menambahkan REST API versioning.
- Menambahkan audit trail.
- Menambahkan indikator koneksi dan error handling frontend.
- Mengamankan aksi reset dan penghapusan data melalui Policies/authorization.

### Fase 2 — Data dan Validasi Operasional

- Membuat master data stand.
- Membuat master data pesawat.
- Membuat master data maskapai.
- Memindahkan data aktif dan histori dari Firebase ke MySQL.
- Menambahkan validasi server-side.
- Menambahkan pencegahan konflik menggunakan database transaction dan locking.
- Menghubungkan dashboard AMC dan monitor ATC ke API Laravel.

### Fase 3 — Rekomendasi dan Pelaporan

- Menambahkan rekomendasi stand.
- Menambahkan reservasi dan pemeriksaan overlap.
- Menambahkan filter histori.
- Menambahkan dashboard analitik.
- Menambahkan export Excel/CSV yang lebih lengkap.

### Fase 4 — Integrasi Lanjutan

- Integrasi flight information system.
- Integrasi data radar atau ADS-B jika disetujui.
- Notifikasi eksternal.
- Integrasi identitas organisasi.
- Monitoring dan observability produksi.
- Optimasi queue, cache, dan broadcasting Laravel jika diperlukan.

---

## 13. Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Migrasi dari Firebase ke MySQL tidak lengkap | Data histori atau stand aktif hilang | Buat backup Firebase, script migrasi tervalidasi, rekonsiliasi jumlah data, dan periode read-only |
| API Laravel tidak menerapkan otorisasi | Data dapat dibaca/diubah pihak tidak berwenang | Gunakan middleware, Policies/Gates, Form Request, dan pengujian authorization |
| Dua operator mengubah stand yang sama | Data konflik | Gunakan transaction/conditional write |
| Log dan perubahan stand tidak sinkron | Histori tidak akurat | Gunakan transaksi atau endpoint server-side |
| Reset tidak sengaja | Operasi apron terganggu | Role khusus, konfirmasi berlapis, audit trail |
| Data histori terlalu besar | Dashboard lambat | Pagination, indexing, arsip |
| Waktu perangkat berbeda | Timestamp tidak akurat | Gunakan server timestamp |
| Koneksi terputus | Operator mengira data tersimpan | Tampilkan status pending/failed secara eksplisit |

---

## 14. Indikator Keberhasilan

- Tidak ada alokasi ganda pada stand aktif.
- Tidak ada data perubahan tanpa identitas pengguna.
- Waktu operator untuk mencatat satu pergerakan kurang dari 60 detik setelah data lengkap.
- Minimal 95% pembaruan realtime diterima monitor dalam waktu kurang dari 3 detik.
- Semua laporan harian dapat dibuat tanpa pengolahan spreadsheet manual.
- Seluruh aksi reset dan koreksi dapat ditelusuri.
- Pengguna operasional memahami arti setiap status tanpa pelatihan panjang.

---

## 15. Keputusan Produk yang Perlu Disetujui

Sebelum implementasi penuh, pemilik produk perlu menetapkan:

1. Apakah sistem digunakan untuk satu bandara atau multi-bandara?
2. Siapa yang boleh melakukan override aturan kapasitas?
3. Berapa lama data histori wajib disimpan?
4. Apakah RON otomatis terbawa ke tanggal operasional berikutnya?
5. Berapa ambang batas delay yang memicu notifikasi?
6. Apakah rekomendasi stand bersifat informatif atau wajib diikuti?
7. Apakah integrasi dengan sistem penerbangan eksternal diperlukan?
8. Apakah sistem target membutuhkan Laravel Reverb/WebSocket atau polling terkontrol untuk realtime?

---

## 16. Kesimpulan

AMCS saat ini sudah memiliki fondasi penting berupa visualisasi apron, pencatatan pergerakan, status operasional, sinkronisasi realtime, histori, dan export laporan. Kelemahan utama terletak pada keamanan, validasi yang masih berada di sisi browser, alokasi yang masih manual, belum adanya audit trail berbasis pengguna, dan belum adanya mekanisme pencegahan konflik antaroperator.

Pengembangan berikutnya menggunakan Laravel 11 sebagai backend dan MySQL sebagai database utama. Prioritas pengembangan adalah membangun fondasi Laravel, migrasi data dari Firebase, autentikasi, otorisasi, audit trail, dan transaksi MySQL yang konsisten. Setelah itu, sistem dapat dikembangkan menjadi alat bantu alokasi stand yang memberikan rekomendasi berdasarkan kapasitas, jadwal, dan konflik penggunaan tanpa menghilangkan keputusan akhir dari petugas operasional.
