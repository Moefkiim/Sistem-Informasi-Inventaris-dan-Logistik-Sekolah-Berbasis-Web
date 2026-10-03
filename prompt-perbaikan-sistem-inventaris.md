# PROMPT UNTUK AI AGENT — PERBAIKAN SISTEM INVENTARIS SEKOLAH (LARAVEL)

Kerjakan seluruh task di bawah secara berurutan per fase (Phase 0 → Phase 5), berhenti di akhir tiap fase untuk menjalankan test sebelum lanjut ke fase berikutnya. Ini hasil audit menyeluruh (backend logic, autentikasi/otorisasi, UI/UX) pada codebase Laravel 13 yang sudah ada — skeleton-nya sendiri solid (sudah pakai DB transaction di beberapa tempat, CSRF, role middleware, password di-hash, tidak ada `{!! !!}` yang bocor XSS), tapi ada 5 isu kritis/berisiko tinggi yang harus diperbaiki dulu.

**Sebelum mulai:** nomor baris (`:67`, `:103`, dst.) di bawah ini berdasarkan kondisi kode saat audit dilakukan — cek ulang isi file sebelum edit, karena nomor baris bisa saja sudah bergeser kalau ada perubahan lain di antara waktu audit dan sekarang. Jangan asumsikan nomor barisnya pasti masih sama persis.

Jalankan test suite yang ada (12 test, baseline sudah hijau) setelah tiap fase, dan tambahkan test baru sesuai bagian "Testing" di akhir dokumen ini begitu domain logic terkait sudah diperbaiki.

---

## PHASE 0 — Buka Jalur yang Macet (KRITIS, <10 menit)

### 1. Perbaiki typo nama route (C1)
Alur approval kepala sekolah akan 500 error karena nama route tidak konsisten:
- `app/Http/Controllers/Principal/ApprovalController.php:67` — ganti `redirect()->route('principal.approval.index')` jadi `redirect()->route('kepala_sekolah.approval.index')`
- `resources/views/principal/approval/show.blade.php:103` — ganti referensi route `principal.approval.*` jadi `kepala_sekolah.approval.*`
- Cek juga: `show.blade.php:76` sudah benar pakai `kepala_sekolah.approval.decide` — jangan diubah, itu referensi yang benar.
- Grep seluruh codebase untuk sisa referensi `principal.approval.*` yang mungkin terlewat di tempat lain.

**Acceptance:** alur approval kepala sekolah bisa diakses end-to-end tanpa 500 error.

---

## PHASE 1 — Perbaiki Konsistensi Stok (KRITIS, domain logic)

### 2. Perbaiki `storeDistribution` (C-1 / F-17)
Ini bug paling besar di seluruh audit: distribusi barang saat ini **tidak pernah mengurangi stok**, tidak mengecek kecukupan stok, dan tidak mencatat riwayat lokasi. Efeknya stok bisa "menggembung" karena distribusi yang seharusnya memindahkan barang tidak tercatat dampaknya ke stok.

Perbaiki dengan:
- Bungkus seluruh proses dalam `DB::transaction()`.
- Guard concurrency: `Item::where('id', $id)->where('stock', '>=', $qty)->lockForUpdate()->first()`, atau pola atomic `decrement()` yang dicek baris terdampak.
- `Item::where('id', $validated['item_id'])->where('stock', '>=', $validated['quantity'])->decrement('stock', $validated['quantity'])` — kalau hasilnya 0 baris terdampak (stok tidak cukup), lempar exception/response error, jangan lanjut proses.
- Buat record `LocationHistory` baru: `from_location_id` = lokasi barang sebelumnya, `to_location_id` = `validated['to_location_id']`.
- Kalau memang mau lokasi master barang ikut berubah mengikuti distribusi terakhir: `Item::whereKey(...)->update(['location_id' => $to_location_id])` — pastikan ini keputusan bisnis yang benar (lokasi barang = lokasi distribusi terakhir), bukan asumsi sepihak; kalau ragu, tanyakan dulu sebelum menerapkan efek samping ini.
- Validasi `to_location_id` dengan `Rule::exists('locations', 'id')->whereNull('deleted_at')` (terkait juga C-3 di Phase 4) — supaya tidak bisa distribusi ke lokasi yang sudah soft-deleted.

### 3. Perkuat `storeOutgoing` (C-2)
Ada celah TOCTOU (time-of-check-to-time-of-use): pengecekan stok dilakukan sebelum transaksi dimulai, jadi ada window race condition kalau dua request barang keluar jalan bersamaan.
- Pindahkan pengecekan stok ke **dalam** `DB::transaction()`.
- Pakai pola atomic yang sama seperti nomor 2: `where('stock', '>=', $qty)->decrement(...)`, cek baris terdampak = 1.
- Hapus guard `$item->stock` yang dilakukan sebelum transaksi dimulai (itu yang jadi sumber race condition).

**Acceptance:** submit distribusi/barang-keluar dengan stok tidak cukup harus ditolak dengan pesan jelas, bukan diam-diam lolos. Dua request bersamaan terhadap item dengan stok terbatas tidak boleh membuat stok jadi negatif (test ini butuh simulasi concurrent request, lihat bagian Testing).

---

## PHASE 2 — Tegakkan Isolasi Peran/Departemen (TINGGI)

### 4. Perbaiki scoping departemen untuk Kajur (F-2, H-6)
Bug: kalau akun Kajur punya `department = null`, query `where('department', null)` di Laravel otomatis jadi `whereNull('department')` — efeknya Kajur dengan departemen belum di-set malah bisa **melihat seluruh data sekolah** (school-wide read), bukan dibatasi.
- `app/Http/Controllers/Kajur/InventoryController.php`: di method `index` dan `show`, kalau `$user->department === null` → `abort(403, 'Jurusan akun Kajur belum diatur.')`. Ganti perbandingan `!==` yang longgar dengan pengecekan strict + null-check eksplisit.
- `app/Http/Controllers/ReportController.php` (baris sekitar 36-50, cek ulang nomor pastinya): untuk laporan barang masuk dan keluar, tambahkan `if ($department) { $query->whereHas('item', fn($q) => $q->where('department', $department)); } else { /* kasus Kajur dengan department null */ abort(403, ...); }`.
- `app/Http/Controllers/ReportController.php` (baris sekitar 27-29): perlakukan `department = null` pada akun Kajur sebagai **kesalahan konfigurasi fatal**, bukan kondisi normal yang di-fallback jadi akses penuh.

### 5. Batasi pembuatan role (F-1)
Bug: Sarpras bisa membuat akun dengan role `kepala_sekolah` lewat form user management — artinya siapa pun yang punya akses Sarpras bisa membuat approver baru sesuka hati.
- `app/Http/Controllers/Sarpras/UserController.php` (sekitar baris 27): ganti validasi role dari `in:kajur,sarpras,kepala_sekolah` jadi `in:kajur,sarpras` (atau buat role `admin` terpisah kalau memang perlu ada yang bisa membuat kepala_sekolah, dengan role itu dipegang orang yang tepat).
- Tambahkan `required_if:role,kajur` pada field `department`, idealnya pakai custom rule yang menegaskan "department wajib diisi kalau role-nya Kajur".

### 6. Lindungi toggle status user (F-3)
- Di method `toggleStatus()`: larang menonaktifkan akun dengan role `kepala_sekolah` dan `sarpras` (atau role apa pun yang levelnya di atas yang sedang melakukan toggle). Pertahankan aturan yang sudah ada (tidak bisa toggle diri sendiri).

### 7. Perbaiki otorisasi & penyimpanan unduhan dokumen (F-5, F-2d)
- Sebaiknya pindahkan penyimpanan dokumen dari disk `public` ke disk `local`/private, lalu sajikan lewat method `download()` terkontrol — ini mencegah file bisa diakses langsung lewat URL publik tanpa autentikasi.
- Kalau karena alasan tertentu tetap harus pakai disk `public`, itu risikonya lebih tinggi dan perlu mitigasi tambahan — diskusikan dulu sebelum memutuskan tetap pakai `public`.
- `DocumentController::index/download/destroy` sudah membatasi akses Kajur berdasarkan department — pastikan ini dipasangkan dengan guard `department = null` dari nomor 4, supaya tidak ada celah yang sama muncul lagi di sini.

**Acceptance:** Kajur dengan `department = null` tidak bisa lihat data departemen lain. Sarpras tidak bisa membuat akun kepala_sekolah. User dengan role rendah tidak bisa menonaktifkan akun kepala_sekolah/sarpras. Dokumen tidak bisa diunduh anonim tanpa login.

---

## PHASE 3 — Perbaiki Sistem Desain Frontend & UX (TINGGI, kelihatan langsung)

### 8. Pilih satu sistem CSS (C2)
Saat ini `resources/views/documents/index.blade.php` pakai Tailwind, sementara ~20 view lain pakai Metronic/Bootstrap 5 — keduanya saling konflik (preflight Tailwind menimpa style Metronic), dan halaman ini tidak termuat styling-nya karena `@vite` Tailwind cuma ada di `welcome.blade.php`, tidak di layout utama.
- **Rekomendasi (risiko lebih rendah): tulis ulang `documents/index.blade.php` memakai class Metronic/Bootstrap 5**, supaya konsisten dengan 20 view lain dan menghindari konflik preflight.
- Alternatif (kalau tetap ingin pakai Tailwind di halaman ini saja): scope Tailwind pakai `@layer` dan load `@vite` hanya di halaman itu — ini lebih berisiko dan butuh pengujian ekstra, jangan dipilih kecuali ada alasan kuat untuk tetap pakai Tailwind di situ.

### 9. Tambahkan pagination dengan query string + view Metronic (C3, H1)
- Publish custom pagination view Metronic, atau override `Paginator::$defaultView` ke view Metronic yang sesuai.
- Tambahkan `->withQueryString()` ke **semua** pemanggilan `->links()` (ada 12 titik yang perlu dicek) — saat ini cuma `documents/index.blade.php:98` yang sudah benar.

### 10. Pindahkan JS keluar dari Blade + tambah submit guard (H2, M5)
- Logic tambah/hapus baris item saat ini di-duplikasi sebagai blok inline 34 baris di beberapa file create/edit — pindahkan ke `resources/js/app.js` sebagai template yang reusable, hapus duplikasinya.
- Tambahkan handler global `data-disable-on-submit` di semua form untuk mencegah double-submit.
- Hapus custom accordion handler di `layouts/app.blade.php` (sekitar baris 357-370) beserta CSS override-nya — percayakan ke data-API bawaan Metronic/Bootstrap, jangan reimplementasi manual.

### 11. Pastikan Phase 0 benar-benar menutup semua sisa referensi `principal.approval.*`
(Sudah dikerjakan di Phase 0 — langkah ini cuma pengingat untuk grep ulang memastikan tidak ada yang terlewat sebelum lanjut ke Phase 4.)

**Acceptance:** halaman `documents/index` tampil dengan styling benar. Semua pagination mempertahankan query string filter saat pindah halaman. Tidak ada lagi blok JS inline terduplikasi di view create/edit.

---

## PHASE 4 — Perkuat Transaksi & Audit Trail (MEDIUM)

### 12. Validasi terhadap data yang sudah soft-deleted (F-10, C-3)
- Ganti semua `exists:items,id` / `exists:locations,id` jadi `Rule::exists('items', 'id')->whereNull('deleted_at')` dan `Rule::exists('locations', 'id')->whereNull('deleted_at')`, terutama di `LogisticsController` dan `InventoryController`.
- Pastikan operasi `increment()`/`decrement()` pada `Item` juga dijaga agar tidak mengenai baris yang sudah soft-deleted (pakai `findOrFail` atau cek jumlah baris terdampak).

### 13. Jadikan riwayat append-only (M-2)
- Di `app/Models/LocationHistory.php` dan `ConditionHistory.php`, tambahkan `static::booted()` yang melempar exception pada event `updating`/`deleting` — riwayat semacam ini seharusnya tidak pernah diubah atau dihapus setelah dicatat.
- Pertimbangkan menghapus field yang bisa diubah dari `$fillable` supaya tidak ada jalur mass-assignment yang bisa menimpa riwayat lama.

### 14. Perketat `$fillable` (M-1, F-13)
- Model `Submission`: hapus `status`, `sarpras_*`, `principal_*`, `reviewed_at`, `decided_at` dari `$fillable` — field-field ini seharusnya cuma berubah lewat method state-transition tertentu, bukan lewat mass-assignment form biasa.
- Model `User`: hapus `role`, `is_active` dari `$fillable` — field sensitif begini di-set eksplisit lewat kode, bukan diterima langsung dari input form.
- Model `Document`: jangan mass-assign `documentable_type`/`documentable_id` langsung — set lewat relasi Eloquent, atau validasi kepemilikan morph-nya secara eksplisit.

### 15. Tambahkan rate limiting login (F-6)
- `routes/web.php` (sekitar baris 14): `Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');`

**Acceptance:** mencoba update/delete langsung ke riwayat lokasi/kondisi harus gagal. Submit form dengan field terlarang (misalnya `role` disisipkan manual di request) tidak mengubah field itu. Brute-force login kena throttle setelah 5 percobaan per menit.

---

## PHASE 5 — Polish (RENDAH, maintainability)

### 16. Satukan status/label/warna (M2-M3)
Ekstrak jadi satu komponen (`<x-status-badge>`) daripada 4 blok switch-case yang terduplikasi di tempat berbeda.

### 17. Tambahkan `old()` ke 5 form (M11)
Form logistik (barang masuk/keluar/distribusi), lokasi, dan user — pastikan input yang sudah diisi tidak hilang saat validasi gagal. Khusus form submission create/edit, pastikan baris-baris item juga ikut di-repopulate, bukan cuma field tunggal.

### 18. Perbaiki typo HTML kecil (M6)
- `id="#kt_aside_menu"` → `id="kt_aside_menu"` (tanda `#` tidak seharusnya ada di atribut `id`, itu salah satu penyebab umum JS selector gagal cocok)
- `positon-xl-relative` → `position-xl-relative`
- Tambahkan `@yield('breadcrumb')` yang hilang, atau hapus `@section('breadcrumb')` di `home.blade.php` kalau memang tidak terpakai.

### 19. Tambahkan index database (M3)
- `submissions.status`
- Index komposit pada `(item_id, entry_date/exit_date/distribution_date)` tergantung tabel
- `items.department`

---

## TESTING (sebelum merge ke branch utama)

Tambahkan Feature test berikut, di atas 12 test baseline yang sudah ada:

1. **`KajurTenantIsolationTest`** — akun Kajur tidak bisa mengakses data departemen lain; akun Kajur dengan `department = null` mendapat 403, bukan akses penuh.
2. **`WorkflowTransitionTest`** — submission yang sudah di-approve tidak bisa di-approve dua kali; status guard pada tiap transisi alur approval.
3. **`DocumentAuthTest`** — dokumen hanya bisa diunduh oleh user yang berhak sesuai department-nya; percobaan unduh tanpa login harus ditolak.
4. **`DistributionStockTest`** — submit distribusi benar-benar mengurangi `stock` di tabel items; submit dengan stok tidak cukup ditolak; dua request concurrent terhadap item dengan stok terbatas tidak membuat stok negatif.
5. **`UserPrivilegeTest`** — Sarpras tidak bisa membuat akun dengan role `kepala_sekolah`; user biasa tidak bisa menonaktifkan akun kepala_sekolah/sarpras.

---

## Urutan pengerjaan & commit

Kerjakan **Phase 0 → 1 → 2 → 3 → 4 → 5**, berhenti dan jalankan test di akhir tiap fase sebelum lanjut — jangan loncat fase. Aplikasi akan jauh lebih aman begitu Phase 2 selesai, jadi kalau waktu terbatas, prioritaskan sampai situ dulu sebelum lanjut ke Phase 3 dst.

Commit terpisah per nomor task (bukan per fase sekaligus), pesan jelas menyebut nomor isu dari audit ini (misalnya `fix: tutup celah stok di storeDistribution (C-1)`), supaya mudah ditelusuri balik ke temuan audit mana yang diselesaikan.

Setelah semua fase selesai, jalankan test suite penuh (baseline + test baru), pastikan hijau semua, lalu laporkan ringkasan: isu apa saja yang selesai, isu apa yang butuh keputusan tambahan dari saya (misalnya soal efek samping `location_id` di nomor 2, atau soal disk `public` vs `local` di nomor 7), dan isu apa yang sengaja belum disentuh beserta alasannya.
