# Laporan Penguatan Integritas AssetUnit, Condition, Status & Loan (Stage 2A V2)

**Tanggal:** 8 Oktober 2026  
**Status:** Selesai (Passed & Verified)  
**Baseline Test Awal:** 141 passed, 560 assertions  
**Hasil Test Akhir:** 160 passed, 610 assertions (19 test baru ditambahkan, 0 failure)

---

## 1. Audit Findings (Temuan Audit)

### Critical
- **Celah Double Booking Pending Loan (Reservation Conflict):**
  Sebelumnya, `AssetUnit::scopeLoanable()`, `isAvailableForLoan()`, dan `LoanController::store()` hanya memeriksa status pinjaman fisik (`whereIn('status', ['dipinjam', 'disetujui'])`). Status `menunggu` (pengajuan pending) tidak dianggap sebagai reservasi. Akibatnya, dua peminjam berbeda bisa mengajukan permohonan pinjaman untuk unit fisik yang sama secara bersamaan.
  *Perbaikan:* Menggunakan `Loan::STATUS_ACTIVE = ['menunggu', 'disetujui', 'dipinjam']` sebagai status yang mengikat unit. Setiap unit yang memiliki loan aktif/pending otomatis dikecualikan dari `scopeLoanable()`, ditolak saat `store()`, dan `isAvailableForLoan()` bernilai `false`.

- **Ketiadaan Re-Check Ketersediaan pada Approval (Race Condition Risk):**
  Sebelumnya, method `LoanController::approve()` langsung mengupdate unit menjadi `dipinjam` tanpa memeriksa ulang apakah unit tersebut telah dipinjam oleh approval lain atau telah berubah statusnya (misal masuk perbaikan/disposed) sejak pengajuan dibuat.
  *Perbaikan:* Memasang lock database `lockForUpdate()` di dalam transaksi `DB::transaction()`, melakukan re-check status unit (`$unit->current_status === 'aktif'`), dan memvalidasi bahwa tidak ada pinjaman lain yang sudah menyetujui unit tersebut sebelum commit.

### High
- **Transisi Status Tidak Proporsional saat Pengembalian Barang Rusak Berat:**
  Sebelumnya, saat pengembalian barang dicatat dengan kondisi `rusak_berat`, unit aset dan item master tetap dipaksa berubah menjadi status `aktif`. Hal ini bertentangan dengan prinsip keselamatan aset fisik.
  *Perbaikan:* Menerapkan aturan transisi berbasis kondisi:
  - `condition_on_return = 'baik'` atau `'rusak_ringan'` → status unit menjadi `aktif`.
  - `condition_on_return = 'rusak_berat'` → status unit otomatis menjadi `dalam_perbaikan` (tidak dapat dipinjam kembali sebelum diperbaiki).

- **Inkonsistensi Status Agregat Item Master vs Unit Fisik:**
  Sebelumnya, jika satu unit dari item yang memiliki banyak unit dipinjam, item master langsung diubah menjadi `dipinjam`, padahal masih ada unit lain yang aktif dan siap pakai.
  *Perbaikan:* Menambahkan method `recalculateAggregateStatus()` dan `recalculateAggregateCondition()` pada model `Item`. Status master mencerminkan ketersediaan: jika masih ada minimal 1 unit `aktif`, master tetap `aktif`; jika seluruh unit aktif sedang dipinjam, master menjadi `dipinjam`; jika seluruh unit dalam perbaikan, master menjadi `dalam_perbaikan`.

### Medium
- **Indikator Stok Minimum Belum Tampak di UI Master Inventaris:**
  Fitur `minimum_stock` dan `isStockLow()` sudah ada di model namun belum divisualisasikan.
  *Perbaikan:* Menambahkan badge indikator "Menipis" atau "Habis" secara non-intrusif pada kolom stok tabel inventaris Sarpras.

### Low
- Method `LoanController::reject()` belum dibungkus dalam `DB::transaction()`.
  *Perbaikan:* Membungkus perubahan status `ditolak` dan pencatatan activity log ke dalam transaksi database atomic.

---

## 2. Business Rules & State Transitions

### 2.1 Loan States
- `menunggu`: Permohonan dibuat, unit aset terikat/reserved, belum diserahkan fisik.
- `disetujui`: Permohonan disetujui (workflow state).
- `dipinjam`: Fisik aset diserahkan ke peminjam, `AssetUnit.current_status = 'dipinjam'`.
- `terlambat`: Waktu pinjam melampaui `due_date`, fisik masih di tangan peminjam.
- `dikembalikan`: Barang fisik telah diterima kembali oleh Sarpras.
- `ditolak`: Permohonan ditolak, reservasi unit fisik dilepaskan kembali.

### 2.2 AssetUnit States
- `aktif`: Tersedia untuk operasional dan peminjaman baru.
- `dipinjam`: Sedang dalam peminjaman aktif.
- `dalam_perbaikan`: Dalam masa pemeliharaan/perbaikan fisik; dilarang dipinjam.
- `tidak_aktif`: Dinonaktifkan sementara oleh Sarpras; dilarang dipinjam.
- `disposed`: Dihapuskan dari pencatatan; state terminal.

### 2.3 Aturan Pengembalian (Return Rules)
```text
Pengembalian Barang:
├── Kondisi: Baik           → Unit Status: Aktif
├── Kondisi: Rusak Ringan   → Unit Status: Aktif (dapat dipakai dengan pemantauan)
└── Kondisi: Rusak Berat    → Unit Status: Dalam Perbaikan (otomatis terkunci dari pinjaman)
```

---

## 3. Pencegahan Double Booking & Concurrency

1. **Reservation saat Pending:**
   Unit yang memiliki relasi pinjaman berstatus `menunggu`, `disetujui`, atau `dipinjam` (`Loan::STATUS_ACTIVE`) tidak akan muncul di dropdown pemilihan unit inventaris dan akan ditolak bila dikirim langsung via HTTP POST.
2. **Atomic Approval dengan Lock Database:**
   Proses `approve()` mengunci record `AssetUnit` menggunakan `lockForUpdate()`. Jika dua staf sarpras mencoba menyetujui dua permohonan berbeda pada unit fisik yang sama, request pertama akan berhasil mengubah unit menjadi `dipinjam`, sedangkan request kedua akan mendeteksi status unit bukan lagi `aktif` dan membatalkan transaksi dengan pesan error yang jelas tanpa merusak integritas database.

---

## 4. History & Audit Trail

- **Condition History (`condition_histories`):**
  Append-only via Eloquent boot guard. Setiap mutasi fisik saat registrasi, inspeksi, atau pengembalian pinjaman mencatat `from_condition`, `to_condition`, `asset_unit_id`, `user_id`, `notes`, dan `recorded_at`.
- **Location History (`location_histories`):**
  Append-only mencatat perpindahan fisik individual per unit aset beserta ruang asal dan tujuan.
- **Activity Log (`activity_logs`):**
  Mencatat setiap event pembuatan peminjaman, approval, penolakan, dan pengembalian lengkap dengan snapshot status lama dan baru.

---

## 5. Migration

**No migration required.**  
Skema tabel `asset_units`, `loans`, dan `items` sudah mendukung kolom `current_condition`, `current_status`, dan `asset_unit_id` secara lengkap.

---

## 6. Daftar File yang Diubah

| File | Keterangan Perubahan |
|---|---|
| `app/Models/Item.php` | Menambahkan method `recalculateAggregateStatus()`, `recalculateAggregateCondition()`, dan `aggregateStatusSummary()`. |
| `app/Models/AssetUnit.php` | Menggunakan `Loan::STATUS_ACTIVE` pada `isAvailableForLoan()` dan `scopeLoanable()` untuk mencegah double booking pending loan. |
| `app/Http/Controllers/Sarpras/LoanController.php` | Menambahkan pending loan guard pada `store()`, availability re-check & `lockForUpdate()` pada `approve()`, aturan status berbasis kondisi pada `returnLoan()`, dan wrapping transaksi pada `reject()`. Menjaga kompatibilitas data lama tanpa unit. |
| `resources/views/sarpras/inventory/index.blade.php` | Menambahkan badge indikator stok menipis (`isStockLow()`) dan stok habis secara proporsional. |
| `tests/Feature/AssetUnitAvailabilityTest.php` | Memperluas pengujian ketersediaan unit: pending loan reservation, approval availability re-check, penolakan concurrent approval, transisi return `rusak_berat`, kalkulasi agregat item, dan integritas konstanta. |
| `docs/PHASE_2A_ASSET_LOAN_INTEGRITY.md` | Laporan dokumentasi lengkap Stage 2A V2. |

---

## 7. Hasil Pengujian (Tests)

Command:
```bash
php vendor/phpunit/phpunit/phpunit --colors=never
```

Hasil Aktual:
```
{"tool":"phpunit","result":"passed","tests":160,"passed":160,"assertions":610,"duration_ms":8413}
```

- **Total Pengujian:** 160 tests passed (100% green)
- **Total Assertions:** 610 assertions
- **Kegagalan / Error:** 0
- **Verifikasi Khusus:**
  - `AssetUnitAvailabilityTest`: 19 passed, 50 assertions
  - `LoanWorkflowTest`: 29 passed, 103 assertions
  - `LoanPerUnitTest`: 8 passed, 27 assertions
  - `AssetUnitBackfillTest`: 9 passed, 41 assertions
  - `AssetUnitConditionTest`: 7 passed, 28 assertions
  - `AssetUnitLocationTest`: 7 passed, 23 assertions
  - `DemoReadinessTest`: 5 passed, 50 assertions
  - `AuthenticationAndRoleAccessTest`: 10 passed, 46 assertions

Cache & Route Check:
```bash
php artisan view:clear && php artisan view:cache # OK
php artisan route:list # OK
```

---

## 8. Remaining Issues

- Tidak ada issue kritis atau blocker pada layer integritas `AssetUnit`, `Condition`, `Status`, dan `Loan`.
- Sistem reservasi berbasis jadwal/kalender belum diimplementasikan karena di luar scope Stage 2A (aturan yang berlaku saat ini adalah 1 unit fisik tidak dapat memiliki lebih dari 1 pending/approved loan secara bersamaan).
