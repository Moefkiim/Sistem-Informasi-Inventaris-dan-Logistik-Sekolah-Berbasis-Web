# Laporan Audit & Penguatan AssetUnit, Condition, dan Status (Stage 2A)

**Tanggal:** 8 Oktober 2026  
**Status:** Selesai (Passed & Verified)  
**Baseline Test:** 141 passed, 560 assertions  
**Hasil Test Akhir:** 153 passed, 587 assertions (12 test baru ditambahkan, 0 failure)

---

## 1. Hasil Audit Struktur AssetUnit

### 1.1 Schema Database `asset_units`
Tabel `asset_units` memiliki field-field berikut:
- `id` (bigint unsigned, primary key)
- `item_id` (foreign key to `items.id`, cascade on delete)
- `unit_inventory_number` (string, unique)
- `serial_number` (string, nullable)
- `current_condition` (string/enum: `baik`, `rusak_ringan`, `rusak_berat`)
- `current_status` (string/enum: `aktif`, `dipinjam`, `dalam_perbaikan`, `tidak_aktif`, `disposed`)
- `location_id` (foreign key to `locations.id`, nullable)
- `is_legacy_migrated` (boolean, default false)
- `notes` (text, nullable)
- `created_at`, `updated_at`, `deleted_at` (soft deletes)

### 1.2 Pemisahan `Condition` dan `Status` (Condition ≠ Status)
- **`current_condition` (Kondisi Fisik):**
  - Nilai: `baik`, `rusak_ringan`, `rusak_berat`
  - Perubahan dicatat secara append-only di tabel `condition_histories` dengan atribut `from_condition`, `to_condition`, `user_id`, `notes`, dan `recorded_at`.
- **`current_status` (Keadaan Operasional):**
  - Nilai: `aktif`, `dipinjam`, `dalam_perbaikan`, `tidak_aktif`, `disposed`
  - Status tidak digabung dengan kondisi. Contoh: unit berkondisi `baik` tetapi berstatus `dipinjam`; atau unit berkondisi `rusak_ringan` dengan status `dalam_perbaikan`.

### 1.3 Model Eloquent & Relasi
Model `AssetUnit` terhubung dengan:
- `belongsTo(Item::class)`: Relasi ke master barang.
- `belongsTo(Location::class)`: Lokasi fisik unit saat ini.
- `hasMany(Loan::class)`: Riwayat peminjaman untuk unit ini.
- `hasMany(ConditionHistory::class)`: Riwayat perubahan kondisi fisik unit.
- `hasMany(LocationHistory::class)`: Riwayat pemindahan lokasi unit.
- `activeLoans()`: Scope relasi loan dengan status `['dipinjam', 'disetujui', 'menunggu']`.

---

## 2. Bug yang Ditemukan & Diperbaiki

### 2.1 Bug: Unit Non-Loanable Muncul di Dropdown & Lolos Validasi Peminjaman
- **Deskripsi Masalah:**
  Pada implementasi sebelumnya di `LoanController::loanableUnitMap()` dan `LoanController::store()`, unit hanya difilter berdasarkan ketiadaan pinjaman aktif (`whereDoesntHave('loans', ...)`).
  Unit yang memiliki status `dalam_perbaikan`, `tidak_aktif`, atau `disposed` masih dapat muncul di dropdown pilihan peminjaman atau dipinjam langsung via HTTP POST.
- **Root Cause:**
  Ketiadaan pengecekan nilai `current_status` unit terhadap status operasional yang diizinkan untuk dipinjam (`LOANABLE_STATUSES`).
- **Solusi & Perbaikan:**
  1. Menambahkan konstanta `AssetUnit::LOANABLE_STATUSES = ['aktif']` pada model `AssetUnit`.
  2. Menambahkan helper `AssetUnit::isAvailableForLoan(): bool` dan scope `AssetUnit::scopeLoanable(Builder $query): Builder`.
  3. Memperbarui `LoanController::loanableUnitMap()` agar menggunakan `->loanable()`.
  4. Memperbarui validasi `LoanController::store()` agar menolak pemilihan unit dengan status selain `LOANABLE_STATUSES` dengan pesan error yang ramah pengguna.
  5. Menambahkan komponen badge status terpusat `<x-unit-status-badge :status="..." />` menggunakan konstanta `AssetUnit::STATUS_LABELS` dan `AssetUnit::STATUS_BADGE_COLORS`.
  6. Memperbarui tampilan detail inventaris (Sarpras & Kajur) serta detail peminjaman agar menampilkan status unit secara baku dan terstruktur.

---

## 3. Aturan State Transition AssetUnit

| Transisi | Kondisi / Trigger | Keterangan |
|---|---|---|
| `aktif` → `dipinjam` | Action `approve` pada peminjaman (`LoanController::approve`) | Unit diserahkan kepada peminjam |
| `dipinjam` → `aktif` | Action `returnLoan` pada pengembalian (`LoanController::returnLoan`) | Unit diterima kembali oleh Sarpras |
| `aktif` → `dalam_perbaikan` | Update status saat unit memerlukan perbaikan | Unit otomatis tidak dapat dipinjam |
| `dalam_perbaikan` → `aktif` | Update status saat pemeliharaan selesai | Unit kembali siap dipinjam |
| `aktif` → `tidak_aktif` | Penonaktifan unit sementara/gudang | Unit diblokir dari peminjaman |
| `*` → `disposed` | Penghapusan/pemusnahan unit | Bersifat permanen; tidak boleh dipinjam |

---

## 4. Keamanan & Authorization

- Route inventaris dan peminjaman dilindungi oleh middleware role-based:
  - `role:sarpras`: CRUD inventaris, persetujuan dan pengembalian peminjaman, update kondisi dan lokasi.
  - `role:kajur`: Read-only inventaris jurusan terkait, pengajuan peminjaman barang.
- Seluruh mutasi kondisi dan lokasi unit dicatat pada history table append-only (`ConditionHistory` & `LocationHistory`) yang dicegah dari modifikasi langsung melalui Eloquent boot guard.

---

## 5. Migration

Tidak ada migration skema baru yang dibuat pada Stage 2A karena:
- Kolom `current_condition` dan `current_status` sudah terpisah secara benar di migrasi awal.
- Corrective migration backfill `2026_10_08_000001_fix_legacy_migrated_asset_units_loan_status` sudah berjalan dan idempotent.

---

## 6. Pengujian & Verifikasi

### 6.1 Test Suite Baru: `tests/Feature/AssetUnitAvailabilityTest.php`
12 skenario pengujian baru ditambahkan:
1. `test_unit_aktif_tanpa_loan_tersedia_untuk_pinjam` — Unit aktif tanpa loan lolos `isAvailableForLoan()`.
2. `test_unit_aktif_dengan_loan_dipinjam_tidak_tersedia` — Unit dengan loan aktif tidak lolos `isAvailableForLoan()`.
3. `test_unit_dalam_perbaikan_tidak_tersedia` — Unit status `dalam_perbaikan` tidak lolos `isAvailableForLoan()`.
4. `test_unit_tidak_aktif_tidak_tersedia` — Unit status `tidak_aktif` tidak lolos `isAvailableForLoan()`.
5. `test_unit_disposed_tidak_tersedia` — Unit status `disposed` tidak lolos `isAvailableForLoan()`.
6. `test_scope_loanable_hanya_kembalikan_unit_aktif_tanpa_loan_aktif` — Query `scopeLoanable()` memfilter unit secara akurat.
7. `test_store_menolak_unit_dalam_perbaikan` — Endpoint POST menolak peminjaman unit berstatus `dalam_perbaikan`.
8. `test_store_menolak_unit_tidak_aktif` — Endpoint POST menolak peminjaman unit berstatus `tidak_aktif`.
9. `test_store_menolak_unit_disposed` — Endpoint POST menolak peminjaman unit berstatus `disposed`.
10. `test_store_menolak_unit_yang_sedang_dipinjam` — Endpoint POST menolak peminjaman unit yang sudah memiliki active loan.
11. `test_asset_unit_memiliki_loanable_statuses_constant` — Integritas konstanta `LOANABLE_STATUSES`.
12. `test_semua_status_enum_memiliki_label_dan_warna` — Integritas kamus warna dan label untuk seluruh status.

### 6.2 Hasil Test Aktual
```
PHPUnit 12.5.36 by Sebastian Bergmann and contributors.
Tests: 153, Assertions: 587, Passed: 153
All tests passed with zero failures.
```

---

## 7. Daftar File yang Diubah / Ditambahkan

| File | Status | Keterangan |
|---|---|---|
| `app/Models/AssetUnit.php` | Modified | Menambahkan konstanta `LOANABLE_STATUSES`, `STATUS_BADGE_COLORS`, helper `isAvailableForLoan()`, `isActive()`, dan `scopeLoanable()`. |
| `app/Http/Controllers/Sarpras/LoanController.php` | Modified | Memperketat `loanableUnitMap()` dengan `->loanable()` dan validasi `store()` terhadap `LOANABLE_STATUSES`. |
| `database/factories/AssetUnitFactory.php` | Added | Factory untuk `AssetUnit` dengan state: `rusak`, `tidakAktif`, `dalamPerbaikan`, `disposed`. |
| `database/factories/ItemFactory.php` | Modified | Menambahkan state `individual()` dan `consumable()`. |
| `resources/views/components/unit-status-badge.blade.php` | Added | Komponen Blade untuk badge status unit aset. |
| `resources/views/sarpras/inventory/show.blade.php` | Modified | Menampilkan badge status unit menggunakan `<x-unit-status-badge>`. |
| `resources/views/kajur/inventory/show.blade.php` | Modified | Menampilkan badge status unit menggunakan `<x-unit-status-badge>`. |
| `resources/views/sarpras/loans/show.blade.php` | Modified | Menampilkan badge status dan kondisi spesifik unit aset. |
| `tests/Feature/AssetUnitAvailabilityTest.php` | Added | Test feature lengkap untuk guard ketersediaan unit. |
| `docs/PHASE_2A_ASSET_CONDITION_STATUS_AUDIT.md` | Added | Laporan dokumentasi lengkap Stage 2A. |
