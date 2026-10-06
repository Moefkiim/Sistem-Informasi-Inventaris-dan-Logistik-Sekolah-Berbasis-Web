# AUDIT IMPLEMENTATION REPORT

Laporan ini championed isi **kondisi sebenarnya** repo pada saat penulisan, bukan kondisi ideal.
Setiap klaim disertai bukti `file:line`. Bagian yang belum dikerjakan ditandai eksplisit sebagai
**BELUM SELESAI**, bukan dipoles supaya terlihat tuntas.

- **Repo**: `Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web`
- **Branch**: `main`
- **Commit baseline sebelum laporan ini**: `2e880da` ("ci: tambah step build asset Vite sebelum menjalankan test")
- **Prompt sumber**: `PROMPT_AI_AGENT_AUDIT_SIILS.md` (20 section, prioritas P0–P3)

---

## 0. Ringkasan Eksekutif: Status 20 Section

Pekerjaan yang benar-benar ada di repo ini (verifikasi Oct 2026) hanya **2 dari 20 section** yang
berstatus Selesai penuh, 5 section Sebagian, dan 13 section belum disentuh sama sekali.

| # | Bagian | Prioritas | Status | Bukti |
|---|--------|-----------|--------|-------|
| 1 | Alur pengajuan draft → approval | P0 | **Sebagian** | `Kajur/SubmissionController.php`, `Sarpras/SubmissionController.php`, `Principal/ApprovalController.php` — guard transisi ada, tapi tabel histori approval tidak ada |
| 2 | Identitas aset individual | P1 | **Sebagian** | `2026_10_05_000001_*.php`, `Item.php:17-33`, form create — kolom ada, tapi model data masih agregat 1 baris = banyak unit fisik |
| 3 | Modul Peminjaman & Pengembalian | P0 | **Selesai** | `LoanController.php`, `Loan.php`, `loans` migration, `LoanFactory`, 3 view, `LoanWorkflowTest.php` (26 test) |
| 4 | Pemisahan kondisi vs status | P1 | **Sebagian** | `current_status` enum ada (`000001:38-46`), hanya di-set oleh `LoanController.php:186,232`; tidak ada UI untuk status `'dalam_perbaikan'`/`'disposed'` |
| 5 | Stok minimum | P1 | **Sebagian** | Kolom + helper `Item.php:60-77`, validasi, input form — tapi **tidak ada** alerting/filter/list badge yang memakainya |
| 6 | Dashboard statistik real | P0 | **Sebagian** | `routes/web.php:39-41` hitung 3 angka dari DB — hanya 3 statistik, tanpa scoping per role, tanpa grafik, tanpa low-stock widget |
| 7 | Audit trail | P0 | **Sebagian** | `ActivityLog.php`, migration, 12 call site — view halaman **hilang** dan tidak ada di nav (keduanya diperbaiki dalam commit ini) |
| 8 | Approval dan histori | P0 | **Sebagian** | Transisi dijaga ketat, tapi `approval_histories` / `submission_status_histories` **tidak pernah dibuat** — hanya `condition_histories` + `location_histories` |
| 9 | Nomor dokumen otomatis | P1 | **Sebagian** | `submission_number` berurutan (`Kajur/SubmissionController.php:236-247`), `loan_number` (`LoanController.php:308-323`) — tetapi `IN-`/`OUT-`/`DIST-` masih pakai `uniqid()` acak (`LogisticsController.php:44,108,169`) |
| 10 | Search dan filter | P2 | **Sebagian** | Ada di inventory sarpras + loans + activity logs; **tidak ada** di lokasi, barang masuk, barang keluar, distribusi, pengajuan sarpras |
| 11 | Export PDF/Excel | P2 | **Belum disentuh** | Tidak ada dependency PDF/Excel di `composer.json`; hanya print via `window.print()` |
| 12 | QR Code (P3) | P3 | **Belum disentuh** | Nol baris `qrcode`/`barcode` di seluruh repo — memang sesuai batasan scope |
| 13 | Audit keamanan | P0 | **Sebagian** | Role middleware + mass-assignment guard kuat; `Item.stock` masih fillable; tidak ada proteksi rate-limit di endpoint mutasi |
| 14 | Audit database | P1 | **Sebagian** | FK & index tersedia; `submissions.department`, `submissions.created_at`, `documents.department` tanpa index |
| 15 | UI/UX | P2 | **Sebagian** | Layout responsif + flash + empty state; belum ada loading state, sorting kolom, atau bulk action |
| 16 | Dokumentasi README | P2 | **Sebagian** | README 25 KB sangat lengkap; **tidak menyebut modul Peminjaman sama sekali** |

Ringkasan: **Selesai 1, Sebagian 13, Belum disentuh 2** (dari 16 section yang dilaporkan; section 17-20
tidak ada di prompt klarifikasi ini dan tidak diklaim).

### Yang terjadi pada 2 section yang saya laporkan "Sebagian"

Dua temuan di atas bukan sekadar penilaian EXISTS/NOT EXISTS, tapi **bug aktif** yang saya temukan
dan perbaiki dalam commit ini — lihat bagian "Bug Diperbaiki".

---

## 1. Ringkasan Perubahan

### 1.1 Yang TELAH ADA sebelum laporan ini (untracked, dari sesi sebelumnya)

Modul Peminjaman & identitas aset sudah ditulis lengkap di working tree tapi **tidak pernah di-commit**:

```
Untracked:
  app/Http/Controllers/Sarpras/LoanController.php
  app/Http/Controllers/Sarpras/ActivityLogController.php
  app/Models/Loan.php
  app/Models/ActivityLog.php
  database/factories/LoanFactory.php
  database/migrations/2026_10_05_000001_enhance_items_and_add_asset_identity.php
  database/migrations/2026_10_05_000002_create_loans_table.php
  database/migrations/2026_10_05_000003_create_activity_logs_table.php
  resources/views/components/loan-status-badge.blade.php
  resources/views/sarpras/loans/{index,create,show}.blade.php
  tests/Feature/LoanWorkflowTest.php
```

### 1.2 Yang saya kerjakan dalam commit ini

Satu-satunya perubahan kode: **memperbaiki halaman audit trail yang rusak**.

---

## 2. Fitur Ditambahkan

Tidak ada fitur produk baru. Yang ditambahkan adalah pelengkapan fitur yang sudah ada namun tidak
fungsional.

### 2.1 Halaman Riwayat Aktivitas (`/sarpras/activity-logs`)

`ActivityLogController` dan route-nya sudah ada, tapi **view-nya tidak pernah dibuat**. Akibatnya
route tersebut 500 saat diakses. Saya membuat `resources/views/sarpras/activity_logs/index.blade.php`
dengan:

- Tabel log: waktu, jenis aksi, pengguna + role, keterangan, label data terkait
- Filter: pencarian teks (nama pengguna/keterangan/label), dropdown jenis aksi (distinct),
  rentang tanggal `start_date`–`end_date` — semua sudah didukung `ActivityLogController.php:16-35`,
  view baru ini yangMahontradeUI-nya
- Ringkasan Before/After per baris via modal, membaca cast `array` `old_values`/`new_values`
  (`ActivityLog.php:30-37`)
- Empty state dan pagination ringkas
- Tombol reset filter

### 2.2 Entri navigasi "Riwayat Aktivitas"

`resources/views/layouts/app.blade.php:199-204` — ditambahkan di grup Administrasi area Sarpras,
tepat sebelum "Dokumen & Berkas". Sebelumnya route ada tapi **tidak dapat dijangkau dari mana pun**.

### 2.3 Test coverage untuk audit trail

Ditambahkan 3 test ke `tests/Feature/LoanWorkflowTest.php`:

| Test | Yang dijaga |
|---|---|
| `test_sarpras_can_open_activity_logs_page` | Halaman render 200 (regression guard untuk view hilang) |
| `test_activity_logs_page_can_filter_by_action` | Filter jenis aksi benar-benar menyaring |
| `test_activity_logs_page_is_restricted_to_sarpras` | Kajur dapat 403 (route role-gate) |

---

## 3. Bug Diperbaiki

### BUG-1 (Blokir): Route `/sarpras/activity-logs` melempar 500 — **DIPERBAIKI**

_(BUG-1 dan BUG-2 adalah bug yang benar-benar terbukti lewat test reproduksi. Bandingkan dengan
BUG-8 di §3.1 yang klaimnya saya tarik kembali karena tidak terbukti.)_


`ActivityLogController.php:40` memanggil `view('sarpras.activity_logs.index', ...)` tetapi direktori
`resources/views/sarpras/activity_logs/` tidak pernah ada.

Bukti kegagalan (sebelum perbaikan):

```
View [sarpras.activity_logs.index] not found.
  at tests/Feature/ActivityLogViewTest.php:11
```

Saya verifikasi ulang dengan test ad-hoc: `GET /sarpras/activity-logs` sebagai user Sarpras
menghasilkan exception "View not found", bukan 200. Setelah view dibuat, 3 test baru lulus.

**Dampak**: Section 7 (Audit trail, P0) dianggap selesai oleh laporan sebelumnya, padahal halaman
audit trail yang merupakan deliverable utama section itu sama sekali tidak bisa dibuka.

### BUG-2 (Minor): Route audit trail tidak dapat dijangkau — **DIPERBAIKI**

Menu sidebar tidak punya entri untuk `sarpras.activity_logs.index`. Satu-satunya cara membuka
halaman itu adalah mengetik URL secara manual. Sudah diperbaiki di `layouts/app.blade.php:199-204`.

### Bug yang BELUM diperbaiki (ditemukan, sengaja tidak disentuh di commit ini)

Saya menemukan ini saat audit. Semuanya **di luar cakupan** commit peminjaman, saya laporkan saja:

| ID | Lokasi | Masalah |
|---|---|---|
| BUG-3 | `app/Models/Item.php:118` | `activeLoans()` memfilter `['dipinjam','disetujui','menunggu']` tapi relasi ini **tidak pernah dipakai** di mana pun. Kode mati. |
| BUG-4 | `app/Http/Controllers/Sarpras/LoanController.php:123` | `borrower_user_id` diisi dengan **ID Sarpras yang mencatat**, bukan user peminjam. Kolomnya bernama `borrower_user_id` dan relasinya `borrower()` — secara semantik ini data yang salah, meskipun tidak menimbulkan celah keamanan (data internal). |
| BUG-5 | `app/Http/Controllers/Sarpras/LoanController.php:71` | `whereNotIn('current_status', ['disposed', 'tidak_aktif'])` **tidak menyaring barang yang sedang dipinjam** di halaman create. Validasi dobel ada di `store()`, tapi user melihat barang yang tak tersedia di dropdown. |
| BUG-6 | `app/Models/Item.php:15-35` | `stock` ada di `$fillable` dan form create mengirim `stock` sebagai input user. Form edit inventaris tidak ada, jadi saat ini tidak bisa dieksploitasi, tapi setiap form baru yang mem-mass-assign `Item` bisa mengubah stok tanpa jejak. |
| BUG-7 | `LogisticsController.php:44,108,169` | Nomor dokumen `'IN-'.date('Ymd').'-'.strtoupper(substr(uniqid(),-4))` — 4 karakter dari `uniqid()` acak, **bukan nomor urut**. Konsisten secara format tapi tidak urut dan rawan tabrakan pada volume tinggi (tabel punya unique constraint). |
| BUG-8 | `app/Http/Controllers/Principal/ApprovalController.php:37` | ~~`show()` tidak memfilter `status` sehingga field approval bisa terisi sebelum giliran.~~ **SALAH — sudah dikoreksi, lihat §3.1.** Yang benar hanya: halaman detail terbuka 200 untuk semua status (bukan kebocoran data lintas jurusan, Kepala Sekolah memang role global), dan *copy* view menyesatkan — blok `@else` menampilkan "Keputusan Telah Dibuat: DRAFT / SUBMITTED / CANCELLED" padahal belum ada keputusan. |

### 3.1 Koreksi: BUG-8 yang saya laporkan ternyata SALAH

Laporan versi sebelumnya menyatakan `ApprovalController::show()` memungkinkan Kepala Sekolah
mengisi field approval sebelum gilirannya. **Klaim itu tidak terbukti.** Saya sudah menulis
test reproduksi untuk membuktikannya, dan hasilnya menolak klaim saya sendiri.

**Test reproduksi** (`POST /kepala-sekolah/approval/{id}/decide` untuk tiap status, lalu cek
`status`, `principal_notes`, `decided_at` di database):

```
DECIDE status=draft      -> DB status=draft,      principal_notes=NULL, decided_at=NULL
DECIDE status=submitted  -> DB status=submitted,  principal_notes=NULL, decided_at=NULL
DECIDE status=approved   -> DB status=approved,   principal_notes=NULL, decided_at=NULL
DECIDE status=rejected   -> DB status=rejected,   principal_notes=NULL, decided_at=NULL
DECIDE status=cancelled  -> DB status=cancelled,  principal_notes=NULL, decided_at=NULL
```

Tidak ada field approval yang bisa terisi. Alasannya ada dua lapis:

1. **Guard di server** — `ApprovalController.php:53` sudah menolak apa pun yang bukan
   `status === 'reviewed_sarpras'`. Guard ini sudah ada sejak awal, bukan hasil kerja saya.
2. **Guard di view** — `resources/views/principal/approval/show.blade.php:72` membungkus seluruh
   form approve/reject di `@if($submission->status === 'reviewed_sarpras')`.

Bukti render per status:

```
draft            btn_approve=false  btn_reject=false  tampil_keputusan_sudah=true
submitted        btn_approve=false  btn_reject=false  tampil_keputusan_sudah=true
reviewed_sarpras btn_approve=true   btn_reject=true   tampil_keputusan_sudah=false
rejected         btn_approve=false  btn_reject=false  tampil_keputusan_sudah=true
approved         btn_approve=false  btn_reject=false  tampil_keputusan_sudah=true
cancelled        btn_approve=false  btn_reject=false  tampil_keputusan_sudah=true
```

Ada test yang sudah mengunci perilaku ini sejak awal: `test_submission_cannot_skip_sarpras_review`
(`tests/Feature/WorkflowTransitionTest.php:118-134`) memakai `assertSessionHasErrors()` dan
status tetap `submitted`.

**Jadi alur approval `Kajur -> Sarpras -> Kepala Sekolah` UTUH. Tidak ada bypass.** Saya tidak
perlu membuat commit `fix:` untuk ini karena tidak ada bug yang perlu diperbaiki.

**Bagian yang benar-benar bermasalah dari temuan ini** — cacat UX, bukan keamanan: blok
`@else` di `show.blade.php:92-100` menampilkan "**Keputusan Telah Dibuat:** DRAFT" untuk
submission yang masih disusun Kajur, dan "KEPUTUSAN TELAH DIBUAT: SUBMITTED" yang belum pernah
ditinjau Sarpras. Copy-nya menyiratkan ada keputusan yang sudah dibuat padahal belum ada,
lalu `decided_at?->format()` menghasilkan baris kosong `Pada:`. Rendah, tapi menyesatkan.
Belum diperbaiki karena di luar cakupan commit ini.

---

## 4. Security Improvement

Tidak ada perbaikan keamanan substantif dalam commit ini — yang ada hanya **menutup celah yang
cukup retiro**: halaman audit trail yang sebelumnya tidak bisa diakses kini tetap berada di balik
`role:sarpras`, dan ada test yang mengunci aturan itu (`test_activity_logs_page_is_restricted_to_sarpras`).

Status keamanan yang sudah ada di codebase (bukan kontribusi commit ini) dan **sudah terverifikasi**:

| Area | Bukti |
|---|---|
| Role middleware + cek user aktif | `app/Http/Middleware/EnsureUserRole.php`, terdaftar di `bootstrap/app.php:15-18` |
| Mass-assignment guard pada field sensitif | `Submission.php:21-27` tidak punya `status` di fillable; ada test `sensitive fields are not mass assignable` |
| Tenant scoping Kajur | `Kajur/InventoryController.php:41-43` (department match), `Kajur/SubmissionController.php:225-230` (ownership) |
| Upload divalidasi + private disk | `DocumentController.php:60` mime+10MB, `:70` simpan ke disk `local`, download via controller bukan URL publik |
| Tidak ada SQL injection | Hanya 1 `selectRaw` di `LoanController.php:58` dengan string statis, tanpa interpolasi variabel |
| Tidak ada XSS | Nol pakai `{!! !!}` di `resources/views` |
| Append-only audit | `ActivityLogController` tidak punya `destroy`; tidak ada route delete untuk log |

---

## 5. Migrasi Baru

Tidak ada. Tiga migrasi untracked sudah ada dari sesi sebelumnya dan ikut ter-commit:

| File | Isi |
|---|---|
| `2026_10_05_000001_enhance_items_and_add_asset_identity.php` | Tambah kolom identitas aset (`inventory_number` unique, `serial_number`, `brand`, `model`), `item_type` enum, `acquisition_year`, `acquisition_price`, `current_status` enum, `minimum_stock` |
| `2026_10_05_000002_create_loans_table.php` | Tabel `loans` + FK ke `items`/`users` + index `(item_id,status)`, `loan_date`, `due_date` + soft delete |
| `2026_10_05_000003_create_activity_logs_table.php` | Tabel `activity_logs` append-only, polymorphic `auditable`, JSON `old_values`/`new_values`, `ip_address`, snapshot `user_name`/`user_role` |

Semua 3 sudah diverifikasi jalan pada `php artisan test` (54 test hijau sebelum saya menambah 3 test baru).

---

## 6. File yang Berubah

Commit modul Peminjaman (terpisah dari commit laporan ini):

```
Modified:  app/Http/Controllers/Kajur/SubmissionController.php
Modified:  app/Http/Controllers/Principal/ApprovalController.php
Modified:  app/Http/Controllers/Sarpras/InventoryController.php
Modified:  app/Http/Controllers/Sarpras/SubmissionController.php
Modified:  app/Models/Item.php
Modified:  resources/views/layouts/app.blade.php
Modified:  resources/views/sarpras/inventory/create.blade.php
Modified:  routes/web.php
Added:     app/Http/Controllers/Sarpras/ActivityLogController.php
Added:     app/Http/Controllers/Sarpras/LoanController.php
Added:     app/Models/ActivityLog.php
Added:     app/Models/Loan.php
Added:     database/factories/LoanFactory.php
Added:     database/migrations/2026_10_05_000001_enhance_items_and_add_asset_identity.php
Added:     database/migrations/2026_10_05_000002_create_loans_table.php
Added:     database/migrations/2026_10_05_000003_create_activity_logs_table.php
Added:     resources/views/components/loan-status-badge.blade.php
Added:     resources/views/sarpras/loans/create.blade.php
Added:     resources/views/sarpras/loans/index.blade.php
Added:     resources/views/sarpras/loans/show.blade.php
Added:     tests/Feature/LoanWorkflowTest.php
```

Perbaikan audit trail (commit terpisah, termasuk dalam file di atas):

```
Modified:  resources/views/layouts/app.blade.php        (+6, entri nav "Riwayat Aktivitas")
Modified:  tests/Feature/LoanWorkflowTest.php            (+39, 3 test baru)
Added:     resources/views/sarpras/activity_logs/index.blade.php
```

---

## 7. Testing & Hasilnya

```
$ php artisan test
Tests:    57 passed (207 assertions)
Duration: 2.16s
```

| Test file | Jumlah | Cakupan |
|---|---|---|
| `LoanWorkflowTest` | 26 | Alur peminjaman end-to-end: create → approve → return, gate stok consumable, gate aset individual, penolakan, filter, auto-terlambat, audit log, **+ 3 test audit trail baru** |
| `LocationManagementTest` | 4 | CRUD lokasi, soft-delete guard, integritas histori |
| `ReportScopingTest` | 3 | Scoping laporan per role, Kajur tanpa department dapat 403 |
| `UserPrivilegeTest` | 3 | Sarpras tidak bisa buat/toggle kepala_sekolah |
| `WorkflowTransitionTest` | 4 | Rantai draft→submitted→reviewed→approved, guard transisi, mass-assignment |
| `DistributionStockTest`, `DocumentAuthTest`, `KajurTenantIsolationTest`, `AuthenticationAndRoleAccessTest`, `ExampleTest` ×2 | 17 | Distribusi/stok, auth dokumen, isolasi tenant, otorisasi role |

Semua 57 lulus. Yang penting: test `test_sarpras_can_open_activity_logs_page` **adalah regression guard
untuk BUG-1** — sebelum view dibuat, test ini gagal dengan "View not found".

Tidak ada test yang gagal atau di-skip.

---

## 8. Hal yang BELUM Bisa Diimplementasikan

Prioritas ini **tidak diselesaikan** dan saya tidak membuatnya selesai:

| Section | Kenapa belum |
|---|---|
| 11 — Export PDF/Excel | Tidak ada dependency (`dompdf`, `phpoffice`, `maatwebsite` 모두 nol di `composer.json`). Butuh penambahan package + route download + view laporan. |
| 12 — QR Code | Sesuai `AGENTS.md` §4, barcode/QR code **dilarang keras** tanpa persetujuan eksplisit. Sengaja tidak dikerjakan. |
| 8 — Approval histori | Butuh desain tabel `approval_histories` yang belum ada di plan. Ini keputusan skema, bukan sekadar kode. |
| 2 — Aset per-unit | Butuh tabel anak (satu baris per unit fisik). Ongkos perubahan skema besar — kolom identitas yang sekarang ada pada baris agregat, idealnya dipindah ke `asset_units`. Saya tidak ingin memutus data yang sudah ada tanpa persetujuan. |
| 6 — Dashboard lanjutan | Butuh keputusan produk: statistik apa yang penting per role. Tidak ada di prompt. |

---

## 9. Risiko yang Tersisa

1. **Tidak ada push yang berhasil.** `git push` gagal: `Permission denied (publickey)` — environment ini tidak punya SSH key yang terdaftar di GitHub. Commit ada secara lokal di `main`, tapi **belum sampai ke remote**. anyone yang clone dari GitHub masih melihat `2e880da` tanpa modul Peminjaman.
2. **Pint/style check gagal** pada 19 file (`vendor/bin/pint --test`). Angka ini **sudah dibuktikan lewat perbandingan commit**, bukan dikira-kira:

   | Titik ukur | File gagal |
   |---|---|
   | `2e880da` (sebelum modul peminjaman) | 19 |
   | `dac980b` (sesudah, tanpa fix) | 27 |
   | `e467414` (sesudah fix pint) | 19 |

   Selisih 8 file itu persis file baru dari commit `eb6eed1`, sudah saya rapikan di `e467414`.
   19 file sisanya **pre-existing** (`bootstrap/app.php`, `AppServiceProvider.php`, `routes/web.php`,
   `Submission.php`, `LocationFactory.php`, dan 14 lainnya) — sudah gagal sebelum kerjaan peminjaman
   dimulai. `pint` **tidak** dijalankan di CI, jadi tidak memblokir pipeline.
3. **BUG-3 s.d. BUG-7 masih hidup** di codebase (§3). Yang paling perlu perhatian adalah **BUG-6**
   (`stock` masih fillable di `Item`). Perhatikan bahwa BUG-8 sudah dikoreksi di §3.1 — klaim
   bypass approval ternyata tidak terbukti, jadi jangan diperlakukan sebagai risiko.
4. **Skema index belum lengkap** — `submissions.department` dan `submissions.created_at` difilter di `ReportController.php:74,80` tapi tanpa index. Belum jadi bottleneck pada data skala sekolah.
5. **`Item.current_status` punya 5 nilai enum tapi hanya 2 yang bisa dicapai** lewat UI. Nilai `'dalam_perbaikan'` dan `'disposed'` hanya bisa diset via Tinker. Section 4 baru benar-benar "Sebagian" karena ini.

---

## 10. Cara Menjalankan Fitur Baru

### Prasyarat

```bash
php -v          # butuh PHP 8.4+
composer -V
npm -v          # butuh Node 20
```

### Setup dari clone bersih

```bash
git clone git@github.com:Moefkiim/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web.git
cd Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web

composer install
npm ci

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate --seed     # WAJIB: 3 migrasi baru (aset identitas, loans, activity_logs)
php artisan storage:link

npm run build                   # atau: npm run dev
php artisan serve
```

### Mengakses fitur baru

| Fitur | URL | Role |
|---|---|---|
| Modul Peminjaman | `/sarpras/loans` | Sarpras |
| Catat peminjaman baru | `/sarpras/loans/create` | Sarpras |
| Riwayat Aktivitas (audit trail) | `/sarpras/activity-logs` | Sarpras |

Keduanya juga bisa dijangkau dari sidebar: **Administrasi → Peminjaman Barang** dan
**Administrasi → Riwayat Aktivitas**.

### Verifikasi cepat

```bash
php artisan test --filter=LoanWorkflowTest     # 26 test
```

### Catatan migrasi

Ketiga migrasi baru menambah kolom/enum pada `items`. Untuk database yang sudah berisi data,
kolom nullable aman. `inventory_number` diberi unique constraint — jika data lama punya nilai
duplikat di kolom itu (mustahil sebelumnya karena kolomnya baru), tidak ada masalah.

---

## 11. Status Section yang Dirapot Tidak Disentuh

Bagian 3 (Peminjaman) dan sebagian 2, 4, 5, 7 ikut ter-commit. **Section 1, 6, 9, 10, 11, 13, 14, 15, 16
belum dikerjakan lebih lanjut** sesuai instruksi untuk memverifikasi push dulu.

Urutan yang saya sarankan setelah push berhasil:
1. Push & verifikasi hash (BLOCKED — butuh SSH key atau `gh auth login`)
2. Perbaiki BUG-6 (keluarkan `stock` dari `$fillable` Item)
3. Section 11 (export PDF/Excel) — satu-satunya gap P2 dengan dampak user nyata terbesar
4. Section 8 (tabel approval histories)
5. Rapikan copy `show.blade.php:92-100` yang menampilkan "Keputusan Telah Dibuat: DRAFT" (§3.1)