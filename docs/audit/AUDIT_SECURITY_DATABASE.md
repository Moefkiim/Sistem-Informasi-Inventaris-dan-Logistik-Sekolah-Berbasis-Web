# AUDIT SECURITY & DATABASE INTEGRITY: SIILS

Dokumen ini memuat evaluasi keamanan sistem, audit otorisasi, penegakan isolasi jurusan (multi-tenancy scoping), serta integritas skema database MySQL.

---

## 1. Audit Otorisasi & Akses Data

### 1.1 Matriks Guard & Middleware
Aplikasi menerapkan tiga lapis middleware otorisasi pada seluruh rute privat (`web.php`):
1. **`auth`**: Memastikan request berasal dari sesi pengguna terautentikasi.
2. **`App\Http\Middleware\EnsureUserRole`**:
   * Memvalidasi bahwa akun memiliki status aktif (`is_active == true`). Pengguna non-aktif langsung di-logout seketika dan diarahkan ke `/login` dengan flash error.
   * Memvalidasi kesesuaian peran (`role:kajur`, `role:sarpras`, `role:kepala_sekolah`). Akses yang tidak sah menghasilkan respon `403 Forbidden`.
3. **`App\Http\Middleware\EnsurePasswordChanged`**:
   * Mendeteksi flag `must_change_password`. Jika akun baru saja direset oleh Sarpras, seluruh request selain `/profile` dan `/logout` akan dialihkan paksa ke halaman ganti kata sandi.

### 1.2 Isolasi Jurusan (Department Scoping)
* **Kajur:** Dibatasi ketat hanya pada data yang memiliki `department == $user->department`.
  * Rute inventaris Kajur (`/kajur/inventory/{item}`) memeriksa kepemilikan jurusan dan melempar `403 Forbidden` jika item milik jurusan lain.
  * Pengajuan barang Kajur (`/kajur/submissions/{submission}`) memeriksa `user_id == $user->id` atau jurusan yang sama.
* **Sarpras & Kepala Sekolah:** Memiliki wewenang lintas jurusan (*global scope*) untuk monitoring dan pengelolaan logistik sekolah secara terpusat.

---

## 2. Keamanan Aplikasi & Request Handling

* **CSRF Protection:** Seluruh form HTTP (POST, PUT, DELETE) dilengkapi directive `@csrf`.
* **XSS Defense:** Seluruh output Blade menggunakan ekspresi `{{ ... }}` (escaped HTML) secara konsisten.
* **SQL Injection Prevention:** Seluruh query Eloquent dan Query Builder menggunakan parameter binding. Tidak ada string concatenation mentah pada klausa `where`.
* **Mass Assignment Guard:** Kolom-kolom sensitif (`role`, `is_active`, `must_change_password`, `status`, `stock`) sengaja dikecualikan dari array `$fillable` pada model Eloquent dan hanya dapat diubah melalui manipulasi properti eksplisit pada service/controller resmi.
* **Excel Formula Injection:** `ReportExportService` dan `InventoryImportService` menerapkan pembersihan karakter prefix berbahaya (`=`, `+`, `-`, `@`, `\t`, `\r`) pada sel spreadsheet untuk mencegah eksekusi formula dinamis di Microsoft Excel.
* **Path Traversal & File Upload:** Berkas dokumen BAST/Nota divalidasi MIME type (PDF, JPEG, PNG, max 10MB), disimpan menggunakan nama acak yang dihasilkan sistem (`hashName`), diletakkan di disk privat `storage/app/private/documents`, dan diunduh melalui `Storage::download` terautentikasi.

---

## 3. Audit Integritas Database & Skema MySQL

### 3.1 Skema & Konvensi Tabel
* **Tabel Utama (22 Migrasi):** `users`, `locations`, `items`, `asset_units`, `incoming_items`, `outgoing_items`, `distributions`, `submissions`, `submission_items`, `submission_histories`, `loans`, `activity_logs`, `condition_histories`, `location_histories`, `asset_unit_status_histories`, `documents`, `notifications`.
* **Karakter Set:** Seluruh tabel menggunakan `utf8mb4` dengan collation `utf8mb4_unicode_ci` yang kompatibel penuh dengan MySQL 8.0 dan MariaDB 10.4+.
* **Kunci Asing (Foreign Keys) & Soft Deletes:** Master data seperti `items` dan `locations` menggunakan `SoftDeletes` untuk menjaga integritas riwayat logistik masa lalu tanpa merusak relasi foreign key.

### 3.2 Transaksi & Pencegahan Race Condition
* Operasi multi-tabel (seperti realisasi pengajuan, transaksi barang masuk, barang keluar, dan peminjaman) dibungkus dalam blok `DB::transaction()` atomik.
* Operasi perubahan status unit aset menggunakan penguncian baris (`lockForUpdate()`) untuk mencegah dua proses konkuren meminjam atau mengubah unit fisik yang sama secara simultan (`AssetUnitConcurrencyTest`).
* Konsistensi stok master (`items.stock`) diverifikasi selalu setara dengan jumlah unit non-`disposed` untuk barang individual (`AssetUnitInvariantTest`).

