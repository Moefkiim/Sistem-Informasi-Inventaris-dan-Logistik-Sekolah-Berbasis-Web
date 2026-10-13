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
6 Okt — berstatus **8 dari 16 section Selesai penuh, 7 Sebagian, dan 1 belum disentuh** (Section 12
QR dilarang oleh `AGENTS.md` §4).

| # | Bagian | Prioritas | Status | Bukti |
|---|--------|-----------|--------|-------|
| 1 | Alur pengajuan draft → approval | P0 | **Selesai** | `Kajur/SubmissionController.php`, `Sarpras/SubmissionController.php`, `Principal/ApprovalController.php` — guard transisi + tabel `submission_histories` (Section 8) + blok "Riwayat Proses" di 3 halaman detail |
| 2 | Identitas aset individual | P1 | **Selesai** | tabel `asset_units` (1 baris per unit fisik) + backfill idempotent (`2026_10_07_000003`), peminjaman/mutasi kondisi/lokasi per unit, dashboard & laporan menghitung per unit — bukti D3/D5 di §2.6 |
| 3 | Modul Peminjaman & Pengembalian | P0 | **Selesai** | `LoanController.php`, `Loan.php`, `loans` migration, `LoanFactory`, 3 view, `LoanWorkflowTest.php` (29 test) + `recorded_by` (BUG-4) |
| 4 | Pemisahan kondisi vs status | P1 | **Sebagian** | `current_status` enum ada (`000001:38-46`), hanya di-set oleh `LoanController.php:186,232`; tidak ada UI untuk status `'dalam_perbaikan'`/`'disposed'` |
| 5 | Stok minimum | P1 | **Sebagian** | Kolom + helper `Item.php:60-77`, validasi, input form — tapi **tidak ada** alerting/filter/list badge yang memakainya |
| 6 | Dashboard statistik real | P0 | **Selesai** | `HomeController.php`, `routes/web.php:22` — statistik 3 angka scoped per role (Kajur = jurusannya, Sarpras/Kepsek = school-wide, Kajur tanpa department = 403), grafik Chart.js (`chart.umd.js` di-vendor `public/plugins/chartjs`): kondisi aset, distribusi per lokasi, persebaran per jurusan (non-Kajur), tren masuk/keluar 12 bulan; `DashboardScopingTest.php` (5 test) |
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

Ringkasan: **Selesai 8, Sebagian 7, Belum disentuh 1** (dari 16 section yang dilaporkan; section 17-20
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

### 2.6 Aset per-unit dan identitas fisik (Section 2, commit `1d28aba`–`bd56726`)

Section 2 kini **Selesai**: identitas aset yang tadinya menyatu di baris agregat `items`
(`inventory_number`/`serial_number` + `stock` = sekumpulan unit fisik) dipindah ke tabel
anak `asset_units` — satu baris per unit fisik. Item individual tetap memiliki baris
agregat `items` (kode/jenis/stok), sedangkan unit fisiknya hidup di `asset_units` dengan
identitas, kondisi, status, dan lokasinya sendiri. Item consumable tidak dibuatkan unit.

Perubahan inti:

- Migrasi `2026_10_07_000001_create_asset_units_table`: tabel `asset_units` (`item_id`,
  `unit_inventory_number`, `serial_number`, `current_condition`/`current_status`,
  `location_id`), diikuti `2026_10_07_000002_add_asset_unit_id_to_histories_and_loans`
  yang menghubungkan `loans`, `condition_histories`, `location_histories` ke unit.
- Backfill idempotent `2026_10_07_000003` + `App\Services\AssetUnitBackfiller`: item
  individual lama dibuatkan satu unit per satuan stok (dev demo: `expected=6 created=6`),
  peminjaman yang masih terbuka dipetakan ke unit yang sesuai, dan tidak menghapus data
  apa pun. Aman pada DB kosong (no-op) maupun DB berisi; rerun aman karena item yang
  sudah ber-unit dilewati.
- Peminjaman (`Sarpras/LoanController`) kini membidik `asset_units` tertentu: unit yang
  dipinjam berpindah status ke `dipinjam` (commit `9341cc7`).
- Mutasi kondisi (`InventoryController::updateCondition`) menulis `condition_histories`
  ber-`asset_unit_id`; mutasi lokasi (`updateLocation`) menulis `location_histories`
  ber-`asset_unit_id` dan menyinkronkan `items.location_id` dari unit pertama
  (commit `c31a010`, `7156357`).
- Dashboard (`HomeController`) dan laporan/rekap (`ReportController`, termasuk tampilan,
  cetak `reports/print`, PDF, dan ekspor Excel) menghitung statistik **per unit** untuk
  item individual; consumable tetap dihitung per kode barang (commit `bd56726`).
- Inventaris Kajur (`Kajur/InventoryController` + view index/show) menampilkan aset
  jurusannya **per unit** — ekspansi unit di index, daftar unit + kolom unit pada
  peminjaman aktif dan riwayat mutasi di detail (commit `1e41d37`).

Bukti D3 — jumlah aset benar per unit. Di dev DB demo (BRG-LAP 2 unit, BRG-PRJ 1 unit,
BRG-KAM 3 unit, BRG-KRT consumable), dashboard menampilkan `totalItems = 7` (6 unit fisik +
1 kode consumable), kondisi `aktif 6 / dipinjam 1`, lokasi `Lab RPL 3 / Tanpa Lokasi 4`,
jurusan `RPL 4 / TKJ 3`. Perilaku ini dikunci test `AssetUnitReportTest`,
`DashboardScopingTest`, dan laporan (cetak/Excel, scoping Kajur).

Bukti D5 — perbandingan sebelum/sesudah per-unit:

| Metrik | Sebelum (semantik agregat) | Sesudah (per unit) | Keterangan |
|---|---|---|---|
| totalItems | 4 (baris `items`) | **7** (6 unit + 1 consumable) | LAP×2, PRJ×1, KAM×3, KRT×1 |
| kondisi aktif | 3 | 6 | unit LAP×2 + KAM×3 + KRT |
| kondisi dipinjam | 1 | 1 | unit PRJ |
| lokasi Lab RPL | 2 | 3 | unit LAP×2 + PRJ |
| lokasi Tanpa Lokasi | 2 | 4 | unit KAM×3 + KRT |
| jurusan RPL | 3 | 4 | LAP×2 + PRJ + KRT |
| jurusan TKJ | 1 | 3 | KAM×3 |

Sebelumnya barang individual dihitung 1× per kode barang sehingga stok 3 unit kamera hanya
terbaca "1"; kini dihitung per unit fisik agar ketersediaan, kondisi, dan sebaran lokasi
terlihat per unit.

**Asumsi migrasi legacy yang tidak bisa dihindari:**

- **Kondisi dan lokasi**: Disalin ke semua unit hasil migrasi karena tidak ada data per unit sebelumnya (`is_legacy_migrated = true`).
- **Status 'dipinjam'**: Pada backfill awal sempat disalin ke semua unit; diperbaiki agar hanya unit yang terhubung ke loan aktif (`dipinjam`/`terlambat`) yang berstatus `dipinjam`, sedangkan sisanya `aktif`. Migration korektif `2026_10_08_000001` membersihkan unit legacy tanpa loan aktif yang sempat terkunci.
- **Item individual soft-deleted**: Tidak ikut di-backfill (`whereNull('deleted_at')`), sehingga riwayat loan dan histori mutasi miliknya tetap `asset_unit_id = NULL`.
- **Item individual stock 0 dengan nomor inventaris**: Dibuatkan tepat 1 unit fisik (D3 fallback) agar nomor inventaris uniknya tetap tercatat di sistem unit.

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

Sesi aset per-unit (7 Okt 2026, **Section 2**):

| File | Isi |
|---|---|
| `2026_10_07_000001_create_asset_units_table.php` | Tabel `asset_units` — identitas fisik per unit (`unit_inventory_number`, `serial_number`), `current_condition`/`current_status` dan `location_id` sendiri per unit, FK ke `items` |
| `2026_10_07_000002_add_asset_unit_id_to_histories_and_loans.php` | Tambah `asset_unit_id` (nullable) ke `loans`, `condition_histories`, `location_histories` |
| `2026_10_07_000003_backfill_asset_units_from_items.php` | Backfill idempotent via `App\Services\AssetUnitBackfiller` — verifikasi count, gagal transparan bila tidak sesuai harapan |

Semua sudah diverifikasi jalan pada `php artisan test` (137 test hijau saat ini).

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

Sesi aset per-unit — **Section 2** (7 Okt 2026, semua ter-push, CI hijau):

```
d4b4f66  test: perbaiki tanggal pada DocumentNumberSequentialTest (stabilitas CI)
1d28aba  feat: tabel asset_units + backfill idempotent data items lama (+ AssetUnitBackfillTest, 5 test)
9341cc7  feat: peminjaman per unit aset — status unit berpindah, dropdown memilih unit (+ LoanPerUnitTest, 8 test)
c31a010  feat: ubah kondisi fisik per unit aset dengan histori condition_histories (+ AssetUnitConditionTest, 7 test)
7156357  feat: mutasi lokasi per unit aset dengan histori location_histories (+ AssetUnitLocationTest, 7 test)
bd56726  feat: dashboard & laporan menghitung barang individual per unit (cetak/PDF/Excel,
            + AssetUnitReportTest, 4 test; D3/D5 §2.6)
1c93a73  docs: laporan audit sebagai bukti D3/D5 untuk Section 2 aset per-unit
1e41d37  feat: inventaris Kajur menampilkan aset per unit jurusan (index ekspansi unit +
            detail daftar unit/peminjaman/histori per unit; + KajurTenantIsolationTest 5 test)
```

---

## 7. Testing & Hasilnya

```
$ php artisan test
Tests:    137 passed (545 assertions)
```

| Test file | Jumlah | Cakupan |
|---|---|---|
| `DashboardScopingTest` | 5 | **Section 6**: statistik dashboard scoped — Kajur `RPL` tidak melihat angka jurusan `TKJ`, Kajur tanpa department dapat 403, Sarpras/Kepsek melihat angka global; chart Kajur ikut scoping & tanpa grafik per-jurusan; chart Sarpras memuat persebaran jurusan + tren masuk/keluar |
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
| `AssetUnitBackfillTest` | 5 | **Section 2**: backfill item individual → unit, idempotent (rerun `expected=6 created=0`), no-op DB kosong, mapping identitas (inventory/serial) & status/condition terwarisi, loan terbuka ter-petakan |
| `LoanPerUnitTest` | 8 | **Section 2**: peminjaman membidik unit spesifik, status unit berpindah, unit tersedia disaring, return mengembalikan unit, guard unit rusak/tidak aktif, dropdown memilih unit |
| `AssetUnitConditionTest` | 7 | **Section 2**: ubah kondisi per unit → `condition_histories` ber-`asset_unit_id`, kondisi unit & item disinkronkan, histori append-only |
| `AssetUnitLocationTest` | 7 | **Section 2**: mutasi lokasi per unit → `location_histories` ber-`asset_unit_id`, `items.location_id` disinkronkan dari unit pertama, histori tetap ada |
| `AssetUnitReportTest` | 4 | **Section 2 (D3/D5)**: dashboard menghitung individu per unit (totalItems=7, chart kondisi/lokasi per unit), consumable 1× sekali, laporan cetak mengekspansi unit, scoping Kajur pada ekspansi unit |
| `KajurTenantIsolationTest` | 5 | **Section 2 (M5)**: Kajur hanya melihat inventaris jurusannya (403 lintas jurusan / tanpa department), index Kajur mengekspansi unit (No. Unit/Seri/kondisi/status/lokasi per unit), consumable satu baris, detail menampilkan daftar unit + histori per unit |

Semua 137 lulus. Test kunci sebagai regression guard:

- `test_sarpras_can_open_activity_logs_page` — **BUG-1**. Sebelum view dibuat, gagal "View not found".
- `ItemStockMassAssignmentTest::stock cannot be set via mass assignment on create` — **BUG-6**.
  Payload `stock=999` ditolak, nilai tetap default DB. Tanpa perbaikan, test ini gagal.
- `WorkflowTransitionTest::approval page shows correct copy per status` — **BUG-8 (bagian copy)**.
  Memastikan halaman tidak lagi menampilkan "Keputusan Telah Dibuat" untuk `draft`/`submitted`.

Tidak ada test yang gagal atau di-skip. `vendor/bin/pint --test` masih gagal di **11 file**,
semuanya pre-existing sejak sebelum modul Peminjaman; file yang disentuh pada sesi verifikasi
lanjutan dan sesi aset per-unit selalu dirapikan pint per-file sehingga tidak ada file baru yang
ikut masuk daftar gagal (file baru sesi per-unit lolos pint: `AssetUnitReportTest`,
`inventory_rows.blade.php`, `HomeController`, `ReportController`).

**Status CI (TASK 1 prompt CI & demo).** Sebelum perbaikan, `composer install` di workflow akan
gagal karena `phpoffice/phpspreadsheet 5.10.0` mewajibkan `ext-gd` (juga zip/iconv/simplexml/
xmlreader/xmlwriter), sedangkan `shivammathur/setup-php` hanya memasang mbstring/intl/sqlite3/dom/
fileinfo/curl. Setelah `extensions` diperluas (commit `8236b6b`), CI run #20 **hijau penuh**;
`composer install` diverifikasi lolos tanpa `--ignore-platform-req` (lokal: hanya `ext-gd` yang
kurang, dan kini tersedia di CI). CI run #21 (commit `f8f6aaa`, seluruh 98 test di php 8.4) juga
**hijau**, dan run #22 (dokumen `c4e937c`) **hijau**. Kode export Excel
(`ReportController::exportExcel`) memang tidak memakai GD saat runtime — kehadirannya hanya untuk
memenuhi requirement composer.

**Dashboard (Section 6) dan CI.** Commit `5623c7d` (scoping statistik) dan `80166c4` (grafik
Chart.js) keduanya ter-push dan dijalankan di CI pada **php 8.4** (workflow yang sama, sqlite
in-memory + build Vite): run #23 dan #24 sama-sama **hijau**. `public/plugins/chartjs/chart.umd.js`
(v4.4.7, MIT) di-vendor statis seperti `public/plugins/daterangepicker` sehingga tidak menyentuh
manifest Vite. Uji manual smoke (login `kajur_rpl`/`sarpras`/`kepsek`, buka `/home`) memastikan
canvas render tanpa error konsol.

---

## 8. Hal yang BELUM Bisa Diimplementasikan

Prioritas ini **tidak diselesaikan** dan saya tidak membuatnya selesai:

| Section | Kenapa belum |
|---|---|
| 12 — QR Code | Sesuai `AGENTS.md` §4, barcode/QR code **dilarang keras** tanpa persetujuan eksplisit. Sengaja tidak dikerjakan. |

Section 2 (Aset per-unit) sudah keluar dari daftar ini — implementasi dan buktinya ada di §2.6.

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
2. **Pint/style check gagal** pada **11 file** (`vendor/bin/pint --test`). Angka ini diambil ulang
   7 Okt 2026. Seluruhnya pre-existing (file-file yang disentuh sesi per-unit lolos pint); `pint`
   **tidak** dijalankan di CI, jadi tidak memblokir pipeline.

   | Titik ukur | File gagal |
   |---|---|
   | `2e880da` (sebelum modul peminjaman) | 19 |
   | `dac980b` (sesudah, tanpa fix) | 27 |
   | `d2e1548` (sesudah fix pint) | 19 |
   | sesudah sesi verifikasi lanjutan | 18 |
   | sesudah sesi aset per-unit (sekarang) | 11 |

   Selisih turun dari 18 → 11 karena file yang tersisa hanya yang memang pre-existing
   (`bootstrap/app.php`, `AppServiceProvider.php`, `routes/web.php`, `DocumentController.php`,
   `Sarpras/LocationController.php`, `Sarpras/UserController.php`, `ItemFactory`,
   `LocationFactory`, `ReportScopingTest`, dan 2 migrasi lama).
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

Bagian 2, 3, 6, 7, 8, 9 dan 11 sudah ter-commit (Section 2 dan 3 dijelaskan di §2; Section 6 di
commit `5623c7d` + `80166c4`; Section 8 di `4ca0511`; Section 7 di sesi pertama; nomor dokumen
Section 9 di `db48328`). **Section 1, 4, 5, 10, 13, 14, 15, 16 belum dikerjakan lebih lanjut.**

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

3. **Section 2 — Aset per-unit / per-serial** — **SELESAI** 7 Okt 2026 (commit `1d28aba`–`bd56726`, bukti §2.6; CI hijau).
4. **Section 12 — QR Code** tetap dilarang tanpa persetujuan eksplisit (`AGENTS.md` §4).
---

## 12. Perbaikan UI Laporan & Riwayat Aktivitas (Task 0–4)

Tanggal: 9 Oktober 2026. Lingkup: halaman **Laporan** (`/laporan`, semua role) dan
**Riwayat Aktivitas** (`/sarpras/activity-logs`). Tidak ada fitur baru di luar batas
proyek; hanya perbaikan konsistensi UI/UX, aksesibilitas kontras, dan perapian data jurusan.

### 12.1 Akar Masalah Jurusan (Task 0)

Filter jurusan sebelumnya memakai string bebas sehingga pencocokan scoping Kajur mudah
gagal (spasi/kapitalisasi tidak konsisten), dan nilai di luar daftar resmi dapat lolos.
Dibuat sumber tunggal kebenaran:

- `config/departments.php` + helper `app/Support/Departments.php`
  (`all()`, `codes()`, `label()`, `normalize()`, `isValid()`).
- Daftar jurusan resmi: **RPL, TKJ, TKRO, TBSM, AKL, OTKP** (mengikuti `config/departments.php`).
- Normalisasi input pada `StoreUserRequest`/`UpdateUserRequest`, `InventoryController`,
  dan `LocationController`; dropdown jurusan memakai daftar dari config.
- Scoping Kajur (`ReportController`, `Kajur\InventoryController`) memakai
  `Departments::normalize()` agar cocok walau beda kapitalisasi/spasi.

### 12.2 Perubahan UI

- **Laporan**: layout filter seragam, tombol aksi konsisten (Filter/Reset/Export),
  tabel inventaris menampilkan **kode barang, nama, jurusan, lokasi, kondisi, jumlah,
  status**, empty-state khusus, dan scoping jurusan yang benar untuk Kajur.
- **Riwayat Aktivitas**: header + filter (role, aksi, pencarian, rentang tanggal),
  paginasi, badge aksi berwarna konsisten, kolom meta (waktu, aktor, target, ringkasan),
  empty-state khusus, dan seluruh teks berbahasa Indonesia.
- **Navigasi**: item "Riwayat Aktivitas" memakai ikon yang konsisten; label grup menu
  dari `config/departments.php`.

### 12.3 Hasil Ukur Kontras (WCAG AA)

Diuji otomatis (headless Chromium) pada halaman `/laporan` dan `/sarpras/activity-logs`.
Sebelum perbaikan ditemukan puluhan elemen dengan rasio kontras < 4.5:1 (mis. badge
`badge-light-*`, tombol `btn-outline-*`, teks `text-muted`). Setelah penyesuaian token
warna pada `resources/views/layouts/app.blade.php`:

| Halaman | Elemen diuji | Pelanggaran < 4.5:1 (awal) | Setelah perbaikan |
|---|---|---|---|
| `/laporan` | seluruh teks/tombol terlihat | ada (badge/tombol/teks muted) | **0** |
| `/sarpras/activity-logs` | seluruh teks/tombol terlihat | ada (badge/tombol/teks muted) | **0** |

### 12.4 File yang Berubah (Task 0–4)

- Baru: `app/Support/Departments.php`, `config/departments.php`,
  `database/migrations/2026_10_09_000001_normalize_department_values_to_abbreviations.php`,
  `resources/views/components/empty-state.blade.php`,
  `resources/views/components/filter-chip.blade.php`,
  `resources/views/components/page-header.blade.php`,
  `tests/Feature/ActivityLogUiTest.php`, `tests/Feature/DepartmentNormalizationTest.php`.
- Diubah: `ReportController.php`, `Kajur/InventoryController.php`, `Kajur/SubmissionController.php`,
  `Sarpras/ActivityLogController.php`, `Sarpras/InventoryController.php`,
  `Sarpras/LocationController.php`, view `laporan/*` dan `sarpras/activity-logs/*`,
  `resources/views/partials/sidebar.blade.php`, `resources/css/app.css`,
  beberapa test pendukung (`DemoReadinessTest`, `KajurTenantIsolationTest`,
  `ReportExportTest`, `ReportScopingTest`).

### 12.5 Verifikasi

- `php artisan test`: **seluruh suite hijau** (termasuk `ActivityLogUiTest`,
  `DepartmentNormalizationTest`, `ReportExportTest`, `ReportScopingTest`,
  `KajurTenantIsolationTest`, `DashboardScopingTest`).
- Audit kontras otomatis: **0 pelanggaran** pada kedua halaman (lihat 12.3).
- Uji tabel data tersembunyi (`.table-responsive` tanpa overflow horizontal) lolos pada
  viewport 1280/1024/768/480/375 px.

### 12.6 Catatan / Batasan

- Tidak ada perubahan skema database pada Task ini.
- Perbaikan hanya pada presentasi; logika bisnis (scoping, stok, approval) tidak diubah.

---

## 13. Instalasi Mulus (Laragon + MySQL) dan Database Nyata tanpa "Suntik" — TASK 1–10

Tanggal: 11 Oktober 2026. Lingkup: mengikuti prompt `prompt-instalasi-mysql-laragon.md`
(TASK 1–10, disediakan pemilik). Sasaran: dari clone bersih di Windows ber-Laragon, cukup beberapa perintah lalu
aplikasi langsung jalan dengan **MySQL/MariaDB sungguhan**, seluruh data awal berasal dari
migration + seeder yang di-commit (tanpa `tinker`/SQL manual), dan pembuktian lewat CI MySQL.

> Catatan: bagian ini adalah kondisi terkini. Untuk langkah instalasi terbaru, rujuk
> `README.md` §6 (bagian §10 laporan ini masih menyebut Node/Vite/SQLite dari era sebelum TASK 6
> dan sudah usang).

### 13.1 Status temuan I1–I9

| ID | Temuan | Status | Commit | Bukti |
|---|---|---|---|---|
| I1 | Database default SQLite | **Selesai** | `3a6e814` | `.env.example` `DB_CONNECTION=mysql`; `config/database.php` fallback `mysql` |
| I2 | Seeder hanya 4 akun | **Selesai** | `c4023cc` | `DemoMasterSeeder`…`DemoWorkflowSeeder`; `DemoDataSeederTest`/`DemoReadinessTest` |
| I3 | Tiga versi PHP berbeda | **Selesai** | `f1cb87e` | `config.platform.php=8.3.0`, lock Symfony 7.4.x; CI matriks 8.3 & 8.4; `composer check-platform-reqs` |
| I4 | Vite wajib dibangun | **Selesai** | `38c5384` | `public/js/siils.js` statis; `@vite` dihapus; `package.json`/`vite.config.js` hilang |
| I5 | Query khusus SQLite (`strftime`, `-31`) | **Selesai** | `179b065` | `App\Support\DbDate` + rentang Carbon `endOfMonth()`; `MonthlyReportTest` |
| I6 | README tidak ramah Windows/Laragon | **Selesai** | `c027887` | README §6: Laragon, cmd/PowerShell/bash, HeidiSQL, tabel troubleshooting |
| I7 | Lokal & zona waktu belum Indonesia | **Selesai** | `3f7fe66` | `.env.example` `Asia/Jakarta`/`id`; `lang/id/*`; `LocalizationTest` |
| I8 | MySQL tidak pernah diuji | **Selesai (lokal)** | `1dd2b22`+`179b065` | Job `mysql-install`; **lokal MySQL 8.4.3: 197 test hijau** (lihat §13.4) |
| I9 | `down()` indeks & `setup` tanpa seed | **Selesai** | `b680c30` + `9ec6187` | `dropIndex` dikoreksi; `composer setup` → `siils:install` (migrate + seed) |

### 13.2 Temuan BARU saat verifikasi MySQL (tidak ada di daftar I1–I9)

**N1 — `siils:install` gagal membuat database di MySQL.** `ensureDatabase()` memakai
`PDO::quote($database)` sehingga menghasilkan `CREATE DATABASE IF NOT EXISTS 'siils' ...`.
MySQL/MariaDB **tidak menerima identifier berkutip tunggal**, sehingga keluar error
`SQLSTATE[42000] ... 1064 ... near ''siils' CHARACTER SET ...'`. Bug ini **hanya muncul di
MySQL/MariaDB** (SQLite tidak lewat jalur ini) dan tidak tertangkap `InstallCommandTest`
karena test tersebut memaksa `config('database.default')='sqlite'`.

- **Perbaikan** (commit `a00adba`): helper `SiilsInstall::createDatabaseStatement()` memakai
  identifier backtick (valid di MySQL & MariaDB); nama database tetap divalidasi regex
  `^[A-Za-z0-9_]+$` sebelum dipakai. Auto-create kini dibatasi ke `mysql`/`mariadb`.
- **Regression guard**: `InstallCommandTest::test_statement_create_database_memakai_identifier_backtick()`.
- **Verifikasi nyata** (MySQL 8.4.3, §13.4): drop DB → `siils:check` gagal `1049` → `siils:install`
  membuat DB + migrate + seed (exit 0) → `siils:check` sukses → `siils:install` kedua idempoten.

### 13.3 Keputusan PHP

**Pilihan A** (disarankan prompt): `config.platform.php = 8.3.0`, lock memakai Symfony 7.4.x,
diuji di PHP 8.3, 8.4, dan 8.5. Tidak ada paket yang memaksa PHP ≥ 8.4 setelah keputusan ini.

### 13.4 Bukti verifikasi lokal (Windows/Laragon — MySQL nyata)

Dijalankan pada **MySQL 8.4.3** (Windows, via `cmd.exe`) dengan `DB_DATABASE=siils_verify`:

| Langkah | Hasil |
|---|---|
| `DROP DATABASE siils_verify` | exit 0 (dipastikan belum ada) |
| `php artisan siils:check` (sebelum dibuat) | **gagal** `1049 Unknown database 'siils_verify'` (exit 1) — pesan Bahasa Indonesia |
| `php artisan siils:install --force` | **membuat database otomatis**, 16 migration, 5 seeder — exit 0 |
| `php artisan siils:check` (sesudah) | sukses "Semua prasyarat terpenuhi" (exit 0) |
| `php artisan siils:install --force` (kedua) | idempoten — seeder melaporkan "sudah ada, dilewati" — exit 0 |
| Jumlah baris per tabel | diukur ulang 11 Okt 2026 (setelah skenario realized + revision): users 5, locations 6, items 9, asset_units 13, incoming 9, outgoing 4, distributions 2, submissions 8, submission_items 10, submission_histories 22, loans 5, activity_logs 41, condition_histories 15, location_histories 13, notifications 1, documents 0 (cocok dengan tabel README §6) |
| `php artisan test` di MySQL | **285 test hijau, 1228 assertion** (exit 0) |
| `php artisan test` di SQLite | **285 test hijau, 1228 assertion** |

Modul Dokumen memang kosong (`documents=0`) sesuai aturan (tidak ada berkas palsu).

### 13.5 Smoke test per role

Sudah terpenuhi oleh `tests/Feature/DemoReadinessTest.php` (dijalankan di seluruh suite,
termasuk job MySQL): setelah seed, `kajur_rpl`, `sarpras`, dan `kepsek` login lalu membuka
dashboard, inventaris, laporan, pengajuan/persetujuan, peminjaman, dan riwayat aktivitas —
semuanya `assertOk()` dan beberapa `assertSee(...)` memverifikasi halaman tidak kosong.

### 13.6 CI

`.github/workflows/tests.yml` job `MySQL/MariaDB` diperbarui sesuai TASK 10: database tidak
dibuat lebih dulu oleh service (`DB_DATABASE=siils_ci`), lalu langkah
`siils:install --force` (menguji pembuatan otomatis) → `siils:check` → `siils:install --force`
(membuktikan idempoten) → `php artisan test`.

**Hasil CI — run #45 HIJAU (semua job).** Push dilakukan dari PC Windows (git + SSH Windows),
commit `8467c47` ("Merge remote-tracking branch 'origin/main'"), HEAD `8467c47` identik dengan
`origin/main`. Run: https://github.com/Moefkiim/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web/actions/runs/38119258001

| Job | Hasil |
|---|---|
| SQLite (PHP 8.3) | ✅ success |
| SQLite (PHP 8.4) | ✅ success |
| MySQL/MariaDB (`mysql:8.0`) | ✅ success — termasuk langkah install (buat DB otomatis), `siils:check`, install ulang idempoten, dan seluruh suite |
| MySQL/MariaDB (`mariadb:10.11`) | ✅ success — idem |

**Catatan kejujuran soal run merah TASK 1.** Prompt TASK 1 meminta men-dorong perubahan lebih
dulu agar run merahnya terlihat. Dalam pengerjaan ini, commit TASK 1–9 **tidak sempat di-push**
dari lingkungan WSL (SSH `Permission denied (publickey)`, `gh` tidak tersedia) dan baru
di-push pada akhir; karena itu **tidak ada run merah khusus untuk TASK 1**. Sebagai gantinya,
riwayat run merah yang terdokumentasi sebelum sesi ini adalah **run #1–#8** (masalah `composer.lock`
vs versi PHP dan Vite) dan **run #35** (perubahan asset unit). Setelah semua perbaikan TASK 1–10,
**run #45 hijau penuh**. Langkah `siils:install`/`siils:check` juga sudah dibuktikan lokal di
MySQL 8.4.3 (§13.4) sekaligus sebagai langkah eksplisit di job CI MySQL.

### 13.7 Pencarian sisa data "suntikan"

`git grep` atas seluruh berkas ter-track tidak menemukan skrip sementara, pemanggilan `tinker`,
maupun `INSERT` SQL manual untuk data dev. Tidak ada step dokumentasi yang bergantung pada data
yang tidak dibuat seeder. Sisa artefak era Node yang masih ada: `.npmrc` (ter-track, isinya
`ignore-scripts=true`/`audit=true`, tidak dipakai lagi) dan `node_modules/` (gitignored, tidak
ter-commit) — keduanya bukan data suntikan dan tidak memengaruhi instalasi tanpa Node.

### 13.8 Checklist manual untuk pemilik (SEMUA "belum diverifikasi di Laragon")

- [ ] Clone bersih ke `C:\laragon\www\siils` — belum diverifikasi di Laragon
- [ ] Laragon **Start All**; PHP → Versi = 8.3/8.4; PHP → Ekstensi aktifkan `gd`, `zip`, `pdo_mysql` — belum diverifikasi di Laragon
- [ ] `composer setup` dari Terminal Laragon — belum diverifikasi di Laragon
- [ ] Buka `http://siils.test` — belum diverifikasi di Laragon
- [ ] Login `kajur_rpl` / `sarpras` / `kepsek` (password `password123`) — belum diverifikasi di Laragon
- [ ] Buka HeidiSQL (menu Database) → pilih `siils` → tabel & data demo terlihat — belum diverifikasi di Laragon
- [ ] `php artisan siils:check` melaporkan "Semua prasyarat terpenuhi" — belum diverifikasi di Laragon
- [ ] (Opsional, destruktif) `php artisan siils:install --fresh` — belum diverifikasi di Laragon

### 13.9 Perintah berguna

```bash
php artisan siils:check              # cek prasyarat (hanya-baca)
composer setup                       # install + .env + key + buat DB + migrate + seed
php artisan siils:install            # idempoten
php artisan siils:install --tanpa-demo
php artisan siils:install --fresh    # destruktif, ditolak di production
php artisan test                     # 285 test (SQLite lokal; MySQL/MariaDB di CI)
```

---

## 14. Perbaikan Alur Pengajuan & UI (TASK 1–9 prompt-perbaikan-alur-dan-ui.md)

Tanggal: 11 Oktober 2026. Prompt: `prompt-perbaikan-alur-dan-ui.md` dengan fitur F1–F7 dan
kriteria K1–K2. Seluruh commit ter-push dan semua job CI hijau.

### 14.1 Status F1–F7 dan K1–K2

| ID | Item | Status | Commit | Bukti CI / hasil test |
|---|---|---|---|---|
| F1 | Realisasi pengajuan (`realized`) | **Selesai** | `9d9b80b`, `3db8df3`, `930c22c`, `9b9e8d1`, `39302d5` | CI run `38136247464` **hijau**; `SubmissionRealizationTest` (9) + `DemoDataSeederTest` '
| F2 | Alasan penolakan wajib + Ajukan Ulang | **Selesai** | `c82a0a4` | `SubmissionRevisionTest` (9); termasuk dalam CI run `38136247464` |
| F3 | Pesan error/validasi terlihat | **Selesai** | `6720c86` | `VisibleErrorMessagesTest`; termasuk dalam CI run `38136247464` |
| F4 | Akun Saya, ubah sandi, reset oleh Sarpras | **Selesai** | `0327bf7`, `1f3e933` | `ProfileAndPasswordTest` (17) + `TemporaryPasswordTest` (Unit) |
| F5 | Halaman error + pesan sesi/throttle | **Selesai** | `2f2f3cb` | `CustomErrorPagesTest` |
| F6 | Notifikasi dalam aplikasi + badge/panel tugas | **Selesai** | `9b9031a` | CI run `38137686430` **hijau**; `SubmissionNotificationTest` (11) |
| F7 | Kerapian UI: Select2, toggle sandi, tabel responsif, page-header/empty-state | **Selesai** | `ce1b54c` | CI run `38139308280` **hijau** |
| K1 | CI PHP 8.3+MySQL & `composer setup` dari clone bersih | **Selesai** | `a5ab6fe` | CI run `38139977697` **hijau** — 3 kombinasi MySQL/MariaDB + job `composer-setup` |
| K2 | Seluruh migration TASK 3–5 jalan di MariaDB 10.11 & MySQL 8.0 | **Selesai** | (bagian dari run K1/K2) | job `MySQL/MariaDB` (`mysql:8.0`, `mariadb:10.11`) menjalankan migrate + seed + seluruh suite |
| TASK 9 | Test end-to-end + dokumentasi | **Selesai** | (commit dok ini) | `EndToEndWorkflowTest` (2, lintas SQLite & MySQL/8.4 lokal) |

> Catatan bukti: TASK 1–4 (F2–F5) tidak memiliki nomor run tersendiri karena didorong bersamaan
> sebelum TASK 5; seluruh commit tersebut tercakup dan hijau pada CI run `38136247464`
> (TIP TASK 5, `39302d5`) serta suite lokal 285 test/1228 assertion.

### 14.2 Keputusan desain: cara memperluas status di TASK 5

Kolom `submissions.status` semula `enum('draft','submitted','reviewed_sarpras','approved','rejected','cancelled')`.
Enum DB **kurang portabel** antar driver (SQLite tanpa native enum + gaya sintaks MySQL/MariaDB
berbeda). Agar migration yang sama jalan mulus di SQLite, MySQL 8.0, dan MariaDB 10.11, kolom
diganti menjadi `string(20)` dengan **default `draft`**; nilai yang sah tetap dijaga di level
aplikasi (model `Submission` + status badge relasi). Nilai baru `realized` ditambahkan sebagai
status final setelah approval. Alasan: enum DB tidak memberikan proteksi tambahan yang tidak
sudah dijamin oleh kode aplikasi dan justru menjadi sumber inkompatibilitas antar driver.

### 14.3 Asumsi yang ternyata keliru

1. **Semua `<table>` di `reports/index` belum di-wrap `table-responsive`** — hasil scan awal
   menghitung 5 tabel vs 1 wrapper. Ternyata seluruh 5 tabel berbagi satu `<div class="table-responsive">`,
   sehingga halaman itu aman; yang benar-benar perlu dibungkus adalah **4 tabel tab di
   `sarpras/inventory/show`** (histori lokasi, kondisi, barang masuk, barang keluar). `reports/pdf`
   dan `reports/print` sengaja dibiarkan (cetak/ekspor bergantung lebar penuh).
2. **Semantik `--env=testing` untuk MySQL** — variabel phpunit.xml (`force=false`) tidak
   menimpa variabel lingkungan proses, sehingga `DB_CONNECTION=mysql` dari luar tetap dipakai;
   tidak perlu flag tambahan.

### 14.4 Hal yang tidak bisa diverifikasi otomatis

- **TASK 7.5 (overflow horizontal):** tidak ada peramban headless di lingkungan ini. Verifikasi
  `scrollWidth <= clientWidth` pada 1366/1024/768/375 px tidak dapat dilakukan otomatis; mitigasi
  statis dilakukan (pembungkusan `table-responsive` dan pengecilan opsi dropdown). Sesi 12.3
  sebelumnya sempat memakai headless Chromium untuk audit kontras; tidak tersedia pada sesi ini.
- **Uji manual Laragon** (§13.8) tetap terbuka untuk pemilik.

### 14.5 Angka test akhir sesi ini

- SQLite (lokal): **285 test / 1228 assertion** hijau.
- MySQL 8.4 (lokal, Windows): **285 test / 1228 assertion** hijau.
- CI (commit `a5ab6fe`, run `38139977697`): **semua job success** — SQLite (PHP 8.3 & 8.4),
  MySQL/MariaDB (`mysql:8.0` PHP 8.4, `mariadb:10.11` PHP 8.4, `mysql:8.0` PHP 8.3), dan
  `composer-setup` (clone bersih tanpa `.env` → `composer setup` → `siils:check` → uji asap per role).

### 14.6 Hasil commit akhir sesi ini

Setelah TASK 9 dikerjakan, rangkaian commit ditutup dengan **`9584022`**
("docs: catat hash HEAD akhir di laporan audit") sebagai `HEAD`; `git rev-parse HEAD` identik
dengan `git ls-remote origin main`. Rantai commit F1–K2 tercantum di §14.1 (commit fitur TASK 9:
`78d47d6`).

---

## 15. Perbaikan Hasil Review R1–R6 (realisasi, sesi, verifikasi browser, laporan) — 12 Okt 2026

Prompt: `prompt-perbaikan-hasil-review.md`. Semua commit ter-push, semua job CI pada run terakhir
(`38223711449`) **hijau**. Test akhir: **317 test hijau** (SQLite lokal dan CI MySQL 8.0 /
MariaDB 10.11). Playwright dijalankan hanya di CI (tidak tersedia `pip` di lingkungan lokal) —
sesuai izin prompt "cukup jalankan lewat CI".

### 15.1 Status R1–R6

| ID | Temuan | Status | Commit | Bukti (test gagal → hijau, run CI) |
|---|---|---|---|---|
| R1 | **Kategori kosong pada "buat barang baru" di realisasi** | **Selesai** | `d1bf382`, `ad0f54f` | **Ya, R1 benar-benar menghasilkan 500** saat direproduksi (lihat §15.2). `RealizationIntegrityTest::test_kategori_null_pada_barang_baru_dinormalisasi_menjadi_umum` & `test_kategori_string_kosong_pada_barang_baru_dinormalisasi_menjadi_umum` **gagal sebelum** `d1bf382` (nilai `null` diteruskan ke kolom `items.category` NOT NULL → SQLSTATE), lalu **hijau** setelah normalisasi `'Umum'`. Selain itu reproduksi membuka form mengungkap T500 kedua: `resources/views/sarpras/realizations/create.blade.php:25` memanggil `$submission->departmentLabel()` yang tidak ada di `Submission` → `test_form_realisasi_approved_merender_tanpa_error` (regression, `ad0f54f`) **gagal 500 → hijau**. CI run `38155827558` dan `38203116135` **hijau** |
| R2 | **Realisasi menerima baris ganda / tidak lengkap / milik pengajuan lain** | **Selesai** | `d1bf382` | `RealizationIntegrityTest`: `test_submission_item_id_ganda_ditolak`, `test_realisasi_sebagian_baris_pengajuan_ditolak`, `test_layanan_menolak_himpunan_baris_yang_tidak_sama_persis` (service-level, tidak bergantung controller), `test_kuantitas_melebihi_permintaan_ditolak`, `test_baris_dari_pengajuan_lain_ditolak`, `test_nama_barang_baru_kosong_ditolak`, `test_layanan_menolak_nama_baru_kosong`, `test_entry_date_tahun_1900_ditolak`, `test_entry_date_masa_mendatang_ditolak`, `test_item_inventaris_yang_tidak_ada_ditolak` — **semua gagal sebelum, hijau sesudah**; CI run `38155827558` **hijau** |
| R3 | **Kode barang baru bentrok** | **Selesai** | `d1bf382` | Generator kode dibuat ulang sampai 3× saat bentrok unique `items.code`. `RealizationIntegrityTest::test_kode_barang_baru_tidak_bentrok_dengan_kode_sudah_ada` (menyisipkan kode yang akan dihasilkan lebih dulu) **gagal sebelum → hijau sesudah**; CI run `38155827558` **hijau** |
| R4 | **Pengerasan sesi & kata sandi sementara** | **Selesai** | `3e5f9c1` | `SessionHardeningTest` (5): `test_sesi_lama_tidak_berlaku_setelah_ganti_kata_sandi`, `test_sesi_lama_dihapus_setelah_reset_sandi_dari_antarmuka`, `test_kata_sandi_sementara_tidak_tersisa_di_payload_sesi_setelah_halaman_dibaca`, `test_kata_sandi_sementara_tidak_tercatat_di_log_aplikasi`, `test_activity_log_reset_tidak_memuat_kata_sandi_sementara` — **gagal sebelum → hijau sesudah**. `ProfileController` memanggil `session()->regenerate()` + pencabutan sesi lain (via `SessionStore`, khusus driver `database`); `SESSION_ENCRYPT=true` di `.env.example`; `TemporaryPassword` mengganti `shuffle()` dengan pengacakan berbasis `random_int`. CI run `38160270321` **hijau** |
| R5 | **Tidak ada verifikasi browser** | **Selesai** | `a0cbd5e` + perbaikan `a63db21`, `669f98d`, `42ac361`, `34004d1`, `4f58530` | Job CI baru `Browser smoke` (MySQL 8.0, PHP 8.4, `php artisan serve` + Playwright/Python + Chromium) menjalankan `tests/Browser/smoke.py`: penelusuran halaman ketiga role pada 1366×768 / 768×1024 / 375×812, pemantauan `console.error`/`pageerror`, `scrollWidth <= clientWidth`, tanpa teks "SQLSTATE"/"Whoops"/"Exception", skenario interaktif (alur pengajuan→persetujuan→realisasi, mode existing/new, **Select2**, lonceng notifikasi, halaman 404/403, throttle 6× login salah). Run akhir **`38203116135` hijau**: browser-smoke `exit_code 0`, **0 pelanggaran**, **123 screenshot** — diunggah sebagai artifact **`browser-smoke-artifacts`** (retensi 14 hari, berisi screenshot bertanda role/halaman/viewport + trace + indeks) |
| R6 | **Laporan Peminjaman & Riwayat Kondisi tidak ada** | **Selesai** | `d3edfe0`, `d264d80`, `4d238e6` | `ReportLoanTest` (8) + `ReportConditionHistoryTest` (7) hijau: filter periode/status/jurusan/barang-unit (loan) & kondisi tujuan (condition), scoping Kajur (jurusan sendiri), Kepala Sekolah read-only, ekspor PDF/Excel/Cetak membawa filter sama, `Loan::lateDays()` dihitung Carbon zona `Asia/Jakarta`. Commit `4d238e6` memperbaiki regresi lintas-driver yang ditangkap CI MySQL 8.0 (`only_full_group_by` pada agregat `condition` → `->reorder()` sebelum `groupBy`). CI run **`38222682658` merah** (bukti gagal dulu) → **`38223711449` hijau** |

**HEAD akhir:** `4d238e6ef5f79e401ee419409dfceb238f764de0` — `git rev-parse HEAD` identik dengan
`git rev-parse origin/main` dan `git status --short` bersih (hanya `prompt-perbaikan-alur-dan-ui.md`
untracked yang memang tidak di-commit).

### 15.2 Apakah R1 benar-benar menghasilkan 500 saat direproduksi?

**Ya — direproduksi dan terbukti dua kali:**

1. **Kategori kosong pada mode `new`** — test `test_kategori_null_pada_barang_baru_dinormalisasi_menjadi_umum`
   gagal terhadap kode sebelum `d1bf382` dengan SQL error karena `items.category` (NOT NULL, default
   `'Umum'`) diisi `null` eksplisit oleh `SubmissionRealizationService::resolveItem`; `entry_date`
   juga membiarkan tanggal ekstrem lolos. Setelah normalisasi `'Umum'` + validasi `required_if` mode
   `new` + `distinct`/`exists`/batas `entry_date`, semua test hijau di SQLite & MySQL.
2. **500 kedua yang tidak terduga** — membuka form realisasi (`GET /sarpras/submissions/{id}/realize`)
   untuk pengajuan berstatus `approved` menghasilkan **`BadMethodCallException` 500** karena
   `$submission->departmentLabel()` tidak ada di `Submission`. Ini bukan download `500` "JSON" melainkan
   halaman debug Laravel. Diperbaiki memakai `$submission->department` + regression test
   `test_form_realisasi_approved_merender_tanpa_error`.

### 15.3 Temuan baru dari `browser-smoke` (di luar daftar R1–R4)

| Temuan | Perbaikan | Bukti |
|---|---|---|
| **T500 format realisasi** (lihat §15.2 butir 2 — `departmentLabel` di `Submission`) | `ad0f54f` | regression test; halaman render ulang di browser-smoke |
| **Overflow horizontal 375 px** pada detail pengajuan Kajur (`kajur/submissions/show`) & Sarpras (`sarpras/submissions/show`) — footer aksi & badge status meluap | `4f58530` | strict-mode violation `scrollWidth > clientWidth` di hitungan browser-smoke → 0 pelanggaran pada run `38203116135` |
| Bug skrip smoke (bukan fitur): memanggil `ctx.stop_tracing()` yang tidak ada; `ancestor::div[contains(@class,'col')]` ambigu (mencocokkan `flex-column`) | `42ac361`, `34004d1` | run `38202758930` → `38203116135` |

### 15.4 Hal yang tidak bisa diverifikasi secara otomatis / keterbatasan

- **Melihat screenshot "dengan mata"** — screenshot tersedia sebagai artifact `browser-smoke-artifacts`
  di run `38203116135`, tetapi tidak ada peramban manusia/manual di lingkungan ini; penegakan kebenaran
  visual dilakukan lewat asersi DOM (200, bebas JS error, tanpa overflow, `.select2-container` ada).
- **Playwright lokal** tidak dijalankan karena `pip` tidak tersedia; seluruh eksekusi browser hanya via CI.
- **Job MySQL 8.0 pertama TASK 4** (`38222682658`) merah karena regresi `only_full_group_by` — ini
  *terbukti* sebagai bukti "gagal dulu, hijau sesudah" (commit `4d238e6`), bukan batasan.
- **Uji manual Laragon** (§13.8) tetap terbuka untuk pemilik.

### 15.5 Ringkasan commit dan CI sesi ini

```
d1bf382  fix(realisasi): cegah 500, baris ganda/sebagian, kode barang bentrok      -> run 38155827558 HIJAU
3e5f9c1  fix: perkuat sesi dan kata sandi sementara                                -> run 38160270321 HIJAU
a0cbd5e  ci: tambah job browser-smoke (Playwright/Python) + screenshot artifact    -> run 38163223521 MERAH (skrip)
a63db21  fix(browser-smoke): cocokkan href absolut, buka review sarpras ...        -> run 38200386985 MERAH (skrip)
669f98d  fix(browser-smoke): diagnosa overflow & form realisasi, Disetujui case..   -> run 38201221224 MERAH (skrip)
42ac361  fix(ci/browser-smoke): hentikan tracing lewat ctx.tracing.stop            -> run 38202758930 MERAH (1 pelanggaran)
4f58530  fix(ui): cegah overflow horizontal pada detail pengajuan kajur & sarpras  -> (bersama 34004d1)
34004d1  fix(ci/browser-smoke): batasi ancestor kolom item ke col-md-4             -> run 38203116135 HIJAU (0 violation, 123 screenshot)
ad0f54f  fix(realisasi): cegah 500 pada form realisasi (departmentLabel)           -> run 38203116135 HIJAU
d3edfe0  feat: laporan peminjaman                                                 -> run 38222682658 (mencakup d264d80) MERAH
d264d80  feat: laporan riwayat kondisi                                             -> (run merah = bukti gagal dulu, 4d238e6)
4d238e6  fix(laporan): reorder sebelum groupBy aggregat kond (only_full_group_by)  -> run 38223711449 HIJAU
```

---

## 16. Penyempurnaan Tahap Akhir TASK 1–7 (siklus hidup unit aset, keterlambatan peminjaman, aksesibilitas, impor massal, cadangan, penjaga N+1) — 13 Okt 2026

Prompt: `prompt-penyempurnaan-tahap-akhir.md`. TASK 1–6 adalah pekerjaan kode; TASK 7 adalah
pembaruan dokumentasi ini. Semua commit ter-push; **seluruh 7 job CI hijau** pada run terakhir
(`38361749790`). Test akhir: **376 test, 1601 asersi** hijau pada SQLite lokal maupun CI
(SQLite 8.3/8.4, MySQL 8.0 PHP 8.3/8.4, MariaDB 10.11), ditambah job `browser-smoke`
(Playwright + axe-core) dan uji round-trip cadangan.

### 16.1 Status S1–S6

Prompt memetakan enam area perbaikan sebagai S1–S6. Ringkasan:

| ID | Cakupan | Status | Commit | Bukti (test gagal → hijau, run CI) |
|---|---|---|---|---|
| **S1** | **Siklus hidup unit aset** — 5 kondisi + status unit + matriks transisi + riwayat *append-only* (TASK 1) | **Selesai** | `9477f5b`, `f974659`, `d6bf578` | `AssetUnitStatusLifecycleTest` (24 test: matriks transisi izin/tolak, unit `dipinjam` tak bisa diubah, `disposed` final & mengurangi stok, kondisi `hilang`/`tidak_layak_pakai` → `tidak_aktif`, riwayat tercatat, 403 role lain), `AssetUnitConditionTest` (7), `AssetUnitReportTest` (4), `AssetUnitAvailabilityTest` (19) — semua **gagal sebelum → hijau sesudah**. CI run `38249678726` & `38253349303` **hijau** |
| **S2** | **Status terlambat peminjaman** efektif via `Loan::scopeOverdue()` tanpa tulis di GET (TASK 2) | **Selesai** | `e89e711` | `LoanOverdueStatusTest` (7): batas hari jatuh tempo zona `Asia/Jakarta`, `terlambat` lama tetap dihitung, daftar/laporan/ringkasan konsisten, dan `test_get_daftar_peminjaman_tidak_menulis_ke_tabel_loans` — **gagal sebelum, hijau sesudah**. CI run `38252220091` **hijau** |
| **S3** | **Aksesibilitas & verifikasi browser** — axe-core ditegakkan di CI (TASK 5) | **Selesai** | `c3eba08`, `1cc6da5`, `a90eba0` | Temuan axe **272 → 189**, `serious` **83 → 0** (lihat §16.2). `A11Y_ENFORCE=true` + allowlist kosong (`tests/Browser/a11y-allowlist.json`). CI run `38325484587` **merah = bukti gagal dulu** (7 sisa `color-contrast`) → `38357080178` **hijau** |
| **S4** | **Impor inventaris massal** `.xlsx`/`.csv` (TASK 4) | **Selesai** | `a16880a` | `InventoryImportTest` (9): pembuatan barang & unit, kode otomatis, pratinjau semua-atau-tidak, kesalahan bernomor baris, duplikat kode/seri, batas ukuran/tipe/jumlah, rumus tidak dievaluasi, akses hanya Sarpras — **hijau**. CI run `38262670673` **hijau** |
| **S5** | **Cadangan/restore, rotasi log, panduan sekolah** (TASK 3) | **Selesai** | `28bdb02`, `5962b24`, `5f48d37`, `2c8775f` | `BackupRestoreCommandTest` (8) hijau; **uji round-trip nyata** di CI membandingkan 19 tabel + checksum tabel kunci pada MySQL 8.0 & MariaDB 10.11 untuk kedua jalur dump (lihat §16.3). CI run `38257017369` **merah = bukti gagal dulu** (deteksi `mysqldump`) → `38262333408` & `38361749790` **hijau** |
| **S6** | **Penjaga N+1 & anggaran query** (TASK 6) | **Selesai** | `336aafb` | `QueryBudgetTest` (9 test, 60 asersi): jumlah query **identik** pada 5 vs 25 baris untuk dashboard tiap role, daftar inventaris, detail barang, daftar peminjaman, riwayat aktivitas, notifikasi, dan seluruh jenis laporan (lihat §16.4). CI run `38361749790` **hijau** |

**HEAD kode akhir:** `336aafbe13016917c006270af59b204291238078` — `git rev-parse HEAD` identik dengan
`git rev-parse origin/main` saat commit dokumentasi ini dibuat (commit dokumentasi berada di atasnya).
`git status --short` bersih; hanya `prompt-penyempurnaan-tahap-akhir.md` (di luar repo) dan
`tests/Browser/__pycache__/` yang memang tidak di-commit.

### 16.2 Aksesibilitas & verifikasi browser (TASK 5) — bukti sebelum/sesudah

| Tahap | Run / Commit | Total temuan | `serious`/`critical` | `moderate` | Artefak |
|---|---|---:|---|---:|---|
| Sebelum | `38273789037` / `c3eba08` | 272 | **83** (link-name 42, scrollable-region-focusable 26, color-contrast 15) | 189 (heading-order 63, landmark-one-main 63, region 63) | `opencode-ci-art0` |
| Setelah fix tahap-1 | `38325484587` / `1cc6da5` | 196 | **7** (color-contrast) — job **gagal** | 189 | `opencode-ci-art` |
| Setelah final | `38357080178` / `a90eba0` | 189 | **0** | 189 | `opencode-ci-art2` |

Perbaikan: `aria-label="Akun Saya"` pada link profil (`resources/views/layouts/app.blade.php`),
`tabindex="0" role="region" aria-label` pada `.table-responsive` (`resources/views/reports/index.blade.php`),
CSS `.badge.badge-light { color: #4b5675 !important; }`, dan override warna `.menu-title` pada sidebar
`.aside-dark` menjadi `#e4e6ef` untuk memenuhi rasio kontras. `moderate` yang tersisa sengaja **tidak**
dikejar pada tahap ini (bukan penghalang, dan sebagian struktural); hanya temuan `serious`/`critical`
yang men-gagalkan job.

### 16.3 Cadangan/restore round-trip nyata (TASK 3)

Pada run `38361749790`, job `MySQL/MariaDB (mysql:8.0, PHP 8.4)` (`115177331459`) dan
`MySQL/MariaDB (mariadb:10.11, PHP 8.4)` (`115177331141`) menjalankan `ci/backup-roundtrip.sh`:
isi data → `siils:backup` → hapus & buat ulang database → `siils:restore` → bandingkan jumlah
baris semua tabel (19 tabel) dan checksum tabel kunci. Kedua job mencetak
`[dump-otomatis] OK - 19 tabel cocok` dan `[dump-php-portabel] OK - 19 tabel cocok`
("Semua uji round-trip cadangan lulus") untuk **kedua jalur** dump. Tabel kunci:
`users`, `items`, `asset_units`, `submissions`, `loans`, `activity_logs`; tabel teknis dikecualikan:
`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.

### 16.4 Penjaga N+1 dan anggaran query (TASK 6)

- **Guard:** `AppServiceProvider::bootLazyLoadingGuard()` — saat test (`runningUnitTests()`)
  `Model::preventLazyLoading(true)` sehingga relasi tanpa eager loading melempar exception dan
  menggagalkan suite; pada `local` hanya menulis peringatan ke log; produksi tidak terpengaruh.
- **Eager loading** diperbaiki pada `Sarpras\LoanController` (`assetUnit`), serta `Kajur\SubmissionController`,
  `Sarpras\SubmissionController`, dan `Principal\ApprovalController` (`items.item`). Tiga test yang
  mengakses relasi langsung ikut diperbaiki (`DemoDataSeederTest`, `EndToEndWorkflowTest`,
  `SubmissionRealizationTest`).
- **Anggaran query** (jumlah query identik pada 5 vs 25 baris) terverifikasi lewat `DB::listen`:
  Dashboard kajur/sarpras/kepsek **14**; daftar inventaris **7**; detail barang **15**; daftar
  peminjaman **8**; riwayat aktivitas **7**; notifikasi **5**; laporan inventaris **12**,
  incoming **10**, outgoing **10**, distribution **11**, submission **11**, loan **11**, condition **10**.

### 16.5 Keputusan dan asumsi yang (semula) salah

- **TASK 4 dikerjakan sebagai satu commit (`a16880a`), bukan tiga** (layanan; antarmuka; dokumentasi)
  seperti pada contoh prompt baris 98. Isi pekerjaan lengkap dan teruji; hanya granularitas commit
  yang berbeda. Dicatat sebagai deviasi sadar.
- **TASK 3:** asumsi awal memanggil `mysqldump` lewat eksekusi shell gagal di runner (CI `38257017369`
  merah, 3 job MySQL/MariaDB). Diperbaiki pada `2c8775f` dengan mendeteksi/menjalankan `mysqldump`
  lewat Symfony Process dan menyediakan jalur dump PHP murni (`--tanpa-mysqldump`).
- **TASK 5:** dugaan awal bahwa `color-contrast` bersumber dari item menu aktif ("Laporan & Rekap")
  **keliru** — node yang disorot axe adalah item sidebar non-aktif ("Dokumen & Berkas"). Karena akar
  penyebab pasti sulit dipastikan, dipakai solusi aman: menaikkan kontras **seluruh** `.menu-title`
  sidebar pada `.aside-dark` menjadi `#e4e6ef`, sehingga temuan `serious` menjadi 0.

### 16.6 Hal yang tidak bisa diverifikasi secara otomatis / keterbatasan

- **Uji manual Laragon / MySQL Windows** tetap menjadi tanggung jawab pemilik; lingkungan ini WSL2
  tanpa Laragon.
- **Pemeriksaan visual "dengan mata"** atas screenshot tidak mungkin di sini; kebenaran visual
  ditegakkan lewat asersi DOM + laporan axe di artefak CI (`browser-smoke-artifacts`).
- **Playwright lokal** tidak dijalankan (Chromium/pip tidak tersedia); seluruh verifikasi browser
  hanya lewat CI, sesuai izin prompt.
- **`php artisan view:cache` gagal di lingkungan lokal** karena komponen `laravel-exceptions-renderer`
  tidak ada di instalasi ini — quirk lingkungan, **tidak** memengaruhi CI (yang hijau).
- **`BackupRestoreCommandTest::test_backup_menyertakan_berkas_dokumen_privat_dan_dapat_dilewati`**
  sempat gagal flaky sekali (berkas sementara), namun lolos pada re-run dan pada run CI; bukan regresi
  dari TASK 6.

### 16.7 Ringkasan commit dan CI sesi ini

```
9477f5b  feat: siklus hidup unit aset (kondisi & status)                        -> run 38249678726 HIJAU
f974659  fix: penyempurnaan siklus unit aset                                    -> (bagian 9477f5b..d6bf578)
d6bf578  test: perluas cakupan status unit & laporan                            -> run 38253349303 HIJAU
e89e711  fix: status terlambat peminjaman efektif (scopeOverdue)                -> run 38252220091 HIJAU
28bdb02  feat: perintah cadangan (siils:backup)                                 -> (bagian TASK 3)
5962b24  feat: perintah pemulihan (siils:restore) + rotasi log                  -> (bagian TASK 3)
5f48d37  ci: uji round-trip cadangan                                            -> run 38257017369 MERAH (deteksi mysqldump)
2c8775f  fix: deteksi & jalankan mysqldump via Symfony Process                  -> run 38262333408 HIJAU
a16880a  feat: impor inventaris massal (xlsx/csv)                               -> run 38262670673 HIJAU
c3eba08  feat(a11y): axe-core di browser-smoke + perbaikan label/region         -> run 38273789037 HIJAU (baseline 83 serious)
1cc6da5  fix(a11y): kontras sidebar & badge-light + A11Y_ENFORCE                -> run 38325484587 MERAH (bukti gagal dulu, 7 serious)
a90eba0  fix(a11y): kontras menu-title sidebar aside-dark                       -> run 38357080178 HIJAU (0 serious)
336aafb  fix: guard N+1 + eager loading + QueryBudgetTest                       -> run 38361749790 HIJAU
```

**Run CI terakhir:** `38361749790` — 7/7 job **hijau** (SQLite 8.3, SQLite 8.4, MySQL 8.0 PHP 8.3,
MySQL 8.0 PHP 8.4, MariaDB 10.11, Composer setup dari clone bersih, Browser smoke).

---

## 17. Perbaikan Hasil Review Kode — N1–N5 (konkurensi, invarian kondisi–status, impor, restore) — 13 Okt 2026

Prompt review menemukan tiga masalah nyata dan dua pengerasan: **N1** race condition yang dapat
mengurangi stok dua kali, **N2** pelanggaran aturan kondisi–status (unit `hilang`/`tidak_layak_pakai`
tetap `aktif` / dapat dipinjam), **N3** jalan buntu status terlarang lewat impor, **N4** pengerasan
impor (pemuatan berkas besar, sisa pratinjau, kepemilikan berkas), dan **N5** validasi path saat
restore. Semua dikerjakan *test-first* (bukti gagal → hijau). HEAD kode: `572b6f1`. Test akhir:
**392 test, 1651 asersi** hijau pada SQLite lokal maupun CI.

### 17.1 Status N1–N5

| ID | Temuan | Perbaikan | Commit | Bukti (gagal → hijau) |
|---|---|---|---|---|
| **N1** | Perubahan status/kondisi unit memakai instansi model basi → stok bisa berkurang dua kali saat `disposed`, atau status menimpa perubahan lain | `changeStatus()`, `applyLoanStatus()`, `changeCondition()` dibungkus `DB::transaction` + `lockForUpdate()` dan selalu membaca status terbaru; `decrementStockForDisposal()` menolak stok tak konsisten (bukan `max(0, …)`); `InventoryController::updateStatus`/`updateUnitCondition` memindahkan pencarian unit ke dalam transaksi berkunci | `cb06f90` | `AssetUnitConcurrencyTest` (4 test): **3/4 gagal** pada kode pra-perbaikan (dispose ganda, instance basi menimpa unit `dipinjam`, status basi) → **hijau**. CI run `38377776477` **hijau** |
| **N2** | Kondisi `hilang`/`tidak_layak_pakai` tidak memaksa status `tidak_aktif`; unit tak layak tetap bisa dipinjam/diaktifkan | `AssetUnit::normalizeInitialState()` dipakai terpusat pada registrasi/impor/realisasi; `changeStatus()` menerima `resultCondition` (whitelist hasil perbaikan) dan menolak `aktif` untuk kondisi tak layak; `scopeLoanable()`/`isAvailableForLoan()` juga mengecualikan kondisi tak layak; `siils:check` memeriksa invarian data | `33af222` | `AssetUnitInvariantTest` (7 test): **7/7 gagal** pra-perbaikan → **hijau** |
| **N3** | Impor menerima status `dipinjam`/`disposed` (jalan buntu: unit terpinjam tanpa peminjaman) | `InventoryImportService::normalizeStatus()` hanya menerima `AssetUnit::IMPORTABLE_STATUSES` (`aktif`, `dalam_perbaikan`, `tidak_aktif`); kondisi tak layak dipaksa `tidak_aktif` dan dilaporkan sebagai **peringatan** di pratinjau (`warnings`) | `33af222` | Bagian dari `AssetUnitInvariantTest` (impor menolak 2 baris status terlarang; impor `Hilang`+`Aktif` → `tidak_aktif` + peringatan) |
| **N4** | Impor memuat seluruh berkas ke memori; pratinjau kedaluwarsa menumpuk; berkas pratinjau dapat dikonfirmasi pengguna lain | `LimitedReadFilter` membatasi baris/kolom saat baca `.xlsx`/`.csv`; `InventoryImportController::purgeStalePreviews()` menghapus pratinjau >24 jam; sidecar `{token}.meta.json` menyimpan pemilik, `authorizePreview()` mewajibkan pengunggah yang sama | `90bae96` | `ImportPreviewHygieneTest` (4 test): **3/4 gagal** pra-perbaikan → **hijau** |
| **N5** | Restore hanya menyaring `..` dan tidak melaporkan entri yang dilewati | `SiilsRestore::safeRelativePath()` memvalidasi **per segmen** (tolak kosong/`.`/`..`/backslash/`C:`), `isWithinRoot()` menahan target di dalam `storage/app/private`, dan command melaporkan jumlah + nama entri yang ditolak | `3986f26` | `BackupRestoreCommandTest::test_restore_melewati_entri_arsip_dengan_path_tidak_aman` (entri `files/../../evil.txt` dilewati, berkas aman tetap dipulihkan) |

### 17.2 Catatan keputusan & asumsi

- **N1 tanpa uji dua koneksi nyata:** suite berbagi satu koneksi database, sehingga uji paralel
  sungguhan akan *flaky* (SQLite *in-memory* per koneksi). Yang diuji deterministik adalah perilaku
  instansi basi (empat skenario); jalur terkunci yang sama tetap dieksekusi pada job CI
  MySQL/MariaDB. Batasan ini didokumentasikan langsung di docblock test.
- **`siils:check` tetap hanya-baca:** kelasnya berjanji "tidak mengubah apa pun". Karena itu
  pembersihan pratinjau kedaluwarsa dilakukan pada alur unggah (`preview()`), **bukan** di
  `siils:check`. `siils:check` hanya *melaporkan* pelanggaran invarian sebagai peringatan.
- **Fix lanjutan MySQL (bukti penting):** run `38381640395` **MERAH** — tiga job MySQL/MariaDB gagal
  karena pemeriksaan invarian memakai `count()` di atas subkueri `SELECT *` dari `LEFT JOIN`,
  memicu `Duplicate column name 'id'`. Diperbaiki di `572b6f1` (agregasi diselesaikan di PHP dan
  `whereNotExists` menggantikan join `loans`). Run `38382812441` **hijau**.
- **Perubahan tak terkait tidak di-commit:** Pint sempat memformat beberapa file lama
  (`DocumentController`, `AssetUnitAvailabilityTest`, dll.); perubahan itu dikembalikan agar commit
  tetap fokus.

### 17.3 Ringkasan commit dan CI sesi ini

```
cb06f90  fix: kunci baris unit pada perubahan status dan cegah pengurangan stok ganda  -> run 38377776477 HIJAU
33af222  fix: tegakkan invarian kondisi-status dan cegah status terlarang lewat impor   -> (gabung, lihat 572b6f1)
90bae96  fix: batasi pembacaan impor, bersihkan pratinjau kedaluwarsa, kunci kepemilikan -> (gabung)
3986f26  fix: validasi path per segmen dan laporkan entri arsip yang ditolak saat restore -> run 38381640395 MERAH (MySQL: kolom id ganda)
572b6f1  fix: hindari kolom id ganda pada pemeriksaan invarian di MySQL/MariaDB        -> run 38382812441 HIJAU (7/7)
```

**Run CI terakhir (kode):** `38382812441` — 7/7 job **hijau** (SQLite 8.3, SQLite 8.4, MySQL 8.0
PHP 8.3, MySQL 8.0 PHP 8.4, MariaDB 10.11, Composer setup dari clone bersih, Browser smoke). HEAD
kode: `572b6f101e763476e6d01e8b5e6d34c3a756386a`.

