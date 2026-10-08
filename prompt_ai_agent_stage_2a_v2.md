# PROMPT AI AGENT — STAGE 2A V2
## AssetUnit, Condition, Status & Loan Integrity Hardening

Repository:
`Sistem Informasi Inventaris dan Logistik Sekolah Berbasis Web`

## TUJUAN

Lanjutkan pengembangan dari kondisi repository SAAT INI.

Jangan melakukan rewrite atau reset project.

Fondasi migrasi `asset_units` yang sudah diperbaiki harus dianggap sebagai baseline stabil.

Baseline terakhir:
- Full test: `141 passed, 560 assertions`
- `AssetUnitBackfillTest`: `9 passed, 41 assertions`
- `DemoReadinessTest`: `5 passed, 50 assertions`
- GitHub Actions terakhir: GREEN
- HEAD terakhir: `9430d0e`

Fokus Stage 2A V2 adalah memperkuat integritas business logic pada:

1. AssetUnit individual
2. Condition vs Status
3. Loan ↔ AssetUnit
4. Pending loan / reservation
5. Approval re-check
6. Pencegahan double booking
7. Status transition
8. Condition history
9. Location history
10. Aggregate status Item
11. Authorization
12. Regression test

JANGAN mengerjakan QR Code, dashboard redesign besar, export baru, mobile app, atau redesign UI total.

---

# ATURAN UTAMA

## 1. AUDIT DULU

Sebelum mengubah kode:

Audit repository aktual.

Minimal periksa:

- `app/Models/AssetUnit.php`
- `app/Models/Item.php`
- `app/Models/Loan.php`
- `app/Http/Controllers/Sarpras/LoanController.php`
- controller AssetUnit/Inventory
- migration `asset_units`
- migration `loans`
- condition history
- location history
- audit trail
- Policy/Gate
- Form Request
- routes
- Blade AssetUnit/Inventory/Loan
- existing Feature/Unit Test
- `AUDIT_IMPLEMENTATION_REPORT.md`
- `AGENTS.md`

Cari seluruh penggunaan:

```text
asset_unit_id
current_status
condition
status
menunggu
disetujui
dipinjam
terlambat
dikembalikan
aktif
dalam_perbaikan
tidak_aktif
disposed
```

Jangan mengasumsikan struktur repository.
Repository aktual adalah sumber kebenaran.

## 2. TEMUAN AUDIT YANG WAJIB DIPERIKSA
Audit sebelumnya menemukan area yang perlu diperkuat.

### A. POTENSI DOUBLE BOOKING
`AssetUnit::loanable()` saat ini perlu diperiksa karena pending loan dengan status `menunggu` berpotensi tidak dianggap sebagai reservation.

Skenario yang WAJIB diuji:
- **AssetUnit A**: `status = aktif`
- **Loan A**: `asset_unit_id = A`, `status = menunggu`
- **User lain membuat Loan B**: `asset_unit_id = A`, `status = menunggu`

Sistem harus menentukan apakah Loan B boleh dibuat.
Untuk satu AssetUnit fisik, JANGAN membiarkan dua pending/approved loan mengambil unit yang sama tanpa business rule yang jelas.

## 3. DEFINISIKAN STATUS LOAN DENGAN JELAS
Audit penggunaan status Loan.
Pisahkan konsep:
- `menunggu`: pengajuan belum diproses
- `disetujui`: pengajuan disetujui / reserved jika memang konsep ini digunakan
- `dipinjam`: barang secara fisik sedang dipinjam
- `terlambat`: barang masih dipinjam tetapi melewati due date
- `dikembalikan`: transaksi selesai
- `ditolak`: pengajuan ditolak

Gunakan vocabulary yang benar-benar sudah ada di repository.
JANGAN menambah status baru jika tidak diperlukan.
Yang penting setiap status punya arti tunggal.

## 4. ACTIVE LOAN HARUS KONSISTEN
Audit helper seperti:
- `activeLoans()`
- `isReturnable()`
- `loanable()`
- `STATUS_BORROWED`

Pastikan definisi "active loan" tidak saling bertentangan.
Khusus `Loan::STATUS_BORROWED` sudah menjadi baseline untuk status fisik:
- `dipinjam`
- `terlambat`

Jangan mengembalikan status `disetujui` sebagai status fisik borrowed jika memang controller saat ini tidak menggunakannya sebagai transisi.

Untuk `menunggu` dan `disetujui`, tentukan apakah mereka:
- reservation
- workflow state
- atau bukan active physical loan

dan gunakan definisi tersebut secara konsisten.

## 5. PENDING LOAN / RESERVATION
Ini salah satu fokus utama Stage 2A V2.
Audit apakah satu AssetUnit boleh mempunyai:
- 1 pending loan, atau
- beberapa pending loan dengan urutan tanggal.

Jangan mengambil keputusan hanya dari asumsi. Periksa business flow existing.

Jika repository tidak memiliki sistem reservation berdasarkan jadwal, gunakan aturan sederhana dan aman:
**Satu AssetUnit tidak boleh mempunyai lebih dari satu pending/approved loan aktif yang konflik.**

Jika sistem memang mendukung peminjaman berdasarkan tanggal/waktu, pertimbangkan overlap berdasarkan:
- start date
- due date
- status loan

Tetapi JANGAN membangun sistem reservation kalender besar jika belum ada kebutuhan.

## 6. APPROVAL WAJIB RE-CHECK AVAILABILITY
Ini WAJIB.

Saat Loan dibuat:
`Loan A` → `menunggu`

Kemudian sebelum `approve()`:
JANGAN hanya mengubah `AssetUnit` → `dipinjam`.

Tetapi periksa ulang:
1. AssetUnit masih ada
2. AssetUnit belum dipinjam
3. AssetUnit tidak dalam perbaikan
4. AssetUnit tidak nonaktif
5. AssetUnit tidak disposed
6. Tidak ada loan lain yang sudah mengambil unit tersebut
7. Loan masih valid untuk disetujui

Jika tidak tersedia:
- approval harus ditolak
- berikan alasan yang jelas
- jangan mengubah status AssetUnit
- jangan membuat data menjadi setengah berubah

## 7. TRANSACTION & ATOMICITY
Audit method:
- create loan
- approve loan
- reject loan
- return loan
- perubahan AssetUnit
- perubahan condition

Jika satu action mengubah lebih dari satu record penting, pertimbangkan:
```php
DB::transaction(...)
```

Tujuannya mencegah:
- Loan berhasil diubah tetapi AssetUnit gagal diubah, atau
- AssetUnit berubah tetapi Loan gagal disimpan.

Jangan menambahkan transaction secara membabi buta. Gunakan hanya pada operasi yang memang membutuhkan atomicity.

## 8. CONCURRENCY / RACE CONDITION
Periksa skenario:
- User A membuka AssetUnit 001
- User B membuka AssetUnit 001
- A approve
- B approve

Keduanya tidak boleh berhasil jika hanya tersedia satu unit.
Jika perlu locking database:
- gunakan pendekatan yang sesuai dengan database project
- jangan mengubah database engine
- jangan membuat solusi kompleks tanpa test

Tambahkan test untuk business behavior tersebut jika memungkinkan.

## 9. ASSETUNIT STATUS
AssetUnit harus merepresentasikan kondisi operasional unit fisik.
Target status yang perlu diaudit:
- `aktif`
- `dipinjam`
- `dalam_perbaikan`
- `tidak_aktif`
- `disposed`

`terlambat` tidak perlu menjadi status AssetUnit jika hanya status Loan.
Jika repository sudah memiliki struktur berbeda, jangan mengganti tanpa alasan.

## 10. STATUS TRANSITION
Audit transition:
- `aktif` ↓ `dipinjam`
- `dipinjam` ↓ `aktif`
- `aktif` ↓ `dalam_perbaikan`
- `dalam_perbaikan` ↓ `aktif`
- `aktif` ↓ `tidak_aktif`
- `tidak_aktif` ↓ `aktif`
- `aktif` / `tidak_aktif` ↓ `disposed`

Disposed seharusnya menjadi state terminal kecuali repository memang memiliki mekanisme reactivation khusus.
Jangan membuat status bisa diubah bebas melalui request biasa.

## 11. CONDITION VS STATUS
WAJIB dipisahkan.

**Condition:**
- `baik`
- `rusak_ringan`
- `rusak_berat`
- `tidak_layak`
- `hilang`

**Status:**
- `aktif`
- `dipinjam`
- `dalam_perbaikan`
- `tidak_aktif`
- `disposed`

**Contoh valid:**
- `condition = baik`, `status = dipinjam`
- `condition = rusak_berat`, `status = dalam_perbaikan`

Jangan menyimpan informasi status operasional di field condition atau sebaliknya.
Jika vocabulary repository berbeda, pertahankan vocabulary existing yang sudah konsisten.

## 12. RULE PENGEMBALIAN BERDASARKAN CONDITION
Audit `returnLoan()`.
Saat barang dikembalikan:
- `condition = baik` → `AssetUnit` → `aktif`

Tetapi jika:
- `condition = rusak_berat` → jangan otomatis memaksa `AssetUnit` → `aktif`

Pertimbangkan rule:
- `baik` → `aktif`
- `rusak_ringan` → `aktif`
- `rusak_berat` → `dalam_perbaikan` (atau rule lain yang sesuai dengan business logic repository)

Untuk `tidak_layak` dan `hilang`, jangan otomatis menjadikan asset aktif.
Pilih behavior berdasarkan desain aplikasi dan dokumentasikan.
JANGAN mengubah rule ini hanya karena terlihat ideal tanpa audit terhadap flow existing.

## 13. ITEM VS ASSETUNIT
Ini temuan penting.
Jika satu Item memiliki banyak AssetUnit:
```text
Laptop Lenovo
├── Unit 001 → aktif
├── Unit 002 → dipinjam
└── Unit 003 → aktif
```

maka `Item.current_status = dipinjam` bisa menyesatkan karena hanya satu unit yang dipinjam.

Audit penggunaan `items.current_status` untuk individual item.
AssetUnit harus menjadi source of truth untuk status fisik unit.

Jika Item membutuhkan status agregat, buat aturan yang jelas.
Contoh:
- 0 dipinjam → Semua Aktif
- 1 dari 3 dipinjam → Sebagian Dipinjam
- 3 dari 3 dipinjam → Semua Dipinjam

Jangan langsung menghapus `items.current_status`. Cari dahulu semua penggunaannya.
Jika field masih diperlukan untuk compatibility, tentukan apakah:
- menjadi derived/aggregate state
- atau hanya digunakan untuk non-individual item

Dokumentasikan keputusan.

## 14. BACKFILL LEGACY JANGAN RUSAK
Behavior ini WAJIB tetap:
- `stock = 3`, `item status = dipinjam`, `active loan = 1` → hasil: `1 AssetUnit = dipinjam`, `2 AssetUnit = aktif`
- `stock = 3`, `active loan = 0` → hasil: `3 AssetUnit = aktif`

Jangan kembali ke bug lama (`3 AssetUnit = dipinjam`).
Pertahankan `is_legacy_migrated` dan corrective migration yang sudah ada.

## 15. CONDITION HISTORY
Audit history condition.
Perubahan: `baik` → `rusak_ringan` → `rusak_berat` → `tidak_layak` harus tetap dapat ditelusuri.
Pastikan jika data individual `condition_history.asset_unit_id` digunakan dengan benar.
Jangan menghapus history ketika condition berubah.
Jika history sudah benar, jangan rewrite.

## 16. LOCATION HISTORY
Audit:
`AssetUnit` → `Location` → `LocationHistory`

Contoh: Unit 001 (Lab Komputer) ↓ (Ruang Guru) ↓ (Lab Komputer).
Semua perpindahan harus tetap dapat dilacak.
Perubahan lokasi tidak boleh mengubah:
- identity asset
- serial number
- inventory number
- history sebelumnya

## 17. RELATIONSHIP ELOQUENT
Audit:
- `AssetUnit belongsTo Item`
- `AssetUnit belongsTo Location`
- `AssetUnit hasMany Loan`
- `AssetUnit hasMany ConditionHistory`
- `AssetUnit hasMany LocationHistory`
- `AssetUnit hasMany AuditTrail`

Gunakan relationship yang benar-benar tersedia. Jangan membuat duplicate relationship.
Periksa juga:
- `$fillable`
- `$guarded`
- `$casts`
- soft delete
- scopes
- accessors
- mutators

## 18. AUTHORIZATION
Audit seluruh action:
- create AssetUnit
- edit AssetUnit
- change condition
- change status
- repair
- deactivate
- dispose
- borrow
- approve
- reject
- return

Pastikan permission tidak hanya berdasarkan UI. Hidden button bukan security.
Server-side authorization wajib tetap berjalan.
Jangan mengubah role existing tanpa kebutuhan.

## 19. VALIDATION
Audit Form Request/controller.
Jangan mempercayai `status` / `current_status` dari request user secara langsung.

Contoh buruk:
```php
$model->update([
    'current_status' => $request->current_status
]);
```

Status sensitif harus berasal dari business action.
Contoh:
- `approve()` → `dipinjam`
- `return()` → `aktif` / `perbaikan`
- `repairComplete()` → `aktif`
- `dispose()` → `disposed`

## 20. UI ASSETUNIT
Jangan redesign total.
Pastikan user dapat melihat:
- Inventory Number
- Serial Number
- Barang
- Condition
- Status
- Lokasi

Jika tersedia. Untuk individual item, halaman detail sebaiknya dapat menunjukkan:
```text
Laptop Lenovo
Stock: 3 Unit

Unit 001 | Baik | Aktif
Unit 002 | Baik | Dipinjam
Unit 003 | Rusak Ringan | Dalam Perbaikan
```

Gunakan komponen UI existing. Jangan membuat design system baru.

## 21. ACTION UI
Jika status management belum memiliki UI, audit kebutuhan action.
Contoh action:
- `[Mulai Perbaikan]`
- `[Selesai Perbaikan]`
- `[Nonaktifkan]`
- `[Aktifkan]`
- `[Dispose]`

Action hanya tampil jika valid.
- AssetUnit dipinjam: jangan tampilkan Mulai Perbaikan jika business rule melarangnya.
- AssetUnit disposed: jangan tampilkan Pinjam.

Jangan membuat dropdown status bebas seperti `Status: [ aktif ▼ ]` untuk semua role.

## 22. MINIMUM STOCK
Audit fitur `minimum_stock`, `stockStatus()`, dan `isStockLow()`.
Jika sudah ada di model tetapi belum terlihat di UI, jangan langsung membangun dashboard besar.
Tambahkan secara incremental:
- badge stok menipis
- filter stok
- indikator sederhana
- daftar barang yang perlu perhatian

Gunakan data existing.

## 23. SEARCH & FILTER
Audit inventory search/filter.
Jika memungkinkan secara natural, dukung filter: Nama, Kode, No. Inventaris, Serial Number, Condition, Status, Lokasi, Kategori, Stok.
Jangan membuat query berat tanpa index atau kebutuhan nyata.

## 24. TEST WAJIB
Tambahkan/perkuat test minimal:

**AssetUnit:**
1. AssetUnit aktif dapat dipinjam.
2. AssetUnit dipinjam tidak dapat dipinjam lagi.
3. AssetUnit dalam perbaikan tidak dapat dipinjam.
4. AssetUnit tidak aktif tidak dapat dipinjam.
5. AssetUnit disposed tidak dapat dipinjam.

**Pending Loan:**
1. Satu unit tidak dapat mempunyai dua pending loan yang konflik.
2. Pending loan tidak dapat menggunakan unit yang sudah tidak tersedia.
3. Approval melakukan availability re-check.
4. Dua approval terhadap satu unit tidak boleh sama-sama berhasil.

**Return:**
1. Return baik → status sesuai rule.
2. Return rusak berat → tidak otomatis menjadi aktif jika rule mengharuskan perbaikan.
3. Return memperbarui condition history.

**Integrity:**
1. Satu AssetUnit tidak memiliki dua active physical loans.
2. Condition dan status terpisah.
3. Location history tetap tersimpan.
4. Legacy backfill 3 unit + 1 active loan → 1 dipinjam + 2 aktif.
5. Legacy backfill 3 unit + 0 active loan → 3 aktif.

**Authorization:**
1. User tanpa permission tidak dapat melakukan perubahan sensitif.

Jangan menghapus test existing.

## 25. TRANSACTION TEST
Jika menambahkan transaction, test minimal:
- Loan update berhasil + AssetUnit update berhasil.
- Jika salah satu gagal: keduanya rollback.

Jangan mengandalkan transaction hanya karena sudah ditulis.

## 26. MIGRATION
Jika membutuhkan migration baru:
- conservative
- idempotent
- hanya menyentuh data target
- jangan menyentuh manual AssetUnit tanpa alasan
- jangan menghapus data history
- `down()` jangan destruktif

Jika tidak diperlukan: `No migration required.`
Jangan membuat migration hanya untuk memenuhi checklist.

## 27. DOKUMENTASI
Update atau buat: `docs/PHASE_2A_ASSET_LOAN_INTEGRITY.md`

Dokumentasikan:
- hasil audit
- AssetUnit, Condition, Status, Loan state
- pending/reservation rule
- approval re-check
- double booking prevention
- transaction/locking jika digunakan
- Item aggregate status
- history
- authorization
- migration
- tests
- remaining issues

## 28. DOCUMENTATION CONSISTENCY
Audit dokumentasi repository terhadap source code aktual.
Khusus cek:
- `README.md`
- `AGENTS.md`
- `composer.json`
- `.github/workflows`
- installation guide

Pastikan informasi Laravel/PHP/testing yang ditulis sesuai dengan repository aktual.
Jangan mengubah dependency hanya untuk menyesuaikan README. Jika hanya dokumentasi yang salah, perbaiki dokumentasinya.

## 29. JANGAN SEKALIGUS MEMBERSIHKAN SEMUA PINT
Jika ditemukan Pint/style issue: jangan melakukan refactor massal dalam tahap ini. Catat saja jika tidak terkait langsung dengan perubahan.

**Prioritas:**
`business correctness` → `data integrity` → `security` → `tests` → `documentation` → `style`

## 30. REGRESSION TEST
Setelah implementasi:
```bash
php artisan test
```

Jika tersedia:
```bash
php artisan test --filter=AssetUnit
```

Gunakan nama filter aktual jika berbeda.
Jika aman:
```bash
php artisan route:list
php artisan view:cache
```

Pastikan full test tidak turun dari baseline tanpa alasan yang jelas.
Jika test gagal: jangan menyembunyikan failure, cari root cause, perbaiki, ulangi test.

## 31. GIT
Jangan membuat commit jika environment/agent memang tidak diperbolehkan.
Jika repository workflow mengizinkan commit, gunakan commit kecil dan deskriptif.

Contoh: `fix: prevent duplicate asset unit loan reservation`

Jangan squash atau rewrite history existing tanpa instruksi.

## 32. DEFINITION OF DONE
Stage 2A V2 selesai jika:
- AssetUnit individual jelas
- Condition ≠ Status
- Loan state konsisten
- Pending loan tidak menyebabkan double booking
- Approval melakukan availability re-check
- Race/concurrency risk ditangani sesuai kebutuhan
- Perubahan Loan dan AssetUnit atomic jika diperlukan
- Status transition valid
- Return berdasarkan condition memiliki rule yang jelas
- Item vs AssetUnit tidak menyesatkan
- Legacy backfill tetap benar
- Condition history aman
- Location history aman
- Authorization aman
- Minimum stock tidak rusak
- Test baru tersedia
- Seluruh regression test lulus
- Dokumentasi diperbarui
- Tidak ada fitur existing yang dihapus/rusak

---

# BATASAN SCOPE
JANGAN mengerjakan:
- QR Code / Barcode
- Redesign dashboard besar
- Redesign seluruh UI
- Export PDF / Excel baru
- Mobile app / API baru
- Notifikasi besar
- Refactor arsitektur besar
- Migrasi framework / database engine
- Menghapus fitur existing

Fitur tersebut dapat menjadi tahap berikutnya.

---

# OUTPUT WAJIB SETELAH SELESAI
Berikan laporan:
1. **Audit Findings**: Pisahkan Critical, High, Medium, Low.
2. **Changes**: Daftar file yang berubah + alasan.
3. **Business Rules**: Jelaskan singkat Loan pending, Loan approval, AssetUnit status, Condition, Return, Item aggregate status.
4. **Tests**: Tuliskan command dan hasil aktual (`php artisan test`).
5. **Migration**: Nama migration + tujuan + data terdampak. (Jika tidak ada: `No migration required.`)
6. **Documentation**: Daftar dokumentasi yang diperbarui.
7. **Remaining Issues**: Jangan menyembunyikan masalah yang belum selesai.

---

# INSTRUKSI TERAKHIR
KERJAKAN SECARA INCREMENTAL.
Jangan reset repository. Jangan membuat ulang modul yang sudah ada. Jangan menghapus fitur existing.
Jangan mengganti implementation yang sudah benar tanpa bukti. Jika menemukan sesuatu yang sudah benar, pertahankan.

Mulai dengan audit. Setelah audit, tunjukkan temuan paling penting secara internal dalam hasil akhir, lalu lakukan perubahan hanya pada bagian yang memang diperlukan.

**Prioritas:**
BUSINESS CORRECTNESS → DATA INTEGRITY → SECURITY/AUTHORIZATION → TEST → DOCUMENTATION → UI POLISH

Target utama tahap ini bukan menambah banyak fitur.
Target utamanya adalah **memastikan satu AssetUnit fisik tidak pernah dapat dipakai secara bersamaan oleh dua proses peminjaman dan seluruh state AssetUnit/Loan tetap konsisten.**