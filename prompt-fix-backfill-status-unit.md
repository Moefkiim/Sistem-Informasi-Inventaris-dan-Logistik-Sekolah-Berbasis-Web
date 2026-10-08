# PROMPT — PERBAIKI PROPAGASI STATUS 'DIPINJAM' PADA BACKFILL ASSET_UNITS

Verifikasi independen terhadap Section 2 (commit `e814536`) menemukan satu kelemahan laten di `app/Services/AssetUnitBackfiller.php`, method `unitPayload()`. Bagian lain sudah benar (transaksi + lock, idempotent, verifikasi count, `is_legacy_migrated`, D4 dipatuhi). Kerjakan perbaikan berikut.

## Masalah

`unitPayload()` menyalin `current_status` item ke SEMUA unit hasil migrasi:

```php
'current_status' => $item->current_status,
```

Sementara `backfillHistoriesAndLoans()` hanya memetakan histori/loan lama ke unit #1. Untuk item individual dengan `stock > 1` dan `current_status = 'dipinjam'`, semua unit jadi 'dipinjam' padahal hanya unit yang terkait loan aktif yang benar-benar dipinjam. Unit lainnya terkunci (tidak muncul di dropdown peminjaman) sampai dikoreksi manual.

Data demo tidak memicu ini (item yang dipinjam ber-stock 1), dan `AssetUnitBackfillTest` tidak pernah menguji kombinasi status 'dipinjam' dengan stock > 1, sehingga lolos tanpa terdeteksi.

## TASK 1 — Perbaiki logika di backfiller (untuk database yang belum menjalankan backfill)

1. Tentukan dulu dari `LoanController` (approve/return) status loan mana yang membuat sebuah unit/item berstatus 'dipinjam'. Pakai himpunan status yang sama persis, jangan menebak (kemungkinan `disetujui`/`dipinjam`/`terlambat`, tapi pastikan dari kode).
2. Ubah `unitPayload()` / `run()` sehingga jumlah unit berstatus 'dipinjam' sama dengan jumlah loan aktif milik item itu (dibatasi jumlah unit), dan sisanya 'aktif'. Karena histori lama dipetakan ke unit #1, unit yang menerima status 'dipinjam' harus unit yang benar-benar terhubung ke loan aktif tersebut (konsisten dengan `asset_unit_id` yang di-backfill).
3. Untuk status selain 'dipinjam' (misalnya `dalam_perbaikan`, `tidak_aktif`, `disposed`), pertahankan perilaku sekarang (disalin ke semua unit) kecuali Anda temukan alasan konkret di kode bahwa itu juga keliru, dan kalau begitu laporkan dulu sebelum mengubah.
4. Kondisi dan lokasi tetap disalin ke semua unit seperti sekarang (tidak ada data per unit sebelumnya), sudah ditandai `is_legacy_migrated`.

## TASK 2 — Migration korektif untuk database yang sudah menjalankan backfill

Mengedit migration `2026_10_07_000003_backfill_asset_units_from_items` tidak akan berpengaruh pada database yang sudah menjalankannya (dev DB, dan DB lain bila ada). Buat migration BARU (jangan edit/hapus migration lama) yang:

1. Hanya menyentuh unit dengan `is_legacy_migrated = true` dan `current_status = 'dipinjam'` yang TIDAK terhubung ke loan aktif manapun (pakai himpunan status aktif dari TASK 1 poin 1), lalu mengubahnya menjadi 'aktif'.
2. Idempotent dan konservatif: tidak menyentuh unit yang didaftarkan manual (`is_legacy_migrated = false`), tidak menyentuh unit yang punya loan aktif.
3. Mencetak ringkasan (berapa unit dikoreksi) seperti pola migration backfill, dan aman pada DB kosong (no-op).
4. `down()` non-destruktif.

## TASK 3 — Test yang menutup celah

Tambahkan ke `AssetUnitBackfillTest` (atau file baru yang serumpun):

1. Item individual `stock = 3`, `current_status = 'dipinjam'`, satu loan aktif: setelah backfill, tepat 1 unit 'dipinjam' (yang terhubung ke loan itu) dan 2 unit 'aktif'.
2. Item individual `stock = 3`, `current_status = 'dipinjam'`, TANPA loan aktif sama sekali (data tidak konsisten): semua unit 'aktif', tidak ada yang terkunci.
3. Migration korektif: siapkan state "salah" (3 unit 'dipinjam', 1 loan aktif) lalu jalankan korektif, hasilnya 1 'dipinjam' dan 2 'aktif'. Jalankan dua kali, hasil tetap sama.
4. Unit dengan `is_legacy_migrated = false` berstatus 'dipinjam' tanpa loan tidak boleh disentuh migration korektif.
5. Buktikan test 1 benar-benar menangkap bug: jalankan sekali terhadap kode lama (atau balikkan sementara perbaikan) dan tunjukkan gagal, lalu hijau setelah perbaikan.

## TASK 4 — Dokumentasi di laporan audit

Tambahkan di `AUDIT_IMPLEMENTATION_REPORT.md` bagian Section 2: asumsi migrasi yang tidak bisa dihindari. Kondisi dan lokasi disalin ke semua unit hasil migrasi karena tidak ada data per unit sebelumnya. Item individual soft-deleted tidak ikut di-backfill, sehingga loan/histori miliknya tetap `asset_unit_id = NULL`. Item individual dengan stock 0 tetapi ber-inventory_number dibuat menjadi 1 unit. Tulis apa adanya, ringkas.

## Verifikasi dan commit

- `php artisan test` penuh dan `DemoReadinessTest` eksplisit hijau.
- Tunjukkan hasil migration korektif pada dev DB (berapa unit dikoreksi; kalau 0, jelaskan itu karena demo data tidak memicu kasus ini).
- Commit terpisah: `fix: batasi status dipinjam hanya pada unit dengan loan aktif saat backfill`, `fix: migration korektif status unit legacy`, `test: tutup celah backfill status dipinjam stock>1`, `docs: catat asumsi migrasi asset_units`.
- Push, verifikasi `rev-parse HEAD` sama dengan `ls-remote origin main`, dan pastikan CI hijau sebelum melapor selesai.
