# Deployment AMCS di Ubuntu 24.04 LTS

Panduan ini memasang **AMCS (Apron Movement Control System)** di server Ubuntu
24.04 menggunakan:

- **Nginx** sebagai web server dan reverse proxy
- **PHP 8.3 + PHP-FPM**
- **MySQL 8**
- **Laravel Reverb** untuk WebSocket realtime
- **Supervisor** untuk Reverb dan queue worker
- **Cron** untuk scheduler Laravel (backup & arsip log)
- **Let's Encrypt/Certbot** untuk HTTPS

> Contoh domain: `amcs.example.com`  
> Contoh direktori aplikasi: `/var/www/amcs`  
> Ganti semua nilai contoh sebelum digunakan di produksi.

---

## 1. Arsitektur singkat

```text
Browser (HTTPS/WSS)
        |
        v
      Nginx  :443
      |  |
      |  +---- /app/  -----------> Reverb :8080 (127.0.0.1)
      |
      +------- / ----------------> PHP-FPM 8.3 ----> MySQL 8
                                         |
                                         +---- queue worker (Supervisor)
                                         +---- scheduler (Cron)
```

- Halaman web, REST API, dan `/broadcasting/auth` dilayani Nginx → PHP-FPM.
- WebSocket Reverb diproxy Nginx pada path `/app/`.
- Server melakukan broadcast langsung ke `127.0.0.1:8080` (loopback), sehingga
  tidak bergantung pada DNS/hairpin NAT ke domain publik.
- Reverb hanya listen di `127.0.0.1:8080` dan tidak dibuka ke publik.

---

## 2. Prasyarat

- Ubuntu Server **24.04 LTS** dengan akses `sudo`.
- Domain yang sudah diarahkan ke IP server (A record):
  - `amcs.example.com` → IP publik server
- Port `80` dan `443` terbuka.
- Akses SSH.
- Spesifikasi minimum yang disarankan:
  - 2 vCPU, 4 GB RAM, 20 GB disk (lebih besar jika histori panjang)
- Repository aplikasi (Git) atau salinan source code.
- Kredensial database dan secret produksi yang kuat.

---

## 3. Persiapan server

```bash
sudo apt update
sudo apt upgrade -y
sudo timedatectl set-timezone Asia/Makassar
timedatectl
```

Buat user deployment (jangan gunakan `root` untuk aplikasi):

```bash
sudo adduser deploy
sudo usermod -aG www-data deploy
```

Pastikan `deploy` bisa menggunakan `sudo`:

```bash
sudo usermod -aG sudo deploy
```

Logout lalu login kembali sebagai `deploy`:

```bash
exit
ssh deploy@IP_SERVER
```

---

## 4. Instal dependency

### 4.1 Paket dasar, Nginx, PHP-FPM, MySQL client

```bash
sudo apt install -y \
  nginx mysql-server mysql-client \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl php8.3-opcache \
  git unzip supervisor certbot python3-certbot-nginx
```

Verifikasi:

```bash
php -v
php -m | grep -iE 'pdo_mysql|mbstring|xml|zip|gd|bcmath|intl|opcache'
nginx -v
mysql --version
mysqldump --version
```

> `mysqldump` diperlukan oleh perintah backup `amcs:backup-database`.

### 4.2 Composer

```bash
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
php /tmp/composer-setup.php --install-dir=/tmp --filename=composer
sudo mv /tmp/composer /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
composer --version
```

### 4.3 Node.js (opsional)

Node **tidak diperlukan untuk menjalankan aplikasi**, karena CSS/JS produksi
sudah berada di `public/`. Node hanya diperlukan jika ingin:

- memperbarui aset klien Echo/Pusher: `npm run vendor:realtime`
- melakukan build Vite untuk aset tambahan

Jika diperlukan:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

---

## 5. Konfigurasi PHP

Buat file tuning PHP-FPM:

```bash
sudo nano /etc/php/8.3/fpm/conf.d/99-amcs.ini
```

Isi:

```ini
memory_limit = 256M
max_execution_time = 120
upload_max_filesize = 20M
post_max_size = 20M
date.timezone = Asia/Makassar

opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
```

> `opcache.validate_timestamps=0` membuat OPcache tidak mengecek perubahan file.
> Setelah setiap deploy, jalankan `php artisan optimize:clear` dan
> `sudo systemctl reload php8.3-fpm`.

Sesuaikan pool PHP-FPM pada `/etc/php/8.3/fpm/pool.d/www.conf` bila perlu:

```ini
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
```

Terapkan:

```bash
sudo /usr/sbin/php-fpm8.3 -t
sudo systemctl restart php8.3-fpm
sudo systemctl enable php8.3-fpm
```

---

## 6. Konfigurasi MySQL

Amankan MySQL:

```bash
sudo mysql_secure_installation
```

Buat database dan user aplikasi:

```bash
sudo mysql
```

```sql
CREATE DATABASE amcs
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'amcs'@'localhost' IDENTIFIED BY 'GANTI_DENGAN_PASSWORD_DATABASE_KUAT';

GRANT ALL PRIVILEGES ON amcs.* TO 'amcs'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

Uji koneksi:

```bash
mysql -u amcs -p amcs -e "SELECT 1;"
```

Pastikan MySQL hanya listen lokal:

```bash
sudo ss -ltnp | grep 3306
```

Jika output menunjukkan `0.0.0.0:3306`, batasi pada
`/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
bind-address = 127.0.0.1
```

Lalu:

```bash
sudo systemctl restart mysql
```

---

## 7. Deploy source code

### 7.1 Siapkan direktori

```bash
sudo mkdir -p /var/www/amcs
sudo chown deploy:www-data /var/www/amcs
sudo chmod 775 /var/www/amcs
```

### 7.2 Ambil source code

**Jika menggunakan Git:**

```bash
cd /var/www
git clone <URL_REPOSITORY> amcs
cd amcs
```

**Jika menggunakan rsync dari komputer lokal:**

```bash
rsync -avz --delete \
  --exclude '.env' \
  --exclude 'node_modules' \
  --exclude 'vendor' \
  ./ deploy@IP_SERVER:/var/www/amcs/
```

### 7.3 Install dependency PHP

```bash
cd /var/www/amcs
composer install --no-dev --optimize-autoloader
```

### 7.4 Siapkan environment

```bash
cp .env.example .env
php artisan key:generate --force
```

Edit `.env`:

```bash
nano .env
```

Gunakan template produksi berikut dan ganti semua nilai placeholder:

```dotenv
APP_NAME="AMCS"
APP_ENV=production
APP_KEY=base64:HASIL_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://amcs.example.com

APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID
APP_TIMEZONE=Asia/Makassar

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=amcs
DB_USERNAME=amcs
DB_PASSWORD=GANTI_DENGAN_PASSWORD_DATABASE_KUAT

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

SANCTUM_STATEFUL_DOMAINS=amcs.example.com

CACHE_STORE=database
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb

FILESYSTEM_DISK=local

MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@amcs.example.com"
MAIL_FROM_NAME="${APP_NAME}"

# --------------------------------------------------------------------
# Reverb / WebSocket
# --------------------------------------------------------------------
# Di produksi, gunakan nilai acak yang berbeda dari contoh.
REVERB_APP_ID=GANTI_APP_ID
REVERB_APP_KEY=GANTI_APP_KEY
REVERB_APP_SECRET=GANTI_APP_SECRET

# Server melakukan broadcast langsung ke Reverb lewat loopback.
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Proses Reverb hanya listen lokal; Nginx meneruskan /app ke sini.
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

# Alamat publik yang dipakai browser (melalui Nginx /app/).
AMCS_REVERB_CLIENT_HOST=amcs.example.com
AMCS_REVERB_CLIENT_PORT=443
AMCS_REVERB_CLIENT_SCHEME=https

# Batasi origin WebSocket ke domain aplikasi.
REVERB_ALLOWED_ORIGINS=https://amcs.example.com

# --------------------------------------------------------------------
# AMCS
# --------------------------------------------------------------------
AMCS_AIRPORT_NAME="Bandara Syukuran Aminuddin Amir Luwuk"
AMCS_AIRPORT_ICAO="WAMW"
AMCS_DELAY_THRESHOLD_MINUTES=30
AMCS_REALTIME_BROADCAST=true
AMCS_REALTIME_POLL_SECONDS=3
AMCS_REALTIME_FALLBACK_POLL_SECONDS=15
```

Buat nilai acak untuk Reverb:

```bash
echo "REVERB_APP_ID=$(openssl rand -hex 8)"
echo "REVERB_APP_KEY=$(openssl rand -hex 16)"
echo "REVERB_APP_SECRET=$(openssl rand -hex 32)"
```

Salin hasilnya ke `.env`.

> Jangan pernah commit `.env` ke repository. File ini berisi password database
> dan secret Reverb.

### 7.5 Permission

```bash
cd /var/www/amcs

sudo chown -R deploy:www-data .
sudo chmod -R ug+rwX .

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

sudo chown deploy:www-data .env
sudo chmod 640 .env
```

Pastikan user `deploy` ada di grup `www-data`:

```bash
groups deploy
```

---

## 8. Migrasi database dan data awal

Jalankan migrasi:

```bash
cd /var/www/amcs
php artisan migrate --force
```

Seed **hanya master data dan role** (jangan jalankan `db:seed` tanpa class di
produksi karena akan membuat akun demo dengan password default):

```bash
php artisan db:seed --class=RoleAndPermissionSeeder --force
php artisan db:seed --class=AircraftTypeSeeder --force
php artisan db:seed --class=AirlineSeeder --force
php artisan db:seed --class=ParkingStandSeeder --force
```

Buat akun administrator pertama:

```bash
php artisan tinker
```

> Jika Laravel meminta konfirmasi karena `APP_ENV=production`, ketik `yes`.

```php
$user = App\Models\User::create([
    'name' => 'Administrator AMCS',
    'email' => 'admin@amcs.example.com',
    'password' => 'GANTI_PASSWORD_ADMIN_KUAT',
    'active' => true,
]);

$user->assignRole('admin');
```

Ketik `exit` untuk keluar dari Tinker.

> Password otomatis di-hash oleh model `User`. Jangan menggunakan password
> contoh dari README di produksi.

Verifikasi:

```bash
php artisan tinker
```

```php
echo App\Models\User::count();
echo App\Models\ParkingStand::count();
echo App\Models\AircraftType::count();
echo App\Models\Airline::count();
```

---

## 9. Konfigurasi Nginx

Buat file:

```bash
sudo nano /etc/nginx/sites-available/amcs
```

Isi:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name amcs.example.com;

    root /var/www/amcs/public;
    index index.php index.html;

    charset utf-8;
    client_max_body_size 20m;

    access_log /var/log/nginx/amcs.access.log;
    error_log  /var/log/nginx/amcs.error.log;

    # Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;

        # Penting agar Laravel mendeteksi HTTPS dengan benar.
        fastcgi_param HTTPS $https if_not_empty;

        fastcgi_read_timeout 120;
    }

    # Blokir file tersembunyi (kecuali ACME challenge).
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # -----------------------------------------------------------------
    # Laravel Reverb - WebSocket
    # Pusher client memakai path /app/{key}
    # -----------------------------------------------------------------
    location /app/ {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 600s;
        proxy_send_timeout 600s;
        proxy_buffering off;
        proxy_pass http://127.0.0.1:8080;
    }

}
```

Aktifkan site:

```bash
sudo ln -s /etc/nginx/sites-available/amcs /etc/nginx/sites-enabled/amcs
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl enable --now nginx
sudo systemctl reload nginx
```

### 9.1 HTTPS dengan Certbot

Pastikan DNS sudah mengarah ke server, lalu:

```bash
sudo certbot --nginx -d amcs.example.com --redirect
```

Certbot akan menambahkan konfigurasi TLS dan redirect HTTP → HTTPS pada file
yang sama. Periksa kembali:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Pastikan blok HTTPS tetap memiliki `location /app/`. Uji renewal:

```bash
sudo certbot renew --dry-run
```

> Jika memakai Cloudflare, aktifkan **WebSockets** dan gunakan mode SSL
> **Full (strict)**.

Jika ada reverse proxy/load balancer **di depan Nginx** (mis. Cloudflare),
Laravel perlu mempercayai proxy tersebut agar skema HTTPS dan IP klien benar.
Tambahkan pada `bootstrap/app.php` di dalam `withMiddleware`:

```php
$middleware->trustProxies(at: [
    '127.0.0.1',
    'IP_PROXY_ATAU_RANGE_CLOUDFLARE',
]);
```

Jika hanya Nginx lokal yang menangani TLS, konfigurasi ini tidak diperlukan.

---

## 10. Supervisor: Reverb dan Queue Worker

### 10.1 Queue worker

```bash
sudo nano /etc/supervisor/conf.d/amcs-queue.conf
```

```ini
[program:amcs-queue]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/amcs/artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600
directory=/var/www/amcs
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/amcs/storage/logs/queue-worker.log
stopwaitsecs=120
```

### 10.2 Reverb

```bash
sudo nano /etc/supervisor/conf.d/amcs-reverb.conf
```

```ini
[program:amcs-reverb]
command=/usr/bin/php /var/www/amcs/artisan reverb:start
directory=/var/www/amcs
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/amcs/storage/logs/reverb.log
stopasgroup=true
killasgroup=true
stopwaitsecs=10
```

Reverb membaca `REVERB_SERVER_HOST=127.0.0.1` dan
`REVERB_SERVER_PORT=8080` dari `.env`.

### 10.3 Aktifkan

```bash
sudo systemctl enable --now supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start amcs-queue:*
sudo supervisorctl start amcs-reverb
sudo supervisorctl status
```

Pastikan Reverb listen lokal:

```bash
sudo ss -ltnp | grep 8080
```

Output harus menunjukkan `127.0.0.1:8080`, bukan `0.0.0.0:8080`.

---

## 11. Scheduler Laravel

Scheduler menjalankan backup database harian dan arsip log bulanan
(lihat `routes/console.php`).

```bash
sudo nano /etc/cron.d/amcs
```

```cron
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

* * * * * www-data cd /var/www/amcs && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Pastikan ada newline di akhir file, lalu:

```bash
sudo chmod 644 /etc/cron.d/amcs
sudo -u www-data /usr/bin/php /var/www/amcs/artisan schedule:list
```

Uji backup manual:

```bash
cd /var/www/amcs
sudo -u www-data /usr/bin/php artisan amcs:backup-database --keep=30
ls -lh storage/app/backups
```

Uji arsip log tanpa menghapus data:

```bash
sudo -u www-data /usr/bin/php artisan amcs:archive-activity --days=1095 --dry-run
```

> Backup berada di `storage/app/backups`. Salin juga ke storage eksternal
> (S3/NAS/rsync) secara berkala. Backup di server yang sama bukan backup
> yang cukup jika server hilang.

---

## 12. Optimasi produksi

Setelah `.env`, database, dan permission benar:

```bash
cd /var/www/amcs

php artisan optimize:clear
php artisan optimize

sudo systemctl reload php8.3-fpm
php artisan queue:restart
sudo supervisorctl restart amcs-reverb
```

Perintah `php artisan optimize` melakukan cache config, event, route, dan view.
Route `/` sudah menggunakan controller sehingga route caching aman.

---

## 13. Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

Jangan membuka port `8080` ke publik. Port tersebut hanya untuk Nginx lokal.
MySQL juga cukup listen di `127.0.0.1`.

---

## 14. Verifikasi deployment

### 14.1 Health check

```bash
curl -I https://amcs.example.com/login
curl -s https://amcs.example.com/up
```

Status yang diharapkan:

- `/login` → `200 OK`
- `/up` → `200 OK`

### 14.2 Reverb dan WebSocket

```bash
sudo supervisorctl status
sudo tail -n 50 /var/www/amcs/storage/logs/reverb.log
sudo ss -ltnp | grep 8080
```

Di browser:

1. Buka `https://amcs.example.com` dan login.
2. Buka **Dashboard AMC** atau **Monitor ATC**.
3. Buka Developer Tools → **Network** → **WS**.
4. Pastikan koneksi WebSocket ke `/app/...` berstatus `101 Switching Protocols`.
5. Indikator koneksi di topbar harus menampilkan **Realtime**.
6. Lakukan perubahan status dari akun lain / tab lain; papan stand harus
   berubah tanpa menunggu polling 3 detik.

Jika indikator menampilkan **Polling**, aplikasi tetap berjalan dengan fallback
polling, tetapi periksa proses Reverb, konfigurasi `/app/`, dan
`REVERB_APP_KEY`.

### 14.3 Cek log

```bash
sudo tail -f /var/www/amcs/storage/logs/laravel.log
sudo tail -f /var/www/amcs/storage/logs/reverb.log
sudo tail -f /var/www/amcs/storage/logs/queue-worker.log
sudo tail -f /var/log/nginx/amcs.error.log
```

---

## 15. Prosedur update / deploy ulang

Jalankan sebagai user `deploy`:

```bash
cd /var/www/amcs

php artisan down --retry=60

git pull origin main   # sesuaikan nama branch (main/master)

composer install --no-dev --optimize-autoloader

php artisan migrate --force

# Idempotent: sinkronkan role/permission bila ada perubahan.
php artisan db:seed --class=RoleAndPermissionSeeder --force

php artisan optimize:clear
php artisan optimize

php artisan queue:restart
sudo supervisorctl restart amcs-reverb

sudo systemctl reload php8.3-fpm

php artisan up
```

Jika aset klien Echo/Pusher berubah:

```bash
npm ci
npm run vendor:realtime
```

Tidak perlu `npm run build` untuk aplikasi saat ini karena CSS/JS produksi
berada langsung di `public/`.

---

## 16. Troubleshooting

### 16.1 Halaman 502 Bad Gateway

Periksa PHP-FPM:

```bash
sudo systemctl status php8.3-fpm
sudo journalctl -u php8.3-fpm -n 100 --no-pager
sudo tail -n 100 /var/log/nginx/amcs.error.log
```

Pastikan socket `/run/php/php8.3-fpm.sock` sesuai konfigurasi Nginx.

### 16.2 500 Internal Server Error

```bash
sudo tail -f /var/www/amcs/storage/logs/laravel.log
```

Biasanya karena:

- `.env` belum benar
- `APP_KEY` kosong
- permission `storage/` atau `bootstrap/cache/`
- migrasi belum dijalankan

Perbaiki permission:

```bash
sudo chown -R www-data:www-data /var/www/amcs/storage /var/www/amcs/bootstrap/cache
sudo chmod -R 775 /var/www/amcs/storage /var/www/amcs/bootstrap/cache
```

### 16.3 WebSocket gagal / tetap Polling

Periksa:

```bash
sudo supervisorctl status amcs-reverb
sudo tail -n 100 /var/www/amcs/storage/logs/reverb.log
curl -I https://amcs.example.com/app/
```

Yang perlu dicek:

- `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`
- `AMCS_REVERB_CLIENT_HOST=amcs.example.com`,
  `AMCS_REVERB_CLIENT_PORT=443`, `AMCS_REVERB_CLIENT_SCHEME=https`
- `REVERB_APP_KEY` sama antara server dan meta tag halaman
- Nginx memiliki `location /app/`
- `REVERB_ALLOWED_ORIGINS` memuat `https://amcs.example.com`
- Cloudflare/proxy meneruskan WebSocket
- `sudo ss -ltnp | grep 8080` menunjukkan Reverb berjalan

Setelah mengubah `.env`:

```bash
php artisan optimize:clear
php artisan optimize
sudo supervisorctl restart amcs-reverb
sudo systemctl reload php8.3-fpm
```

### 16.4 Login berhasil tetapi API mengembalikan 401

Pastikan:

```env
APP_URL=https://amcs.example.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=amcs.example.com
```

Jika di belakang reverse proxy tambahan, pastikan header
`X-Forwarded-Proto: https` diteruskan.

### 16.5 Export Excel gagal

Pastikan ekstensi PHP `zip` aktif:

```bash
php -m | grep zip
```

Jika belum:

```bash
sudo apt install php8.3-zip
sudo systemctl restart php8.3-fpm
```

### 16.6 Backup gagal

Pastikan `mysqldump` tersedia untuk user `www-data`:

```bash
which mysqldump
sudo -u www-data which mysqldump
sudo -u www-data /usr/bin/php /var/www/amcs/artisan amcs:backup-database --keep=30
```

Periksa juga kredensial database di `.env` dan permission
`storage/app/backups`.

---

## 17. Checklist go-live

- [ ] Domain mengarah ke server dan HTTPS aktif.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] `APP_KEY` sudah digenerate.
- [ ] Password database dan secret Reverb bukan nilai contoh.
- [ ] `.env` tidak dapat diakses dari browser.
- [ ] Migrasi dan seeder master data selesai.
- [ ] Akun admin pertama dibuat dengan password kuat.
- [ ] Login, dashboard, monitor ATC, laporan, dan export diuji.
- [ ] WebSocket menampilkan **Realtime** di dashboard dan ATC.
- [ ] Permission `storage/` dan `bootstrap/cache/` benar.
- [ ] Reverb dan queue worker berjalan di Supervisor.
- [ ] `schedule:run` berjalan tiap menit.
- [ ] Backup manual berhasil dan disalin ke luar server.
- [ ] Firewall hanya membuka SSH, HTTP, dan HTTPS.
- [ ] Log error dipantau.
- [ ] Prosedur restore backup diuji.

---

## 18. Catatan keamanan produksi

- Jangan menjalankan `php artisan db:seed` tanpa class di produksi.
- Jangan menggunakan akun/password demo (`admin@amcs.local`, `Amcs#2026`).
- Ganti `REVERB_APP_KEY`, `REVERB_APP_SECRET`, dan password database.
- Batasi `REVERB_ALLOWED_ORIGINS` ke domain aplikasi.
- Jangan membuka port `8080` atau `3306` ke internet.
- Gunakan HTTPS di seluruh trafik.
- Simpan backup terenkripsi di lokasi terpisah.
- Audit akses SSH dan gunakan SSH key, bukan password.
- Pertimbangkan `fail2ban` untuk SSH/Nginx.
- Perbarui paket sistem dan dependency secara berkala:
  ```bash
  sudo apt update && sudo apt upgrade
  composer update --no-dev
  ```
  Lakukan pengujian di staging sebelum update produksi.
