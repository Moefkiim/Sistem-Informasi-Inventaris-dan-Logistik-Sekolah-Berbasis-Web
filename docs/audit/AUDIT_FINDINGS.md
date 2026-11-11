# AUDIT FINDINGS: DAFTAR TEMUAN DAN REKOMENDASI SIILS

Dokumen ini mencatat seluruh temuan teknis, bug, kelemahan, atau potensi inkonsistensi yang diidentifikasi selama audit menyeluruh.

---

### Temuan #1 (Terselesaikan pada Sesi Ini)

* **ID:** `BUG-VIEW-001`
* **Judul:** Syntax error unexpected `endif` pada view daftar pengajuan Kajur
* **Prioritas:** `P0 - Critical (Blocker)`
* **Modul:** Pengajuan Barang Kajur
* **Role Terdampak:** Kajur (`kajur`), CI Pipeline
* **File dan Lokasi:** `resources/views/kajur/submissions/index.blade.php` (baris 120-126)
* **Kondisi:** Terdapat sisa duplikasi tag `@endif` dan potongan form revisi lama yang menyebabkan kegagalan kompilasi Blade view.
* **Langkah Reproduksi:** Mengakses rute `GET /kajur/submissions` atau menjalankan `php artisan test --filter=DemoReadinessTest`.
* **Hasil Aktual:** HTTP 500 `ParseError: syntax error, unexpected token "endif"`. Seluruh job GitHub Actions gagal.
* **Hasil yang Diharapkan:** View terkompilasi bersih dengan status HTTP 200.
* **Akar Masalah:** Pembaruan tombol SweetAlert konfirmasi menyisakan tag penutup form dan `@endif` lama di bawahnya.
* **Solusi:** Menghapus 6 baris duplikasi tag menggantung tersebut.
* **Test Verifikasi:** `DemoReadinessTest` lolos 5/5, view linter lolos 0 error.
* **Status:** `RESOLVED` (Telah di-commit pada commit `780c51c`)

---

### Temuan #2

* **ID:** `OBS-SEC-001`
* **Judul:** Security Advisories pada Komponen Symfony HTTP Foundation & Kernel
* **Prioritas:** `P2 - Medium`
* **Modul:** Vendor Dependencies
* **Role Terdampak:** Sistem / Server Administrator
* **File dan Lokasi:** `composer.lock`
* **Kondisi:** `composer audit` melaporkan 7 advisory pada versi sub-paket Symfony yang terpasang di bawah `laravel/framework:^13.17`.
* **Langkah Reproduksi:** Jalankan `composer audit` di root repositori.
* **Hasil Aktual:** Exit code 1 dengan rincian advisory (misal CVE-2026-118514, CVE-2026-45070).
* **Hasil yang Diharapkan:** Tidak ada celah keamanan yang terdaftar.
* **Akar Masalah:** Keterikatan dependensi framework memerlukan pembaruan patch release minor.
* **Solusi / Rekomendasi:** Jalankan `composer update symfony/*` atau `composer update laravel/framework symfony/*` pada siklus rilis berikutnya setelah menguji kompatibilitas.
* **Status:** `OPEN (Rekomendasi Pemeliharaan)`

---

### Temuan #3

* **ID:** `OBS-ENV-001`
* **Judul:** Status `storage:link` belum dibuat pada instalasi lokal baru
* **Prioritas:** `P3 - Low`
* **Modul:** Storage & Asset
* **Role Terdampak:** Pengembang Lokal
* **File dan Lokasi:** `public/storage`
* **Kondisi:** Output `php artisan about` menunjukkan `Storage public/storage: NOT LINKED`.
* **Langkah Reproduksi:** Jalankan `php artisan about`.
* **Analisis Dampak:** Seluruh dokumen berkas BAST/Nota di SIILS disimpan pada disk privat (`storage/app/private/documents`) dan disajikan lewat controller streaming (`DocumentController@download`), sehingga `storage:link` memang tidak diperlukan oleh aplikasi inti. Namun, jika ada fitur avatar atau media publik di masa depan, symlink akan dibutuhkan.
* **Solusi / Rekomendasi:** Jalankan `php artisan storage:link` saat setup awal demi kelengkapan standar instalasi Laravel.
* **Status:** `INFORMATIONAL`

---

### Temuan #4

* **ID:** `UX-TABLE-001`
* **Judul:** Konsistensi Kolom Nomor Urut (No) pada Tabel Data
* **Prioritas:** `P3 - Low`
* **Modul:** Tampilan Antarmuka (Views)
* **Role Terdampak:** Semua Role
* **File dan Lokasi:** Berkas views di `resources/views/sarpras/`, `resources/views/kajur/`, dll.
* **Kondisi:** Kolom nomor urut sebelumnya belum seragam.
* **Hasil Aktual:** Telah distandarkan pada commit `7133034` dengan penomoran berlanjut `$items->firstItem() + $loop->index`.
* **Status:** `VERIFIED & RESOLVED`

