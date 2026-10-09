# Laporan Audit dan Perbaikan Menyeluruh
## Sistem Informasi Inventaris dan Logistik Sekolah Berbasis Web

**Tanggal:** 9 Oktober 2026  
**Status:** Audit & Remediasi Selesai (Semua Pengujian Lulus)

---

## 1. Ringkasan Eksekutif

Audit komprehensif telah dilaksanakan terhadap seluruh komponen aplikasi **Sistem Informasi Inventaris dan Logistik Sekolah Berbasis Web**. Audit ini mencakup integritas data, aturan bisnis multi-peran (Kajur, Sarpras, Kepala Sekolah), arsitektur model dan database, alur transaksi logistik, hak akses jurusan, keandalan audit trail, hingga konsistensi UI/UX.

### Status Kesehatan Aplikasi
- **Integritas Aturan Bisnis:** Sangat Baik. Semua batasan peran (role scoping) dan hak akses multi-tenancy jurusan berfungsi sesuai spesifikasi sekolah (`AGENTS.md`).
- **Audit Trail & Riwayat:** Dilindungi penuh. Tidak ada penghapusan data histori mutasi lokasi atau kondisi (`append-only`). Relasi logistik dan audit trail telah diperkuat dengan `withTrashed()` untuk mencegah *broken reference* atau `Attempt to read property on null` jika master item/lokasi diarsipkan.
- **Integritas Relasional Lokasi:** Diperbaiki. Lokasi kini tidak dapat dihapus jika masih menampung `items` maupun `asset_units`.
- **Konsistensi UI/UX:** Diperbaiki. Bug pergeseran kolom tabel pada inventaris Sarpras, duplikasi penambahan baris form pengajuan Kajur, dan dropdown jurusan dokumen telah diselesaikan.
- **Hasil Pengujian Otomatis:** **175 tests passed, 662 assertions passed (100% Green)**.

---

## 2. Temuan Audit per Kategori

### A. Arsitektur & Model
1. **Relasi `Location` ke `AssetUnit` Belum Didefinisikan:**
   - *Temuan:* Model [Location](file:///c:/Users/user/OneDrive/Documents/Project%20Web%20Inventaris/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/app/Models/Location.php) hanya memiliki relasi `items()`, padahal sistem inventaris sekolah mendukung unit individual (`AssetUnit`). Pengecekan sebelum menghapus lokasi hanya memeriksa `$location->items()->count()`, sehingga lokasi yang memiliki unit aktif berpotensi terhapus.
   - *Perbaikan:* Ditambahkan relasi `assetUnits(): HasMany` pada `Location` dan validasi proteksi hapus di [LocationController](file:///c:/Users/user/OneDrive/Documents/Project%20Web%20Inventaris/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/app/Http/Controllers/Sarpras/LocationController.php).
2. **Kueri Agregasi Bulanan Terikat ke Database Engine Tertentu:**
   - *Temuan:* Pada [HomeController](file:///c:/Users/user/OneDrive/Documents/Project%20Web%20Inventaris/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/app/Http/Controllers/HomeController.php), kueri tren bulanan menggunakan fungsi `DATE_FORMAT()` spesifik MySQL tanpa penanganan driver SQLite/PostgreSQL.
   - *Perbaikan:* Logika `monthlyTrend` diubah menjadi database-agnostic dengan memeriksa driver koneksi (`sqlite`, `pgsql`, atau fallback MySQL/MariaDB).

### B. Alur Transaksi & Logistik
1. **Potensi Race-Condition pada Penomoran Pengajuan:**
   - *Temuan:* Metode `generateSubmissionNumber()` pada [SubmissionController](file:///c:/Users/user/OneDrive/Documents/Project%20Web%20Inventaris/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/app/Http/Controllers/Kajur/SubmissionController.php) mengambil nomor terakhir tanpa *pessimistic lock*.
   - *Perbaikan:* Ditambahkan `lockForUpdate()` dalam blok transaksi database untuk menjamin nomor serial pengajuan selalu unik bahkan pada lonjakan request bersamaan.
2. **Filter Laporan Triwulan:**
   - *Temuan:* Preset filter periode pada [ReportController](file:///c:/Users/user/OneDrive/Documents/Project%20Web%20Inventaris/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/app/Http/Controllers/ReportController.php) hanya menyediakan Hari Ini, Bulan Ini, dan Tahun Ini, padahal pelaporan logistik sekolah umum menggunakan acuan triwulan.
   - *Perbaikan:* Ditambahkan filter preset `'triwulan'` (`startOfQuarter()` s/d `endOfQuarter()`).

### C. Hak Akses & Multi-tenancy Jurusan
1. **Validasi Scoping Kajur:**
   - *Temuan:* Kajur hanya boleh melihat dan mengelola data jurusannya sendiri. Pengecekan pada controller dan middleware sudah bekerja dengan sangat baik (`User::isKajur()`, filter `department`).
   - *Perbaikan:* Input jurusan pada modal dokumen Sarpras distandarisasi menggunakan pilihan dropdown dari `config('departments')` untuk mencegah variasi penulisan nama kejuruan.

### D. Audit Trail & Immutability
1. **Ketahanan Referensi Data yang Di-soft-delete:**
   - *Temuan:* Transaksi barang masuk, barang keluar, distribusi, mutasi lokasi, dan riwayat kondisi memerlukan relasi ke item dan lokasi. Jika master item atau lokasi di-soft-delete, relasi `belongsTo` standar akan menghasilkan `null`, berisiko memicu error pada tampilan logistik.
   - *Perbaikan:* Seluruh relasi logistik ([IncomingItem](file:///c:/Users/user/OneDrive/Documents/Project%20Web%20Inventaris/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/app/Models/IncomingItem.php), [OutgoingItem](file:///c:/Users/user/OneDrive/Documents/Project%20Web%20Inventaris/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/app/Models/OutgoingItem.php), [Distribution](file:///c:/Users/user/OneDrive/Documents/Project%20Web%20Inventaris/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/app/Models/Distribution.php), [LocationHistory](file:///c:/Users/user/OneDrive/Documents/Project%20Web%20Inventaris/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/app/Models/LocationHistory.php), [ConditionHistory](file:///c:/Users/user/OneDrive/Documents/Project%20Web%20Inventaris/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/app/Models/ConditionHistory.php)) telah ditambahkan `->withTrashed()`. View Blade juga dilengkapi *safe fallback* label `(Dihapus/Arsip)`.

### E. UI/UX & Konsistensi Tampilan
1. **Pergeseran Kolom Tabel Inventaris Sarpras:**
   - *Temuan:* Pada [index.blade.php](file:///c:/Users/user/OneDrive/Documents/Project%20Web%20Inventaris/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/resources/views/sarpras/inventory/index.blade.php), terdapat 8 kolom pada header `thead`, tetapi baris `tbody` hanya me-render 7 kolom karena kolom Kategori terlewat. Hal ini menyebabkan badge Stok tampil di bawah judul header Kategori.
   - *Perbaikan:* Ditambahkan kolom `<td>{{ $item->category }}</td>` pada `tbody` sehingga seluruh 8 kolom sejajar rapi.
2. **Duplikasi Penambahan Baris Form Pengajuan Kajur:**
   - *Temuan:* Pada form pengajuan item Kajur (`create.blade.php` dan `edit.blade.php`), event listener tombol "Tambah Item" didefinisikan 2 kali (sekali di `resources/js/app.js` dan sekali lagi di inline `<script>` blade). Akibatnya, setiap tombol diklik, dua baris input muncul sekaligus.
   - *Perbaikan:* Inline listener ganda dihapus dan digantikan integrasi class `btn-remove-item` yang dikelola terpusat oleh `app.js`.

---

## 3. Daftar Perubahan yang Dilakukan

| File | Klasifikasi | Alasan & Rincian Perubahan |
|---|---|---|
| `app/Models/Location.php` | Backend / Relasi | Menambahkan relasi `assetUnits(): HasMany` untuk mendeteksi unit individual di lokasi tersebut. |
| `app/Http/Controllers/Sarpras/LocationController.php` | Controller / Validasi | Mengikutsertakan `withCount(['items', 'assetUnits'])` dan mencegah penghapusan jika unit atau item > 0. |
| `resources/views/sarpras/locations/index.blade.php` | UI / Sarpras | Menampilkan jumlah Jenis Item dan Unit Aset, serta menonaktifkan tombol hapus jika masih ada aset terkait. |
| `app/Http/Controllers/Kajur/SubmissionController.php` | Controller / Transaksi | Menambahkan `lockForUpdate()` saat generasi nomor seri pengajuan untuk mencegah duplikasi identitas pengajuan. |
| `app/Models/IncomingItem.php`<br>`app/Models/OutgoingItem.php`<br>`app/Models/Distribution.php`<br>`app/Models/LocationHistory.php`<br>`app/Models/ConditionHistory.php` | Model / Audit Trail | Menambahkan `->withTrashed()` pada relasi `item()` dan `location()` / `toLocation()` agar riwayat mutasi tidak error saat master data diarsipkan. |
| `resources/views/sarpras/logistics/*.blade.php` | UI / Logistik | Memberikan penanganan null-safe pada nama barang, lokasi, dan jurusan dengan label `[Arsip/Dihapus]`. |
| `app/Http/Controllers/HomeController.php` | Controller / Dashboard | Membuat kueri agregasi bulanan fleksibel terhadap jenis database (SQLite, MySQL, PostgreSQL). |
| `app/Http/Controllers/ReportController.php` | Controller / Laporan | Menambahkan opsi preset triwulan (`startOfQuarter` s/d `endOfQuarter`). |
| `resources/views/sarpras/inventory/index.blade.php` | UI / Tabel | Menyelaraskan 8 kolom tabel inventaris Sarpras dengan menambahkan `<td>{{ $item->category }}</td>`. |
| `resources/views/kajur/submissions/create.blade.php`<br>`edit.blade.php` | UI / Form Dinamis | Menghilangkan script ganda penambah baris item sehingga 1 klik tombol menghasilkan tepat 1 baris input baru. |
| `resources/views/documents/index.blade.php` | UI / Dokumen | Mengganti text input jurusan menjadi dropdown `<select>` konsisten berbasis `config('departments')`. |
| `config/app.php` | Konfigurasi | Mengubah konfigurasi default ke timezone `Asia/Jakarta` dan locale `id` dengan dukungan fallback `.env`. |
| `tests/Feature/LocationManagementTest.php` | Pengujian / Feature | Menambahkan kasus uji bahwa lokasi yang menampung `assetUnits` tidak dapat dihapus. |

---

## 4. Hasil Pengujian

Pengujian dilakukan menggunakan suite PHPUnit bawaan Laravel dengan database in-memory:
- **Total Uji Dijalankan:** 175 Tests
- **Total Pernyataan (Assertions):** 662 Assertions
- **Hasil:** **100% Lulus (0 Gagal, 0 Error, 0 Warning)**
- **Waktu Eksekusi:** ~44 detik

### Cakupan Kasus Uji Penting:
1. **Hak Akses & Scoping Role:**
   - Kajur hanya dapat membuat, melihat, dan menyunting pengajuan berstatus draft milik jurusannya.
   - Kepala Sekolah dapat menyetujui (approve) atau menolak (reject) pengajuan beserta pencatatan catatan review.
   - Sarpras memiliki hak memproses pengajuan approved menjadi barang masuk / distribusi.
2. **Integritas Stok & Status Unit:**
   - Mutasi stok barang habis pakai / individual bertambah secara konsisten saat barang masuk dan berkurang saat barang keluar.
   - Status unit (`aktif`, `dipinjam`, `dalam_perbaikan`, `rusak_berat`, `diafkirkan`) sinkron dengan transaksi peminjaman dan perbaikan.
3. **Audit Trail Mutasi Lokasi & Riwayat Kondisi:**
   - Setiap mutasi lokasi mencatat entri baru di `location_histories` (from_location, to_location, user pemindah, catatan).
   - Setiap perubahan kondisi mencatat entri baru di `condition_histories` (kondisi lama, kondisi baru, catatan).
   - Penghapusan atau pengarsipan lokasi/item tidak merusak keterbacaan riwayat lama.

---

## 5. Panduan Verifikasi Manual untuk Sekolah

Untuk memverifikasi sistem secara langsung pada lingkungan browser sekolah, jalankan skenario uji berikut:

### Skenario 1: Siklus Pengajuan Pengadaan (Kajur ➔ Kepala Sekolah ➔ Sarpras)
1. **Login sebagai Kajur** (misal: Kajur RPL).
2. Buka menu **Pengajuan** ➔ klik **Buat Pengajuan Baru**.
3. Klik tombol **Tambah Item** ➔ pastikan hanya muncul **1 baris baru** per klik. Isi nama barang, jumlah, dan estimasi harga.
4. Simpan sebagai **Draft** ➔ verifikasi tombol Edit masih tersedia.
5. Klik **Ajukan (Submit)** ➔ status berubah menjadi `submitted` dan form terkunci dari pengeditan Kajur.
6. **Login sebagai Kepala Sekolah**.
7. Buka menu **Pengajuan** ➔ temukan pengajuan Kajur RPL tadi ➔ klik **Review**.
8. Masukkan catatan dan klik **Setujui (Approve)** ➔ status berubah menjadi `approved`.
9. **Login sebagai Sarpras**.
10. Buka menu **Pengajuan** ➔ klik **Proses Pengadaan** untuk mencatat penerimaan barang ke inventaris.

### Skenario 2: Mutasi Lokasi & Pencatatan Kondisi Aset
1. **Login sebagai Sarpras**.
2. Masuk ke **Data Master ➔ Lokasi**. Buat lokasi baru bernama "Ruang Uji Coba".
3. Masuk ke **Inventaris Master**, pilih salah satu barang individual ➔ klik **Detail**.
4. Pindahkan salah satu unit aset ke "Ruang Uji Coba".
5. Kembali ke menu **Data Master ➔ Lokasi** ➔ coba hapus "Ruang Uji Coba".
   - *Hasil yang diharapkan:* Muncul notifikasi penolakan karena lokasi masih memiliki unit aset terdaftar.
6. Pindahkan kembali unit tersebut ke lokasi asal, lalu lakukan pengubahan kondisi dari `baik` ke `rusak_ringan`.
7. Periksa tab **Riwayat Kondisi** dan **Riwayat Lokasi** pada halaman detail barang ➔ pastikan riwayat bertambah dan tidak ada error data kosong.

### Skenario 3: Verifikasi Filter Laporan dan Dokumen
1. Masuk ke menu **Laporan**.
2. Pilih preset periode **Triwulan ini** ➔ pastikan data terfilter antara awal kuartal hingga akhir kuartal kalender berjalan.
3. Gunakan filter jurusan untuk memverifikasi isolasi rekapitulasi per kejuruan.
4. Masuk ke menu **Dokumen** ➔ klik tombol **Upload Dokumen** ➔ pastikan dropdown Jurusan menampilkan daftar jurusan resmi sekolah.

---

## 6. Rekomendasi Pengembangan Non-Intrusif (Fase Berikutnya)

Sesuai dengan batasan ketat pada `AGENTS.md` (tidak menambahkan QR Code, WhatsApp gateway, mobile app, accounting, pihak ketiga), beberapa saran perbaikan non-intrusif yang direkomendasikan di masa depan antara lain:
1. **Paginasi Konfiguratif:** Menambahkan pilihan jumlah baris per halaman (10, 25, 50, 100) pada tabel riwayat audit trail yang memiliki volume data besar.
2. **Indeks Database Tambahan:** Menambahkan composite index pada kolom `(item_id, created_at)` pada tabel `condition_histories` dan `location_histories` untuk mengoptimalkan performa kueri riwayat aset seiring berjalannya tahun ajaran.
3. **Backup Otomatis Skrip DB:** Menyiapkan skrip cron/artisan backup basis data sekolah harian lokal tanpa ketergantungan layanan cloud berbayar.
