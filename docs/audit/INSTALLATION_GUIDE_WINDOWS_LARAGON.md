# PANDUAN INSTALASI WINDOWS + LARAGON + MYSQL (SIILS)

Panduan langkah demi langkah yang telah diverifikasi untuk memasang dan menjalankan aplikasi SIILS secara lokal pada sistem operasi Windows menggunakan Laragon.

---

## 1. Prasyarat Sistem

* **OS:** Windows 10 / 11 (64-bit).
* **Lingkungan Web Server:** [Laragon Full](https://laragon.org/download/) (direkomendasikan versi yang menyertakan Apache, MySQL 8.0, dan PHP 8.3+).
* **PHP:** Versi 8.3 atau 8.4 (Aktifkan ekstensi: `pdo_mysql`, `gd`, `zip`, `mbstring`, `fileinfo`, `curl`, `openssl`).
* **Composer:** Composer v2.x.
* **Database Client:** HeidiSQL (bawaan Laragon) atau phpMyAdmin.

---

## 2. Langkah Instalasi Otomatis (Metode Tercepat)

1. Buka aplikasi **Laragon**, lalu klik tombol **Start All** hingga Apache dan MySQL menyala (indikator hijau).
2. Buka folder root web Laragon di `C:\laragon\www\`.
3. Buka **Terminal Laragon** (Klik kanan ikon Laragon -> **Terminal**), lalu jalankan perintah berikut secara berurutan:

```bash
# 1. Clone repository ke dalam folder www
git clone https://github.com/Moefkiim/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web.git siils
cd siils

# 2. Jalankan satu perintah instalasi terpadu
composer setup
```

Perintah `composer setup` secara otomatis menjalankan:
* `composer install` (mengunduh dependensi PHP).
* Pembuatan file `.env` dari `.env.example`.
* Pembuatan kunci aplikasi `APP_KEY`.
* Pembuatan database `siils` di MySQL lokal bila belum ada.
* Menjalankan seluruh migrasi skema tabel.
* Mengisi data demo awal (`DatabaseSeeder`).

---

## 3. Langkah Instalasi Manual

Jika Anda ingin mengkonfigurasi database dengan nama khusus (misal `inventaris_sekolah`):

1. **Buat Database di MySQL:**
   Buka HeidiSQL atau Terminal MySQL, lalu eksekusi query berikut:
   ```sql
   CREATE DATABASE IF NOT EXISTS inventaris_sekolah CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Salin dan Sesuaikan Konfigurasi `.env`:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Buka file `.env` dengan teks editor, pastikan parameter berikut terisi:
   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=inventaris_sekolah
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Jalankan Migrasi & Data Demo:**
   ```bash
   php artisan migrate --seed
   ```

4. **Periksa Kesiapan Aplikasi:**
   ```bash
   php artisan siils:check
   ```
   Pastikan seluruh indikator berstatus centang hijau (exit code 0).

---

## 4. Akun Login Pengujian Bawaan

| Role Pengguna | Username | Email Alternatif | Password Default | Akses Wewenang |
|---|---|---|---|---|
| **Kajur RPL** | `kajur_rpl` | `kajur.rpl@sekolah.sch.id` | `password123` | Buat & pantau pengajuan, inventaris jurusan RPL |
| **Kajur TKRO** | `kajur_tkro` | `kajur.tkro@sekolah.sch.id` | `password123` | Pengajuan & inventaris jurusan TKRO |
| **Sarpras** | `sarpras` | `sarpras@sekolah.sch.id` | `password123` | Pengelolaan inventaris, logistik, review, peminjaman |
| **Kepala Sekolah** | `kepsek` | `kepsek@sekolah.sch.id` | `password123` | Approval / reject pengajuan, rekapitulasi laporan |
| **User Non-Aktif** | `user_nonaktif` | `nonaktif@sekolah.sch.id` | `password123` | Uji coba penolakan autentikasi |

---

## 5. Menjalankan Server Pengembang

Jika menggunakan Laragon dengan fitur Auto Virtual Host, buka:
* URL: `http://siils.test`

Atau jalankan server pengembang bawaan PHP:
```bash
php artisan serve
```
Buka peramban di `http://127.0.0.1:8000/login`.

---

## 6. Pemecahan Masalah Umum (Troubleshooting)

* **`SQLSTATE[HY000] [2002] Connection refused`**:
  * *Penyebab:* Service MySQL belum menyala.
  * *Solusi:* Buka Laragon dan pastikan tombol **Start All** sudah diklik.
* **`SQLSTATE[HY000] [1045] Access denied for user`**:
  * *Penyebab:* Kredensial `DB_USERNAME` atau `DB_PASSWORD` di `.env` salah.
  * *Solusi:* Di Laragon default adalah user `root` dengan password kosong.
* **Perubahan `.env` tidak berefek**:
  * *Solusi:* Jalankan `php artisan config:clear` untuk menghapus cache konfigurasi lama.
* **Error `419 Page Expired` saat submit form**:
  * *Solusi:* Pastikan `APP_URL` di `.env` sesuai dengan domain/port yang dibuka di browser.

