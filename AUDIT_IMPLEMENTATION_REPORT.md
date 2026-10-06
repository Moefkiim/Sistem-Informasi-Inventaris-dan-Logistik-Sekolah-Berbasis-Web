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

Pekerjaan yang benar-benar ada di repo ini (verifikasi Oct 2026) — setelah sesi verifikasi lanjutan
6 Okt — berstatus **6 dari 16 section Selesai penuh, 9 Sebagian, dan 1 belum disentuh** (Section 12
QR dilarang oleh `AGENTS.md` §4). Pekerjaan terbuka yang tersisa: aset per-unit (§2) dan dashboard
lanjutan (§6).

| # | Bagian | Prioritas | Status | Bukti |
|---|--------|-----------|--------|-------|
| 1 | Alur pengajuan draft → approval | P0 | **Selesai** | `Kajur/SubmissionController.php`, `Sarpras/SubmissionController.php`, `Principal/ApprovalController.php` — guard transisi + tabel `submission_histories` (Section 8) + blok "Riwayat Proses" di 3 halaman detail |
| 2 | Identitas aset individual | P1 | **Sebagian** | `2026_10_05_000001_*.php`, `Item.php:17-33`, form create — kolom ada, tapi model data masih agregat 1 baris = banyak unit fisik |
| 3 | Modul Peminjaman & Pengembalian | P0 | **Selesai** | `LoanController.php`, `Loan.php`, `loans` migration, `LoanFactory`, 3 view, `LoanWorkflowTest.php` (29 test) + `recorded_by` (BUG-4) |
| 4 | Pemisahan kondisi vs status | P1 | **Sebagian** | `current_status` enum ada (`000001:38-46`), hanya di-set oleh `LoanController.php:186,232`; tidak ada UI untuk status `'dalam_perbaikan'`/`'disposed'` |
| 5 | Stok minimum | P1 | **Sebagian** | Kolom + helper `Item.php:60-77`, validasi, input form — tapi **tidak ada** alerting/filter/list badge yang memakainya |
| 6 | Dashboard statistik real | P0 | **Sebagian** | `routes/web.php:39-41` hitung 3 angka dari DB — hanya 3 statistik, tanpa scoping per role, tanpa grafik, tanpa low-stock widget |
| 7 | Audit trail | P0 | **Selesai** | `ActivityLog.php`, migration, 12 call site, view `sarpras/activity_logs`, entri nav, test akses role |
| 8 | Approval dan histori | P0 | **Selesai** | `submission_histories` dibuat (commit `4ca0511`): from/to status, aktor, catatan, recorded_at — pencatatan di 6 titik transisi + 10 test |
| 9 | Nomor dokumen otomatis | P1 | **Selesai** | `submission_number` & `loan_number` berurutan; `IN-`/`OUT-`/`DIST-` kini memakai `generateDocumentNumber()` urut race-safe (commit `db48328`) + 5 test |
| 10 | Search dan filter | P2 | **Sebagian** | Ada di inventory sarpras + loans + activity logs; **tidak ada** di lokasi, barang masuk, barang keluar, distribusi, pengajuan sarpras |
| 11 | Export PDF/Excel | P2 | **Selesai** | `barryvdh/laravel-dompdf` ^3.1 + `phpoffice/phpspreadsheet` ^5.10; route `reports.pdf` / `reports.excel`; view `reports/pdf.blade.php`; 6 test |
| 12 | QR Code (P3) | P3 | **Belum disentuh** | Nol baris `qrcode`/`barcode` di seluruh repo — memang sesuai batasan scope |
| 13 | Audit keamanan | P0 | **Sebagian** | Role middleware + mass-assignment guard kuat; `Item.stock` sudah dikeluarkan dari `$fillable` (BUG-6, commit `bc9a3ed`); tidak ada proteksi rate-limit di endpoint mutasi |
| 14 | Audit database | P1 | **Sebagian** | FK & index tersedia; `submissions.department`, `submissions.created_at`, `documents.department` tanpa index |
| 15 | UI/UX | P2 | **Sebagian** | Layout responsif + flash + empty state; belum ada loading state, sorting kolom, atau bulk action |
| 16 | Dokumentasi README | P2 | **Sebagian** | README 25 KB sangat lengkap; **tidak menyebut modul Peminjaman sama sekali** |

Ringkasan: **Selesai 6, Sebagian 9, Belum disentuh 1** (dari 16 section yang dilaporkan; section 17-20
tidak ada di prompt klarifikasi ini dan tidak diklaim).

### Yang terjadi pada 2 section yang saya laporkan "Sebagian"

Dua temuan di atas bukan sekadar penilaian EXISTS/NOT EXISTS, tapi **bug aktif** yang saya temukan
dan perbaiki dalam commit ini — lihat bagian "Bug Diperbaiki".

---

## 1. Ringkasan Perubahan

Laporan ini lahir dari kondisi **untracked** di sesi sebelumnya: modul Peminjaman & identitas aset
sudah ditulis lengkap di working tree tapi tidak pernah di-commit. Kondisi itu sudah beres — seluruhnya
sudah masuk `main` (lokal). Riwayat commit terkait pekerjaan audit:

| Commit | Isi |
|---|---|
| `eb6eed1` | feat: modul peminjaman, identitas aset, audit trail (21 file — file yang sebelumnya untracked) |
| `dac980b` | docs: laporan audit ini (20 section) |
| `d2e1548` | style: pint pada 8 file baru (perubahan whitespace saja) |
| `eebb67e` | docs: koreksi BUG-8 (klaim salah) + bukti perbandingan angka pint |
| `bc9a3ed` | fix: BUG-6 — `stock` keluar dari `Item::$fillable` |
| `aa5d05a` | fix: BUG-8 bagian copy — hapus "Keputusan Telah Dibuat" untuk pengajuan belum diputuskan |
| `002449d` | docs: catat BUG-3/4/5/7 sebagai technical debt, perbarui status BUG-6 dan BUG-8 |
| `a6ff9e0` | docs: perbarui hash pasca-rebase dan status konflik yang sudah selesai |
| (commit ini) | docs: tandai push selesai, rapikan §1 dan §11 |

Dua catatan riwayat yang penting untuk pembaca:

1. **`origin/main` bergerak sendiri.** Sementara pekerjaan berjalan, `origin/main` di-commit
   menjadi `2688930` ("Delete AUDIT_IMPLEMENTATION_REPORT.md", lewat GitHub web, 5 Okt 18:18)
   yang menghapus laporan ini, sementara 5 commit lokal belum sampai ke remote. Karena itu
   seluruh riwayat di atas **di-rebase di atas `2688930`**, sehingga hash 4 commit terakhir
   berbeda dari versi sebelum rebase. Isinya identik, hanya basisnya yang berubah.
2. **Konflik modify/delete sudah diselesaikan dengan versi lokal menang** — file laporan ini
   dipertahankan, penghapusan di remote dibatalkan, dan seluruh commit sudah di-push.
   Detail di §9 butir 1 dan §11.

Sesi verifikasi & lanjutan (6 Okt 2026):

| Commit | Isi |
|---|---|
| `b6099b3` | fix: BUG-4 — `borrower_user_id` = peminjam asli, kolom baru `recorded_by` untuk pencatat (+ migration, model, view, 3 test) |
| `db48328` | fix: BUG-7 — `generateDocumentNumber()` urut & race-safe untuk IN/OUT/DIST (+ 5 test) |
| `5d67ea0` | fix: BUG-3 + BUG-5 — barang dipinjam tidak muncul di dropdown loan; `activeLoans()` dipakai di detail aset Kajur/Sarpras (+ 6 test) |
| `4ca0511` | feat: Section 8 — tabel `submission_histories` + pencatatan di 6 titik transisi + blok riwayat di 3 halaman detail (+ 10 test) |
| `83dd94c` | feat: Section 11 — export laporan PDF (dompdf) & Excel (.xlsx) server-side, route `reports.pdf`/`reports.excel` (+ 6 test) |

Sesi verifikasi CI & akun demo (7 Okt 2026):

| Commit | Isi |
|---|---|
| `8236b6b` | ci: tambahkan ekstensi `gd, zip, iconv, simplexml, xmlreader, xmlwriter, ctype, filter` ke setup-php agar `composer install` di workflow lolos requirement phpspreadsheet (CI run #20 **hijau**) |
| `f8f6aaa` | test: `DemoReadinessTest` (5 test) — bukti akun demo Kajur/Sarpras/Kepsek bisa login & akses halaman setelah `migrate:fresh`, akun nonaktif ditolak (CI run #21 **hijau**) |

Perubahan kode di luar commit feat sesi pertama: perbaikan bug (BUG-6, BUG-8 bagian copy). Pada sesi
lanjutan: perbaikan BUG-4, BUG-5, BUG-7, BUG-3 + dua fitur produk (histori approval & export
PDF/Excel), semuanya ber-commit terpisah dan ter-push.

---

## 2. Fitur Ditambahkan

Sesi pertama hanya melengkapi fitur yang sudah ada namun tidak fungsional. Sesi verifikasi
lanjutan menambahkan **dua fitur produk baru** — histori approval (Section 8) dan export
PDF/Excel (Section 11) — selain perbaikan BUG-3/4/5/7.

### 2.4 Riwayat Proses Pengajuan (Section 8, commit `4ca0511`)

Tabel baru `submission_histories` (append-only) mencatat setiap transisi status pengajuan:
status sebelum → sesudah, aktor (nama + peran via relasi `actor`), catatan Sarpras/Kepala
Sekolah pada saat transisi, dan waktu. Dicatat otomatis di 6 titik transisi (store,
update draft, cancel, submitDraft, Sarpras process, Kepsek decide) dan ditampilkan sebagai
blok "Riwayat Proses Pengajuan (Audit Trail)" di halaman detail Kajur, Sarpras, dan Kepala
Sekolah.

### 2.5 Export Laporan PDF/Excel (Section 11, commit `83dd94c`)

Tombol "PDF" dan "Excel" di halaman Laporan & Rekap; keduanya mewarisi semua filter aktif
(jenis laporan, date range, jurusan). PDF dirender server-side via dompdf (A4 landscape,
kop laporan, meta, tabel, kolom tanda tangan); Excel via PhpSpreadsheet (header tebal +
autosize kolom). Data list dari query yang sama dengan tampilan layar, sehingga selalu
terisi otomatis dari database.

### 2.1 Halaman Riwayat Aktivitas (`/sarpras/activity-logs`)

`ActivityLogController` dan route-nya sudah ada, tapi **view-nya tidak pernah dibuat**. Akibatnya
route tersebut 500 saat diakses. Saya membuat `resources/views/sarpras/activity_logs/index.blade.php`
dengan:

- Tabel log: waktu, jenis aksi, pengguna + role, keterangan, label data terkait
- Filter: pencarian teks (nama pengguna/keterangan/label), dropdown jenis aksi (distinct),
  rentang tanggal `start_date`–`end_date` — semua sudah didukung `ActivityLogController.php:16-35`,
  view baru inilah yang menampilkan UI-nya
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
BUG-8 di §3.3 yang klaimnya saya tarik kembali karena tidak terbukti.)_


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

### Bug yang sudah diperbaiki dan belum

| ID | Lokasi | Masalah | Severity | Status |
|---|---|---|---|---|
| BUG-1 | `ActivityLogController.php:40` | Route `/sarpras/activity-logs` memanggil view yang tidak pernah dibuat → 500 | **Blokir** | **Selesai** — view dibuat |
| BUG-2 | `layouts/app.blade.php` | Tidak ada entri nav ke audit trail | Minor | **Selesai** — nav ditambahkan |
| BUG-6 | `Item.php:15-35` | `stock` ada di `$fillable`, kelas invariant yang sama dengan `Submission.status`/`User.role` | Sedang | **Selesai** — commit `bc9a3ed` |
| BUG-8 | `ApprovalController.php:37` | Klaim bypass approval **SALAH**; bagian UX copy saja (§3.3) | Ringan | **Selesai** — commit `aa5d05a` |
| BUG-3 | `app/Models/Item.php` | Relasi `activeLoans()` tidak pernah dipakai (kode mati) | Ringan | **Selesai** — commit `5d67ea0` |
| BUG-4 | `LoanController.php` | `borrower_user_id` terisi ID Sarpras pencatat, bukan peminjam asli | Sedang | **Selesai** — commit `b6099b3` |
| BUG-5 | `LoanController.php` | Dropdown create tidak menyaring barang yang sedang dipinjam | Ringan | **Selesai** — commit `5d67ea0` |
| BUG-7 | `LogisticsController.php` | Nomor dokumen pakai `uniqid()` — tidak urut, berisiko tabrakan | Sedang | **Selesai** — commit `db48328` |

### 3.2 Technical debt yang dicatat, sengaja TIDAK diperbaiki

> **Pembaruan 6 Okt 2026:** keempat item di bawah **sudah diperbaiki** lewat prompt verifikasi
> lanjutan. Rincian implementasi ada di commit `b6099b3` (BUG-4), `db48328` (BUG-7),
> `5d67ea0` (BUG-3 + BUG-5). Teks di bawah dipertahankan sebagai arsip keputusan desain
> (khususnya alasan pemilihan kolom `recorded_by` untuk BUG-4).

Empat item di bawah **tidak mengeksploitasi apa pun saat ini**. Tidak ada benturan keamanan
atau kehilangan data yang terjadi sekarang. Tetapi semuanya adalah bahan bakar yang bisa
menjadi masalah nyata begitu ada penambahan fitur, jadi dicatat di sini beserta lokasi
dan skenario agar tidak terlupa.

| ID | Lokasi | Skenario | Severity | Kenapa belum diperbaiki |
|---|---|---|---|---|
| **BUG-3** | `app/Models/Item.php` — relasi `activeLoans()` | Relasi ini memfilter `whereIn('status', ['dipinjam','disetujui','menunggu'])`, tapi **tidak pernah dipanggil di mana pun** di seluruh `app/` dan `resources/`. Grep seluruh basis kode hanya menemukan definisinya. Kode mati: biaya baca, dan nilai `'disetujui'` yang sudah tidak dipakai `LoanController` lagi bisa membuat siapa pun yang nanti memakainya salah anggapan. | Ringan | Tidak ada fungsi yang terganggu. Perbaikannya mendesak hanya kalau relasi itu mulai dipakai. |
| **BUG-4** | `app/Http/Controllers/Sarpras/LoanController.php` — `store()` | `borrower_user_id` diisi `$request->user()->id`, yaitu **Sarpras yang mencatat**, bukan peminjam sesungguhnya. Kolomnya bernama `borrower_user_id` dan relasinya `borrower()` — secara semantik berbeda. Kalau nanti ada pertanyaan "siapa yang meminjam?" di laporan, datanya akan menjawab "Sarpras". Tidak ada celah keamanan karena field itu internal, bukan input user. | Sedang | Perbaikan butuh keputusan desain: apakah peminjam harus akun user, atau `borrower_user_id` cukup diganti namanya jadi `created_by` agar semantiknya jujur. Keputusan ini butuh persetujuan Anda, bukan sekadar patch. |
| **BUG-5** | `app/Http/Controllers/Sarpras/LoanController.php` — `create()` | Query mengambil barang hanya dengan `whereNotIn('current_status', ['disposed','tidak_aktif'])`, **tidak menyaring barang yang sedang dipinjam**. Akibatnya dropdown menampilkan aset yang sudah habis dipinjam. Tidak merusak data: `store()` punya guard sendiri yang menolak dengan pesan "Aset sedang dipinjam atau tidak tersedia". Jadi ini murni kebingungan UX, bukan celah. | Ringan | Data aman. Perbaikannya satu baris (`whereNotIn('current_status', ['dipinjam','disposed','tidak_aktif'])`) tapi butuh test agar tidak menutup barang consumable yang valid. |
| **BUG-7** | `app/Http/Controllers/Sarpras/LogisticsController.php` — `store` barang masuk, barang keluar, distribusi | Format nomor `'IN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4))` — 4 karakter terakhir `uniqid()` berasal dari `microtime`, jadi **acak, bukan nomor urut**. Konsekuensinya: nomor dokumen tidak bisa dibaca urut, dan pada volume tinggi `substr(uniqid(), -4)` memang mungkin tabrakan. Kolom punya unique constraint, jadi tabrakan akan gagal insert (data tidak corrupt, tapi user kena error). | Sedang | Butuh pendekatan yang sama seperti `submission_number` dan `loan_number` (baca terakhir → urut → `str_pad`), plus perhatian pada race condition. Itu perubahan melintasi tiga method dan menyangkut format nomor dokumen yang sudah dipakai data lama — tidak akan saya kerjakan tanpa instruksi eksplisit. |

Urutan saran penanganan: BUG-4 (butuh keputusan desain Anda) → BUG-7 (butuh perubahan format,
berdampak pada data lama) → BUG-5 (satu baris) → BUG-3 (bersihkan kode mati).

### 3.3 Koreksi: BUG-8 yang saya laporkan ternyata SALAH

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

Satu set dari sesi sebelum ini ikut ter-commit, plus dua migrasi baru dari sesi verifikasi lanjutan:

| File | Isi |
|---|---|
| `2026_10_05_000001_enhance_items_and_add_asset_identity.php` | Tambah kolom identitas aset (`inventory_number` unique, `serial_number`, `brand`, `model`), `item_type` enum, `acquisition_year`, `acquisition_price`, `current_status` enum, `minimum_stock` |
| `2026_10_05_000002_create_loans_table.php` | Tabel `loans` + FK ke `items`/`users` + index `(item_id,status)`, `loan_date`, `due_date` + soft delete |
| `2026_10_05_000003_create_activity_logs_table.php` | Tabel `activity_logs` append-only, polymorphic `auditable`, JSON `old_values`/`new_values`, `ip_address`, snapshot `user_name`/`user_role` |
| `2026_10_06_000001_add_recorded_by_to_loans_table.php` | **BUG-4**: kolom `recorded_by` (pencatat transaksi), backfill `recorded_by = borrower_user_id`, lalu `borrower_user_id = NULL` agar diisi peminjam asli |
| `2026_10_06_000002_create_submission_histories_table.php` | **Section 8**: tabel `submission_histories` append-only (from_status, to_status, actor_user_id, notes, recorded_at; index `(submission_id, recorded_at)`) |

Semua sudah diverifikasi jalan pada `php artisan test` (93 test hijau saat ini).

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

Rapian pint (commit terpisah, 8 file):

```
Modified:  (hanya whitespace/indent, tanpa perubahan logika) — lihat commit d2e1548
```

Perbaikan BUG-6 (commit `bc9a3ed`):

```
Modified:  app/Models/Item.php                           (-1, 'stock' keluar dari $fillable)
Modified:  app/Http/Controllers/Sarpras/InventoryController.php
             (+7/-1, assignment eksplisit $item->stock setelah new Item($validated))
Added:     tests/Feature/ItemStockMassAssignmentTest.php (4 test regresi)
```

Perbaikan copy halaman approval, BUG-8 bagian view (commit `aa5d05a`):

```
Modified:  resources/views/principal/approval/show.blade.php
             blok @else dibagi: reviewed_sarpras / approved+rejected / lainnya
Modified:  tests/Feature/WorkflowTransitionTest.php      (+2 test copy)
```

Sesi verifikasi & lanjutan, 6 Okt 2026 (semua ter-push, lihat §9/§11):

```
b6099b3  fix: BUG-4 — borrower_user_id kini peminjam asli, kolom recorded_by untuk pencatat
           (+ migration, Loan model/controller/view, LoanWorkflowTest +3)
db48328  fix: BUG-7 — helper generateDocumentNumber() urut&race-safe di LogisticsController
           (+ tests/Feature/DocumentNumberSequentialTest.php, 5 test)
5d67ea0  fix: BUG-3+BUG-5 — filter barang dipinjam di dropdown loan, activeLoans() dipakai
           di detail aset Kajur/Sarpras (6 test baru)
4ca0511  feat: Section 8 — tabel submission_histories + pencatatan di 6 titik transisi,
           blok "Riwayat Proses Pengajuan" di 3 halaman detail (10 test baru)
83dd94c  feat: Section 11 — export laporan PDF (dompdf) & Excel (.xlsx) server-side
           (composer: barryvdh/laravel-dompdf ^3.1, phpoffice/phpspreadsheet ^5.10;
            Route reports.pdf/reports.excel; 6 test baru)
```

---

## 7. Testing & Hasilnya

```
$ php artisan test
Tests:    98 passed (379 assertions)
```

| Test file | Jumlah | Cakupan |
|---|---|---|
| `DemoReadinessTest` | 5 | **TASK 2 (CI & demo)**: akun demo `kajur_rpl`/`sarpras`/`kepsek` terprovisi (`role` + `is_active` benar) dari `DatabaseSeeder`, ketiganya login via HTTP dan membuka dashboard + halaman sesi ini (export PDF/Excel, blok "Riwayat Proses", blok "Peminjaman Aktif", `recorded_by` pada detail peminjaman); `user_nonaktif` ditolak login |
| `LoanWorkflowTest` | 29 | Alur peminjaman end-to-end: create → approve → return, gate stok consumable, gate aset individual, penolakan, filter, auto-terlambat, audit log, **+ 3 test audit trail, + 3 test BUG-4 (`recorded_by`)** |
| `DocumentNumberSequentialTest` | 5 | **BUG-7**: nomor IN/OUT/DIST urut 0001…, lanjut setelah nomor uniqid lama tanpa duplikat, bulk 50 transaksi |
| `LoanDropdownAndActiveLoanDisplayTest` | 6 | **BUG-5 + BUG-3**: barang dipinjam/stok 0 tak muncul di dropdown, blok "Peminjaman Aktif" di detail aset Sarpras & Kajur |
| `SubmissionHistoryTest` | 10 | **Section 8**: pencatatan histori di semua transisi (store/submit/cancel/Sarpras review-reject/Kepsek approve-reject), urutan alur penuh, tampilan di detail |
| `ReportExportTest` | 6 | **Section 11**: tombol export, unduhan PDF `.pdf` & `.xlsx` (content-type + nama file), filter date range, scoping Kajur, tipe invalid ditolak |
| `LocationManagementTest` | 4 | CRUD lokasi, soft-delete guard, integritas histori |
| `ReportScopingTest` | 3 | Scoping laporan per role, Kajur tanpa department dapat 403 |
| `UserPrivilegeTest` | 3 | Sarpras tidak bisa buat/toggle kepala_sekolah |
| `WorkflowTransitionTest` | 6 | Rantai draft→submitted→reviewed→approved, guard transisi, mass-assignment, **+ 2 test copy halaman approval** |
| `ItemStockMassAssignmentTest` | 4 | **Regression guard BUG-6**: `stock` tidak bisa diset/diubah lewat mass-assignment, assignment eksplisit tetap jalan |
| `DistributionStockTest`, `DocumentAuthTest`, `KajurTenantIsolationTest`, `AuthenticationAndRoleAccessTest`, `ExampleTest` ×2 | 17 | Distribusi/stok, auth dokumen, isolasi tenant, otorisasi role |

Semua 98 lulus. Test kunci sebagai regression guard:

- `test_sarpras_can_open_activity_logs_page` — **BUG-1**. Sebelum view dibuat, gagal "View not found".
- `ItemStockMassAssignmentTest::stock cannot be set via mass assignment on create` — **BUG-6**.
  Payload `stock=999` ditolak, nilai tetap default DB. Tanpa perbaikan, test ini gagal.
- `WorkflowTransitionTest::approval page shows correct copy per status` — **BUG-8 (bagian copy)**.
  Memastikan halaman tidak lagi menampilkan "Keputusan Telah Dibuat" untuk `draft`/`submitted`.

Tidak ada test yang gagal atau di-skip. `vendor/bin/pint --test` masih gagal di **18 file**,
semuanya pre-existing sejak sebelum modul Peminjaman (daftar file identik dengan baseline
`2e880da`); file yang disentuh pada sesi verifikasi lanjutan selalu dirapikan pint per-file
sehingga tidak ada file baru yang ikut masuk daftar gagal.

**Status CI (TASK 1 prompt CI & demo).** Sebelum perbaikan, `composer install` di workflow akan
gagal karena `phpoffice/phpspreadsheet 5.10.0` mewajibkan `ext-gd` (juga zip/iconv/simplexml/
xmlreader/xmlwriter), sedangkan `shivammathur/setup-php` hanya memasang mbstring/intl/sqlite3/dom/
fileinfo/curl. Setelah `extensions` diperluas (commit `8236b6b`), CI run #20 **hijau penuh**;
`composer install` diverifikasi lolos tanpa `--ignore-platform-req` (lokal: hanya `ext-gd` yang
kurang, dan kini tersedia di CI). CI run #21 (commit `f8f6aaa`, seluruh 98 test di php 8.4) juga
**hijau**. Kode export Excel (`ReportController::exportExcel`) memang tidak memakai GD saat runtime —
kehadirannya hanya untuk memenuhi requirement composer.

---

## 8. Hal yang BELUM Bisa Diimplementasikan

Prioritas ini **tidak diselesaikan** dan saya tidak membuatnya selesai:

| Section | Kenapa belum |
|---|---|
| 12 — QR Code | Sesuai `AGENTS.md` §4, barcode/QR code **dilarang keras** tanpa persetujuan eksplisit. Sengaja tidak dikerjakan. |
| 2 — Aset per-unit | Butuh tabel anak (satu baris per unit fisik). Ongkos perubahan skema besar — kolom identitas yang sekarang ada pada baris agregat, idealnya dipindah ke `asset_units`. Saya tidak ingin memutus data yang sudah ada tanpa persetujuan. |
| 6 — Dashboard lanjutan | Butuh keputusan produk: statistik apa yang penting per role. Tidak ada di prompt. |

---

## 9. Risiko yang Tersisa

1. **Push sudah selesai.** Tidak ada risiko tertinggal: `git ls-remote origin main` dan
   `git rev-parse HEAD` identik, `git status -sb` menunjukkan `main...origin/main` tanpa selisih.
   Catatan prosesnya: push dari WSL gagal `Permission denied (publickey)` (tidak ada SSH key,
   `gh` tidak terpasang) dan harus dijalankan dari PowerShell Windows. Saat mencoba push,
   ditemukan masalah kedua yang sudah diselesaikan: remote sempat bergerak ke `2688930`
   ("Delete AUDIT_IMPLEMENTATION_REPORT.md", via GitHub web, 5 Okt) sehingga memicu konflik
   **modify/delete** pada file ini. Konflik diselesaikan dengan versi lokal menang (keputusan
   pengguna), seluruh commit di-rebase di atas `2688930`, dan file ini tetap ada di remote.
2. **Pint/style check gagal** pada 18 file (`vendor/bin/pint --test`). Angka ini **sudah dibuktikan lewat perbandingan commit**, bukan dikira-kira:

   | Titik ukur | File gagal |
   |---|---|
   | `2e880da` (sebelum modul peminjaman) | 19 |
   | `dac980b` (sesudah, tanpa fix) | 27 |
   | `d2e1548` (sesudah fix pint) | 19 |
   | sesudah `bc9a3ed` (sekarang) | 18 |

   Selisih 8 file pada `dac980b` itu persis file baru dari commit `eb6eed1`, sudah saya rapikan di `d2e1548`.
   `InventoryController.php` ikut turun ke 18 karena formatnya ikut terpangkas saat perbaikan BUG-6.
   Sisanya **pre-existing** (`bootstrap/app.php`, `AppServiceProvider.php`, `routes/web.php`,
   `Submission.php`, `LocationFactory.php`, dan 13 lainnya) — sudah gagal sebelum kerjaan peminjaman
   dimulai. Tidak ada file baru yang masuk daftar gagal. `pint` **tidak** dijalankan di CI, jadi tidak memblokir pipeline.
3. **BUG-3, BUG-4, BUG-5, BUG-7 sudah diperbaiki** di sesi verifikasi lanjutan
   (commit `b6099b3`, `db48328`, `5d67ea0`), dan **BUG-6 sudah selesai** (commit `bc9a3ed`).
   `stock` bukan lagi risiko; relasi `activeLoans()` kini dipakai; barang yang dipinjam sudah
   tidak muncul di dropdown; dan nomor dokumen sudah urut/aman. Tidak ada tech-debt aktif dari
   daftar ini. Perhatikan BUG-8 sudah dikoreksi di §3.3 — yang tersisa hanya copy view dan
   sudah diperbaiki di `aa5d05a`.
4. **`phpspreadsheet` di-install dengan `--ignore-platform-req=ext-gd`** karena platform PHP
   mesin ini tidak punya `ext-gd`. Menulis `.xlsx` di modul laporan ini tidak menyentuh gambar,
   jadi aman. Kalau nanti laporan perlu perendaman image (logo/ikon dalam sel), wajib
   menambahkan `ext-gd` dulu.
5. **Skema index belum lengkap** — `submissions.department` dan `submissions.created_at` difilter di `ReportController` tapi tanpa index. Belum jadi bottleneck pada data skala sekolah.
6. **`Item.current_status` punya 5 nilai enum tapi hanya 2 yang bisa dicapai** lewat UI. Nilai `'dalam_perbaikan'` dan `'disposed'` hanya bisa diset via Tinker. Section 4 baru benar-benar "Sebagian" karena ini.

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
belum dikerjakan lebih lanjut.**

### Status langkah teknis

1. **Konflik push — selesai.** Konflik modify/delete dengan `2688930` diselesaikan dengan versi
   lokal menang, file laporan ini dipertahankan. Saat rebase, `git rebase --continue` pernah gagal
   sekali karena `.git/index.lock` bentrok, sehingga commit BUG-6 dibuat ulang manual dari pesan
   commit aslinya — diff-nya diverifikasi identik dengan versi sebelum rebase.
2. **Push — selesai (termasuk sesi verifikasi lanjutan).** Semua commit
   (`b6099b3`, `db48328`, `5d67ea0`, `4ca0511`, `83dd94c`, dan pembaruan laporan ini) ter-push.
   `origin/main` identik dengan HEAD lokal, `git status -sb` tanpa selisih.
   Push harus dijalankan dari PowerShell Windows karena WSL tidak punya kredensial:

   ```powershell
   Set-ExecutionPolicy Bypass -Scope Process -Force
   cd "C:\Users\user\OneDrive\Documents\Project Web Inventaris\Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web"
   git push origin main
   git ls-remote origin main     # harus sama dengan git rev-parse HEAD
   ```

### Sisa pekerjaan berikutnya

3. **Section 2 — Aset per-unit / per-serial** (butuh keputusan skema `asset_units`).
4. **Section 6 — Dashboard lanjutan** (butuh keputusan produk statistik per role).
5. **Section 12 — QR Code** tetap dilarang tanpa persetujuan eksplisit (`AGENTS.md` §4).