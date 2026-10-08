# PROMPT AI AGENT — STAGE 2A
## Audit dan Penguatan AssetUnit, Condition, dan Status

Anda bekerja pada repository Laravel:
`Sistem Informasi Inventaris dan Logistik Sekolah Berbasis Web`

## TUJUAN

Lanjutkan pengembangan dari kondisi repository SAAT INI.

Fondasi migrasi `asset_units` sudah diperbaiki dan harus dianggap sebagai baseline yang stabil.

Hasil terakhir yang WAJIB dipertahankan:
- Full test: `141 passed, 560 assertions`
- `AssetUnitBackfillTest`: `9 passed, 41 assertions`
- `DemoReadinessTest`: `5 passed, 50 assertions`
- GitHub Actions terakhir: GREEN
- HEAD terakhir: `9430d0e`
- Jangan melakukan rewrite besar atau reset fitur yang sudah berjalan.

Fokus tahap ini hanya:
1. Audit struktur `AssetUnit`
2. Memastikan pemisahan `condition` dan `status`
3. Memastikan state transition konsisten
4. Memastikan relasi AssetUnit dengan Item, Loan, Location History, Condition History, dan Audit Trail aman
5. Memperbaiki bug atau inkonsistensi yang ditemukan
6. Menambahkan test untuk behavior penting

JANGAN masuk ke QR Code, dashboard redesign, export, atau redesign UI besar pada tahap ini.

---

# ATURAN PALING PENTING

## 1. AUDIT DULU, JANGAN LANGSUNG CODING

Sebelum mengubah file apa pun, periksa repository aktual.

Minimal periksa:
- `AssetUnit` model
- migration `asset_units`
- model `Item`
- model `Loan`
- model condition history
- model location history
- model audit trail jika ada
- controller terkait AssetUnit
- controller Loan
- controller Item
- routes
- Form Request
- Policy/Gate
- Blade/component AssetUnit
- seeder/factory
- existing tests
- dokumentasi implementasi sebelumnya

Cari seluruh penggunaan:
- `asset_unit_id`
- `current_status`
- `condition`
- `status`
- `dipinjam`
- `terlambat`
- `aktif`
- `dalam_perbaikan`
- `tidak_aktif`
- `disposed`
- `rusak_ringan`
- `rusak_berat`
- `tidak_layak`
- `hilang`

Gunakan hasil audit repository sebagai sumber kebenaran.

Jangan mengasumsikan nama tabel, enum, field, route, atau relationship tanpa memeriksa kode aktual.

---

# 2. KONSEP DATA YANG WAJIB DIPERTAHANKAN

## CONDITION

Condition menjelaskan kondisi fisik barang.

Target minimal:
- `baik`
- `rusak_ringan`
- `rusak_berat`
- `tidak_layak`
- `hilang`

Jika repository sudah menggunakan vocabulary berbeda, JANGAN mengganti secara massal tanpa alasan. Audit terlebih dahulu dan gunakan sistem existing bila sudah konsisten.

## STATUS

Status menjelaskan keadaan operasional asset.

Target minimal:
- `aktif`
- `dipinjam`
- `terlambat`
- `dalam_perbaikan`
- `tidak_aktif`
- `disposed`

Jika `terlambat` hanya merupakan status Loan dan bukan status AssetUnit, pertahankan desain tersebut. Jangan memaksakan `terlambat` menjadi status AssetUnit.

Yang penting:
**Condition ≠ Status**

Contoh:
- condition = `baik`, status = `dipinjam`
- condition = `rusak_ringan`, status = `dalam_perbaikan`

Jangan menggunakan satu field untuk dua konsep tersebut.

---

# 3. AUDIT DATABASE ASSET_UNITS

Periksa migration `asset_units`.

Audit minimal:
- `id`
- `item_id`
- inventory/asset number jika tersedia
- serial number jika tersedia
- condition
- current_status
- location_id jika tersedia
- acquisition data jika memang ada
- `is_legacy_migrated`
- timestamps
- soft delete jika memang digunakan

Jangan menambah field hanya karena "ideal".

Tambahkan hanya jika benar-benar diperlukan oleh behavior existing, belum tersedia di tabel lain, aman terhadap data lama, dan memiliki test.

---

# 4. IDENTITAS INDIVIDUAL ASSET

Pastikan setiap AssetUnit dapat dibedakan secara individual.

Contoh:
Barang: Laptop Lenovo ThinkPad, Stock: 3

AssetUnit:
- Unit A
- Unit B
- Unit C

Ketiganya bukan hanya angka stock.

Jika repository sudah memiliki inventory number atau asset number:
- pastikan uniqueness sesuai kebutuhan
- jangan membuat duplicate identifier
- jangan mengubah data legacy secara agresif

Jika ada serial number:
- audit uniqueness
- serial number boleh NULL jika barang tidak memilikinya
- jangan membuat serial number palsu

---

# 5. AUDIT STATUS TRANSITION

Buat atau perkuat aturan transisi status.

Minimal audit:

## AKTIF → DIPINJAM
Boleh jika AssetUnit dapat dipinjam, tidak sedang dipinjam, tidak dalam perbaikan, tidak disposed, dan tidak tidak_aktif.

## DIPINJAM → AKTIF
Terjadi setelah Loan berhasil dikembalikan.

## DIPINJAM → TERLAMBAT
Jika memang repository menggunakan status ini pada AssetUnit.

Jika `terlambat` hanya berada pada Loan, jangan duplikasi status secara paksa ke AssetUnit.

## AKTIF → DALAM_PERBAIKAN
Boleh jika asset membutuhkan perbaikan.

## DALAM_PERBAIKAN → AKTIF
Hanya jika perbaikan selesai dan condition memungkinkan.

## AKTIF → TIDAK_AKTIF
Untuk asset yang sengaja dinonaktifkan.

## TIDAK_AKTIF → AKTIF
Hanya jika memang diperbolehkan oleh business rule.

## DISPOSED
Asset disposed tidak boleh kembali menjadi asset aktif kecuali repository memang memiliki mekanisme khusus.

---

# 6. CEGAH STATE YANG KONTRADIKTIF

Cari kemungkinan data seperti:
- status `dipinjam` tetapi tidak ada active Loan
- status `aktif` tetapi ada Loan aktif
- status `disposed` tetapi masih bisa dipinjam
- status `dalam_perbaikan` tetapi bisa dipinjam
- asset soft deleted masih muncul sebagai pilihan peminjaman
- satu AssetUnit memiliki lebih dari satu Loan aktif

Jika ditemukan:
1. reproduksi masalah
2. cari root cause
3. perbaiki logic pencegahannya
4. tambahkan regression test

Untuk data legacy yang tidak konsisten, gunakan migration korektif hanya jika benar-benar diperlukan dan harus conservative/idempotent.

---

# 7. LOAN ↔ ASSETUNIT

Audit integrasi dengan Loan yang sekarang sudah terbukti benar.

Baseline wajib:
- approval Loan → AssetUnit menjadi `dipinjam`
- return Loan → AssetUnit kembali `aktif`
- active loan hanya menggunakan AssetUnit yang memang tersedia
- satu AssetUnit tidak boleh mempunyai dua active Loan
- AssetUnit `dalam_perbaikan`, `tidak_aktif`, atau `disposed` tidak boleh dipinjam

JANGAN mengubah behavior backfill yang sudah diperbaiki.

Jika stock = 3 dan hanya ada 1 active Loan:
- 1 unit = `dipinjam`
- 2 unit = `aktif`

Jika tidak ada active Loan:
- semua unit = `aktif`

Jangan kembali ke behavior lama yang menandai seluruh unit sebagai `dipinjam`.

---

# 8. CONDITION HISTORY

Audit apakah perubahan condition dapat dilacak.

Contoh:
`baik` → `rusak_ringan` → `dalam_perbaikan` → `baik`

Pastikan:
- perubahan condition tidak menghilangkan history
- history memiliki `asset_unit_id` jika memang history bersifat individual
- timestamp tersedia
- actor/user tersedia jika sistem memang menyimpan informasi tersebut
- perubahan melalui controller/service tidak melewati business rule

Jika history existing sudah benar, jangan rewrite.

---

# 9. LOCATION HISTORY

Audit hubungan AssetUnit dengan lokasi.

Contoh:
Unit Laptop-001:
`Lab Komputer → Ruang Guru → Lab Komputer`

History harus tetap dapat ditelusuri.

Jangan hanya menyimpan lokasi terakhir jika repository memang sudah memiliki location history.

Pastikan history individual menggunakan `asset_unit_id` jika desain existing memang demikian.

---

# 10. MODEL DAN RELATIONSHIP

Periksa relationship Eloquent.

Minimal audit:
- `AssetUnit belongsTo Item`
- `AssetUnit belongsTo Location` jika tersedia
- `AssetUnit hasMany Loan`
- `AssetUnit hasMany ConditionHistory`
- `AssetUnit hasMany LocationHistory`
- `AssetUnit hasMany AuditTrail`

Gunakan nama model dan relasi yang sudah ada di repository.

Jangan membuat duplicate relationship dengan nama berbeda untuk objek yang sama.

Periksa juga:
- `fillable`
- `guarded`
- casts
- accessors
- mutators
- scopes
- soft deletes

Pastikan enum/status tidak diam-diam menerima nilai invalid.

---

# 11. BUSINESS LOGIC

Jika logic seperti menentukan apakah asset bisa dipinjam, tersedia, boleh diubah, disposed, atau status transition ditaruh langsung di Blade, audit apakah seharusnya berada di Model, Service, Policy, Form Request, atau domain class.

Jangan refactor besar hanya demi style. Prioritaskan behavior correctness.

---

# 12. AUTHORIZATION

Pastikan role tidak dapat memanipulasi AssetUnit secara bebas.

Audit berdasarkan struktur aplikasi saat ini:
- user tanpa permission tidak bisa mengubah asset
- user tidak boleh mengubah asset department/unit yang di luar kewenangannya
- approval workflow tetap berjalan
- perubahan status sensitif tidak boleh hanya bergantung pada hidden input Blade

Periksa Policy, Gate, middleware, dan controller authorization.

Jangan membuat aturan baru yang bertentangan dengan role existing.

---

# 13. VALIDATION

Audit request validation untuk:
- item_id
- asset/inventory number
- serial number
- condition
- status
- location_id

Khusus status:
Jangan percaya nilai status dari request user.

Jangan biarkan user mengirim `status=disposed` dan database langsung menerima.

Status sebaiknya dikontrol melalui business action:
- action borrow → `dipinjam`
- action return → `aktif`
- action repair → `dalam_perbaikan`

---

# 14. UI AUDIT

Audit halaman AssetUnit yang sudah ada.

Jangan redesign total.

Minimal tampilkan jika field tersebut memang tersedia:
- daftar AssetUnit
- identitas asset
- barang induk
- condition
- status
- lokasi
- inventory number
- serial number
- action sesuai permission

Gunakan komponen/style existing project.

Jika sudah ada badge component atau pattern seperti `x-condition-badge`, gunakan kembali.

Jangan memperkenalkan design system baru.

---

# 15. SEARCH DAN FILTER

Jika halaman AssetUnit sudah memiliki search/filter, audit agar dapat membedakan bila relevan:
- barang
- condition
- status
- lokasi
- inventory number
- serial number

Jangan memaksakan semua filter jika belum dibutuhkan.

---

# 16. TEST WAJIB

Tambahkan atau perkuat Feature/Unit Test.

Minimal:
1. AssetUnit aktif dapat dipinjam.
2. AssetUnit yang sedang dipinjam tidak dapat dipinjam lagi.
3. AssetUnit dalam perbaikan tidak dapat dipinjam.
4. AssetUnit disposed tidak dapat dipinjam.
5. Return Loan mengembalikan AssetUnit menjadi aktif.
6. Satu AssetUnit tidak dapat memiliki dua active Loan.
7. Condition dan status adalah dua data yang berbeda.
8. Perubahan condition tidak menghapus history.
9. Perubahan lokasi tidak menghilangkan location history.
10. User tanpa authorization tidak dapat melakukan perubahan sensitif.
11. Backfill: stock 3 + 1 active loan → 1 dipinjam + 2 aktif.
12. Backfill tanpa active loan: stock 3 → 3 aktif.

Jangan menghapus test existing.

---

# 17. REGRESSION TEST

Jalankan:

```bash
php artisan test
```

Jika tersedia, gunakan filter test AssetUnit yang sesuai dengan repository aktual.

Jika aman:
```bash
php artisan route:list
php artisan view:cache
```

Jangan menjalankan command destruktif terhadap database production.

---

# 18. JIKA MENEMUKAN BUG

Urutan:
1. reproduksi bug
2. cari root cause
3. buat test yang gagal
4. lakukan perubahan paling kecil
5. jalankan test
6. pastikan regression test lulus
7. dokumentasikan

Jangan:
- rewrite seluruh AssetUnit
- mengganti seluruh schema
- rename tabel massal
- menghapus data legacy
- redesign seluruh UI
- membuat module baru yang belum dibutuhkan

---

# 19. MIGRATION RULE

Jika membutuhkan migration:
- idempotent jika memungkinkan
- hanya menyentuh data yang perlu
- jangan mengubah manual AssetUnit tanpa alasan
- gunakan kondisi jelas
- jangan menghapus data secara agresif
- `down()` jangan destruktif jika tidak diperlukan

Jika tidak membutuhkan migration, JANGAN membuat migration.

---

# 20. DOCUMENTATION

Buat atau update:

`docs/PHASE_2A_ASSET_CONDITION_STATUS_AUDIT.md`

Isi:
- hasil audit
- struktur AssetUnit
- pemisahan condition/status
- state transition
- relationship
- authorization
- bug yang ditemukan
- bug yang diperbaiki
- migration jika ada
- test yang ditambahkan
- hasil test
- file yang berubah

Jangan menulis dokumentasi seolah-olah fitur sudah ada jika sebenarnya belum.

---

# 21. HASIL AKHIR WAJIB DILAPORKAN

Gunakan format:

## Audit Result
- AssetUnit:
- Condition:
- Status:
- Loan:
- Condition History:
- Location History:
- Authorization:
- UI:

## Changes
Daftar file yang diubah dan alasan singkat.

## Tests
Tuliskan command dan hasil aktual.

## Migration
Jika ada:
- nama migration
- tujuan
- data terdampak
- apakah idempotent

Jika tidak:
`No migration required.`

## Remaining Issues
Tuliskan masalah yang masih ada jika ditemukan.

Jangan menyembunyikan test yang gagal.

---

# 22. DEFINITION OF DONE

Stage 2A selesai jika:
- AssetUnit memiliki identitas individual yang jelas
- Condition dan status tidak tercampur
- Status transition konsisten
- AssetUnit tidak dapat dipinjam ketika tidak tersedia
- Tidak ada double active loan untuk satu AssetUnit
- Loan ↔ AssetUnit tetap sinkron
- Condition history aman
- Location history aman
- Authorization diperiksa
- Existing backfill behavior tetap benar
- Test behavior penting tersedia
- Regression test lulus
- Tidak ada fitur existing yang rusak
- Dokumentasi diperbarui

---

# BATASAN SCOPE

JANGAN kerjakan:
- QR Code
- barcode
- redesign dashboard
- redesign seluruh UI
- export PDF
- export Excel
- notifikasi besar
- mobile app
- API baru
- refactor arsitektur besar
- mengganti framework
- mengganti database
- menghapus fitur existing

Semua itu menjadi tahap berikutnya.

---

# INSTRUKSI TERAKHIR

Kerjakan secara incremental.

Repository saat ini adalah sumber kebenaran.

Pertahankan seluruh hasil perbaikan sebelumnya.

Jangan reset.
Jangan membuat ulang modul yang sudah ada.
Jangan mengganti implementasi yang sudah benar tanpa bukti masalah.

Jika menemukan sesuatu yang sudah benar, biarkan.

Prioritas:

**correctness → data integrity → authorization → test → documentation → UI polish**

Mulai dengan AUDIT terlebih dahulu, kemudian lakukan perubahan hanya pada bagian yang memang diperlukan.
