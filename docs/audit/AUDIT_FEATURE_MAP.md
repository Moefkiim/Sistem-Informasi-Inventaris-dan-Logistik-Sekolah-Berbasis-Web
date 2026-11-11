# AUDIT FEATURE MAP: SISTEM INFORMASI INVENTARIS DAN LOGISTIK SEKOLAH (SIILS)

Dokumen ini memetakan seluruh route aktual, middleware, controller, model, view, validasi input, dan test yang mencakup setiap fitur aplikasi SIILS.

---

## 1. Modul Autentikasi & Akun Pengguna

| Nama Fitur | Role Akses | HTTP Method & Route | Controller & Method | Model & Tabel | View / Komponen | Validasi Input | Middleware / Guard | Status & Test Terkait |
|---|---|---|---|---|---|---|---|---|
| Form Login | Guest | `GET /login` (`login`) | `Auth\AuthController@showLoginForm` | `User` (`users`) | `auth.login` | - | `guest` | PASS (`AuthenticationAndRoleAccessTest`) |
| Proses Login | Guest | `POST /login` | `Auth\AuthController@login` | `User` (`users`) | - | `login` (string, required), `password` (string, required) | `guest`, `throttle:5,1` | PASS (Mendukung username & email, blokir akun non-aktif) |
| Logout | Auth | `POST /logout` (`logout`) | `Auth\AuthController@logout` | `User` (`users`) | - | - | `auth` | PASS (`AuthenticationAndRoleAccessTest`) |
| Profil Pengguna | Auth (Semua Role) | `GET /profile` (`profile.show`) | `ProfileController@show` | `User` (`users`) | `profile.show` | - | `auth`, `role`, `password.changed` | PASS (`ProfileAndPasswordTest`) |
| Perbarui Password | Auth (Semua Role) | `PUT /profile/password` (`profile.password`) | `ProfileController@updatePassword` | `User` (`users`) | - | `current_password` (current_password), `password` (min:8, confirmed) | `auth`, `role`, `throttle:6,1` | PASS (Reset flag `must_change_password`, cabut sesi lama) |

---

## 2. Modul Dashboard & Notifikasi

| Nama Fitur | Role Akses | HTTP Method & Route | Controller & Method | Model & Tabel | View / Komponen | Validasi Input | Middleware / Guard | Status & Test Terkait |
|---|---|---|---|---|---|---|---|---|
| Root Redirect | Semua | `GET /` | `RootRedirectController` | - | - | - | - | PASS (Redirect guest ke `/login`, auth ke `/home`) |
| Dashboard Utama | Auth (Semua Role) | `GET /home` (`home`) | `HomeController@index` | `Item`, `AssetUnit`, `Submission`, `Location`, `Loan` | `home` | - | `auth`, `role`, `password.changed` | PASS (Scoped per-role melalui `TaskOverview`: Kajur hanya jurusannya) |
| Daftar Notifikasi | Auth (Semua Role) | `GET /notifications` (`notifications.index`) | `NotificationController@index` | `Notification` (`notifications`) | `notifications.index` | - | `auth`, `role`, `password.changed` | PASS (`SubmissionNotificationTest`) |
| Buka & Baca Notifikasi | Auth (Semua Role) | `GET /notifications/{notification}` (`notifications.open`) | `NotificationController@open` | `Notification` (`notifications`) | - | - | `auth`, `role`, `password.changed` | PASS (Menandai `read_at` lalu redirect ke target) |
| Tandai Semua Dibaca | Auth (Semua Role) | `POST /notifications/read-all` (`notifications.read_all`) | `NotificationController@readAll` | `Notification` (`notifications`) | - | - | `auth`, `role`, `password.changed` | PASS (`SubmissionNotificationTest`) |

---

## 3. Modul Pengajuan Barang (Kajur)

| Nama Fitur | Role Akses | HTTP Method & Route | Controller & Method | Model & Tabel | View / Komponen | Validasi Input | Middleware / Guard | Status & Test Terkait |
|---|---|---|---|---|---|---|---|---|
| Area Redirect Kajur | Kajur | `GET /kajur/area` (`kajur.area`) | `RedirectToRouteController` | - | - | - | `auth`, `role:kajur` | PASS (Redirect ke `kajur.submissions.index`) |
| Daftar Pengajuan Kajur | Kajur | `GET /kajur/submissions` (`kajur.submissions.index`) | `Kajur\SubmissionController@index` | `Submission`, `SubmissionItem` | `kajur.submissions.index` | `search`, `status`, `start_date`, `end_date` | `auth`, `role:kajur` | PASS (Scoped `user_id`, filter daterange & search) |
| Form Buat Pengajuan | Kajur | `GET /kajur/submissions/create` (`kajur.submissions.create`) | `Kajur\SubmissionController@create` | `Submission` | `kajur.submissions.create` | - | `auth`, `role:kajur` | PASS (Dynamic rows item form) |
| Simpan Pengajuan (Draft / Submit) | Kajur | `POST /kajur/submissions` (`kajur.submissions.store`) | `Kajur\SubmissionController@store` | `Submission`, `SubmissionItem`, `SubmissionHistory` | - | `title`, `purpose`, `action` (draft/submit), `items.*` (name, qty>=1, unit, price>=0, specs) | `auth`, `role:kajur` | PASS (Atomik DB transaction, notifikasi otomatis) |
| Detail Pengajuan Kajur | Kajur | `GET /kajur/submissions/{submission}` (`kajur.submissions.show`) | `Kajur\SubmissionController@show` | `Submission`, `SubmissionItem`, `SubmissionHistory` | `kajur.submissions.show` | - | `auth`, `role:kajur` | PASS (Tenant isolation: 403 jika ID milik jurusan lain) |
| Form Edit Draft | Kajur | `GET /kajur/submissions/{submission}/edit` (`kajur.submissions.edit`) | `Kajur\SubmissionController@edit` | `Submission`, `SubmissionItem` | `kajur.submissions.edit` | - | `auth`, `role:kajur` | PASS (Hanya boleh diedit saat status `draft`) |
| Perbarui Draft | Kajur | `PUT /kajur/submissions/{submission}` (`kajur.submissions.update`) | `Kajur\SubmissionController@update` | `Submission`, `SubmissionItem` | - | Sama seperti store | `auth`, `role:kajur` | PASS (Mencegah edit status non-draft) |
| Batalkan Pengajuan Draft | Kajur | `POST /kajur/submissions/{submission}/cancel` (`kajur.submissions.cancel`) | `Kajur\SubmissionController@cancel` | `Submission`, `SubmissionHistory` | - | - | `auth`, `role:kajur` | PASS (Hanya status `draft` yang bisa dibatalkan) |
| Kirim Draft ke Sarpras | Kajur | `POST /kajur/submissions/{submission}/submit` (`kajur.submissions.submitDraft`) | `Kajur\SubmissionController@submitDraft` | `Submission`, `SubmissionHistory` | - | - | `auth`, `role:kajur` | PASS (Transisi `draft` -> `submitted`) |
| Ajukan Ulang Revisi Draf | Kajur | `POST /kajur/submissions/{submission}/revise` (`kajur.submissions.revise`) | `Kajur\SubmissionController@revise` | `Submission`, `SubmissionItem`, `SubmissionHistory` | - | - | `auth`, `role:kajur` | PASS (`LecturerRevisionTest`: kloning item dari pengajuan `rejected`) |
| Inventaris Jurusan Kajur | Kajur | `GET /kajur/inventory` (`kajur.inventory.index`) | `Kajur\InventoryController@index` | `Item`, `AssetUnit`, `Location` | `kajur.inventory.index` | `search`, `condition`, `status`, `location_id` | `auth`, `role:kajur` | PASS (Scoped `department == user->department`) |
| Detail Inventaris Jurusan | Kajur | `GET /kajur/inventory/{item}` (`kajur.inventory.show`) | `Kajur\InventoryController@show` | `Item`, `AssetUnit`, `LocationHistory`, `ConditionHistory` | `kajur.inventory.show` | - | `auth`, `role:kajur` | PASS (403 jika item bukan milik jurusannya) |

---

## 4. Modul Verifikasi & Realisasi Pengajuan (Sarpras)

| Nama Fitur | Role Akses | HTTP Method & Route | Controller & Method | Model & Tabel | View / Komponen | Validasi Input | Middleware / Guard | Status & Test Terkait |
|---|---|---|---|---|---|---|---|---|
| Area Redirect Sarpras | Sarpras | `GET /sarpras/area` (`sarpras.area`) | `RedirectToRouteController` | - | - | - | `auth`, `role:sarpras` | PASS (Redirect ke `sarpras.inventory.index`) |
| Review Pengajuan Kajur | Sarpras | `GET /sarpras/submissions` (`sarpras.submissions.index`) | `Sarpras\SubmissionController@index` | `Submission` | `sarpras.submissions.index` | `search`, `status`, `department`, `start_date`, `end_date` | `auth`, `role:sarpras` | PASS |
| Detail Pengajuan Sarpras | Sarpras | `GET /sarpras/submissions/{submission}` (`sarpras.submissions.show`) | `Sarpras\SubmissionController@show` | `Submission`, `SubmissionItem` | `sarpras.submissions.show` | - | `auth`, `role:sarpras` | PASS |
| Proses Review Pengajuan | Sarpras | `POST /sarpras/submissions/{submission}/process` (`sarpras.submissions.process`) | `Sarpras\SubmissionController@process` | `Submission`, `SubmissionHistory` | - | `sarpras_notes` (nullable, max:1000) | `auth`, `role:sarpras` | PASS (Transisi `submitted` -> `reviewed_sarpras`) |
| Pengajuan Siap Direalisasi | Sarpras | `GET /sarpras/realizations` (`sarpras.realizations.index`) | `Sarpras\SubmissionController@ready` | `Submission` | `sarpras.realizations.index` | `search`, `department`, `start_date`, `end_date` | `auth`, `role:sarpras` | PASS (Menampilkan pengajuan berstatus `approved`) |
| Form Realisasi | Sarpras | `GET /sarpras/submissions/{submission}/realize` (`sarpras.submissions.realize_form`) | `Sarpras\SubmissionController@realizeForm` | `Submission`, `Location`, `Item` | `sarpras.realizations.create` | - | `auth`, `role:sarpras` | PASS |
| Eksekusi Realisasi | Sarpras | `POST /sarpras/submissions/{submission}/realize` (`sarpras.submissions.realize`) | `Sarpras\SubmissionController@realize` | `Submission`, `Item`, `AssetUnit`, `IncomingItem` | - | Mode new/existing, qty, location_id, condition, source | `auth`, `role:sarpras` | PASS (`SubmissionRealizationTest`: stok bertambah, unit dibuat, transisi `realized`) |

---

## 5. Modul Persetujuan / Approval (Kepala Sekolah)

| Nama Fitur | Role Akses | HTTP Method & Route | Controller & Method | Model & Tabel | View / Komponen | Validasi Input | Middleware / Guard | Status & Test Terkait |
|---|---|---|---|---|---|---|---|---|
| Area Redirect Kepsek | Kepsek | `GET /kepala-sekolah/area` (`kepala_sekolah.area`) | `RedirectToRouteController` | - | - | - | `auth`, `role:kepala_sekolah` | PASS (Redirect ke `kepala_sekolah.approval.index`) |
| Daftar Persetujuan | Kepsek | `GET /kepala-sekolah/approval` (`kepala_sekolah.approval.index`) | `Principal\ApprovalController@index` | `Submission` | `principal.approval.index` | `status`, `department` | `auth`, `role:kepala_sekolah` | PASS |
| Detail Telaah & Putusan | Kepsek | `GET /kepala-sekolah/approval/{submission}` (`kepala_sekolah.approval.show`) | `Principal\ApprovalController@show` | `Submission`, `SubmissionItem` | `principal.approval.show` | - | `auth`, `role:kepala_sekolah` | PASS |
| Eksekusi Approval/Reject | Kepsek | `POST /kepala-sekolah/approval/{submission}/decide` (`kepala_sekolah.approval.decide`) | `Principal\ApprovalController@decide` | `Submission`, `SubmissionHistory` | - | `decision` (approve/reject), `principal_notes` (wajib jika reject) | `auth`, `role:kepala_sekolah` | PASS (Transisi `approved` atau `rejected`) |

---

## 6. Modul Master Inventaris & Logistik (Sarpras)

| Nama Fitur | Role Akses | HTTP Method & Route | Controller & Method | Model & Tabel | View / Komponen | Validasi Input | Middleware / Guard | Status & Test Terkait |
|---|---|---|---|---|---|---|---|---|
| Master Inventaris | Sarpras | `GET /sarpras/inventory` (`sarpras.inventory.index`) | `Sarpras\InventoryController@index` | `Item`, `AssetUnit`, `Location` | `sarpras.inventory.index` | Multi-filter (dept, cat, cond, stat, loc, stock, dates) | `auth`, `role:sarpras` | PASS |
| Form Tambah Barang | Sarpras | `GET /sarpras/inventory/create` (`sarpras.inventory.create`) | `Sarpras\InventoryController@create` | `Location` | `sarpras.inventory.create` | - | `auth`, `role:sarpras` | PASS |
| Simpan Barang Baru | Sarpras | `POST /sarpras/inventory` (`sarpras.inventory.store`) | `Sarpras\InventoryController@store` | `Item`, `AssetUnit`, `LocationHistory`, `ConditionHistory` | - | `name`, `code` (nullable/auto), `item_type`, `unit`, `location_id`, dll. | `auth`, `role:sarpras` | PASS (Auto generate code bila kosong) |
| Detail Inventaris Lengkap | Sarpras | `GET /sarpras/inventory/{item}` (`sarpras.inventory.show`) | `Sarpras\InventoryController@show` | `Item`, `AssetUnit`, `LocationHistory`, `ConditionHistory`, `AssetUnitStatusHistory` | `sarpras.inventory.show` | - | `auth`, `role:sarpras` | PASS (Histori lengkap unit & timeline) |
| Mutasi Lokasi Barang | Sarpras | `POST /sarpras/inventory/{item}/location` (`sarpras.inventory.updateLocation`) | `Sarpras\InventoryController@updateLocation` | `Item`, `AssetUnit`, `LocationHistory` | - | `to_location_id`, `notes`, `unit_ids` (opsional) | `auth`, `role:sarpras` | PASS |
| Ubah Kondisi Fisik | Sarpras | `POST /sarpras/inventory/{item}/condition` (`sarpras.inventory.updateCondition`) | `Sarpras\InventoryController@updateCondition` | `Item`, `AssetUnit`, `ConditionHistory` | - | `condition`, `notes`, `asset_unit_id` (opsional) | `auth`, `role:sarpras` | PASS (5 kondisi fisik aset didukung) |
| Ubah Status Siklus Unit | Sarpras | `POST /sarpras/inventory/{item}/status` (`sarpras.inventory.updateStatus`) | `Sarpras\InventoryController@updateStatus` | `Item`, `AssetUnit`, `AssetUnitStatusHistory` | - | `asset_unit_id`, `status`, `reason`, `condition_after_repair` | `auth`, `role:sarpras` | PASS (Matriks transisi status tervalidasi) |
| Download Template Impor | Sarpras | `GET /sarpras/inventory/import/template` (`sarpras.inventory.import.template`) | `Sarpras\InventoryImportController@template` | - | - | - | `auth`, `role:sarpras` | PASS (Download .xlsx resmi) |
| Form Impor Excel/CSV | Sarpras | `GET /sarpras/inventory/import` (`sarpras.inventory.import.create`) | `Sarpras\InventoryImportController@create` | - | `sarpras.inventory.import` | - | `auth`, `role:sarpras` | PASS |
| Pratinjau File Impor | Sarpras | `POST /sarpras/inventory/import/preview` (`sarpras.inventory.import.preview`) | `Sarpras\InventoryImportController@preview` | - | `sarpras.inventory.import-preview` | `file` (.xlsx/.csv, max:2MB) | `auth`, `role:sarpras` | PASS (Validasi komprehensif baris/kolom) |
| Konfirmasi Impor All-or-Nothing | Sarpras | `POST /sarpras/inventory/import/confirm` (`sarpras.inventory.import.confirm`) | `Sarpras\InventoryImportController@confirm` | `Item`, `AssetUnit` | - | `token` (pratinjau valid) | `auth`, `role:sarpras` | PASS (Atomik transaksi) |
| Download Kesalahan Impor | Sarpras | `GET /sarpras/inventory/import/errors/{token}` (`sarpras.inventory.import.errors`) | `Sarpras\InventoryImportController@downloadErrors` | - | - | - | `auth`, `role:sarpras` | PASS (Export format CSV) |
| Log Barang Masuk | Sarpras | `GET /sarpras/logistics/incoming` (`sarpras.logistics.incoming`) | `Sarpras\LogisticsController@incomingIndex` | `IncomingItem`, `Item` | `sarpras.logistics.incoming` | `search`, `source`, `start_date`, `end_date` | `auth`, `role:sarpras` | PASS |
| Simpan Barang Masuk | Sarpras | `POST /sarpras/logistics/incoming` (`sarpras.logistics.incoming.store`) | `Sarpras\LogisticsController@storeIncoming` | `IncomingItem`, `Item` | - | `item_id`, `quantity`>=1, `source`, `source_origin`, `entry_date` | `auth`, `role:sarpras` | PASS (Stok bertambah otomatis) |
| Log Barang Keluar | Sarpras | `GET /sarpras/logistics/outgoing` (`sarpras.logistics.outgoing`) | `Sarpras\LogisticsController@outgoingIndex` | `OutgoingItem`, `Item` | `sarpras.logistics.outgoing` | `search`, `reason`, `start_date`, `end_date` | `auth`, `role:sarpras` | PASS |
| Simpan Barang Keluar | Sarpras | `POST /sarpras/logistics/outgoing` (`sarpras.logistics.outgoing.store`) | `Sarpras\LogisticsController@storeOutgoing` | `OutgoingItem`, `Item` | - | `item_id`, `quantity`>=1, `reason`, `exit_date`, `notes` | `auth`, `role:sarpras` | PASS (Stok berkurang, dicegah negatif) |
| Log Distribusi / Penyaluran | Sarpras | `GET /sarpras/logistics/distributions` (`sarpras.logistics.distributions`) | `Sarpras\LogisticsController@distributionIndex` | `Distribution`, `Item`, `Location` | `sarpras.logistics.distributions` | `search`, `to_location_id`, `recipient_department`, `start_date`, `end_date` | `auth`, `role:sarpras` | PASS |
| Simpan Distribusi Barang | Sarpras | `POST /sarpras/logistics/distributions` (`sarpras.logistics.distributions.store`) | `Sarpras\LogisticsController@storeDistribution` | `Distribution`, `Item`, `Location` | - | `item_id`, `quantity`>=1, `to_location_id`, `recipient_name`, `distribution_date` | `auth`, `role:sarpras` | PASS (Validasi ketersediaan stok & update lokasi) |

---

## 7. Modul Peminjaman & Pengembalian (Sarpras)

| Nama Fitur | Role Akses | HTTP Method & Route | Controller & Method | Model & Tabel | View / Komponen | Validasi Input | Middleware / Guard | Status & Test Terkait |
|---|---|---|---|---|---|---|---|---|
| Daftar Peminjaman | Sarpras | `GET /sarpras/loans` (`sarpras.loans.index`) | `Sarpras\LoanController@index` | `Loan`, `Item`, `AssetUnit` | `sarpras.loans.index` | `search`, `status`, `department`, `start_date`, `end_date`, `due` | `auth`, `role:sarpras` | PASS (Status `terlambat` dihitung runtime via `scopeOverdue`) |
| Form Buat Peminjaman | Sarpras | `GET /sarpras/loans/create` (`sarpras.loans.create`) | `Sarpras\LoanController@create` | `Item`, `AssetUnit` | `sarpras.loans.create` | - | `auth`, `role:sarpras` | PASS |
| Simpan Permohonan Peminjaman | Sarpras | `POST /sarpras/loans` (`sarpras.loans.store`) | `Sarpras\LoanController@store` | `Loan`, `Item`, `AssetUnit` | - | `item_id`, `borrower_name`, `borrower_type`, `loan_date`, `due_date`, `quantity`/`asset_unit_ids` | `auth`, `role:sarpras` | PASS (Lock unit aset) |
| Detail Peminjaman | Sarpras | `GET /sarpras/loans/{loan}` (`sarpras.loans.show`) | `Sarpras\LoanController@show` | `Loan`, `Item`, `AssetUnit` | `sarpras.loans.show` | - | `auth`, `role:sarpras` | PASS |
| Setujui Peminjaman | Sarpras | `POST /sarpras/loans/{loan}/approve` (`sarpras.loans.approve`) | `Sarpras\LoanController@approve` | `Loan`, `AssetUnit` | - | - | `auth`, `role:sarpras` | PASS (Unit berubah status ke `dipinjam`) |
| Tolak Peminjaman | Sarpras | `POST /sarpras/loans/{loan}/reject` (`sarpras.loans.reject`) | `Sarpras\LoanController@reject` | `Loan`, `AssetUnit` | - | `notes` | `auth`, `role:sarpras` | PASS (Kembalikan ketersediaan unit) |
| Proses Pengembalian Barang | Sarpras | `POST /sarpras/loans/{loan}/return` (`sarpras.loans.returnLoan`) | `Sarpras\LoanController@returnLoan` | `Loan`, `AssetUnit`, `ConditionHistory` | - | `return_date`, `return_condition`, `notes` | `auth`, `role:sarpras` | PASS (Perbarui kondisi unit aset dan status transaksi) |

---

## 8. Modul Master Lokasi & Manajemen Pengguna (Sarpras)

| Nama Fitur | Role Akses | HTTP Method & Route | Controller & Method | Model & Tabel | View / Komponen | Validasi Input | Middleware / Guard | Status & Test Terkait |
|---|---|---|---|---|---|---|---|---|
| Master Lokasi | Sarpras | `GET /sarpras/locations` (`sarpras.locations.index`) | `Sarpras\LocationController@index` | `Location` | `sarpras.locations.index` | `search`, `department` | `auth`, `role:sarpras` | PASS |
| Tambah Lokasi | Sarpras | `POST /sarpras/locations` (`sarpras.locations.store`) | `Sarpras\LocationController@store` | `Location` | - | `name`, `code` (unique), `building`, `department` | `auth`, `role:sarpras` | PASS |
| Hapus Lokasi (Soft-Delete) | Sarpras | `DELETE /sarpras/locations/{location}` (`sarpras.locations.destroy`) | `Sarpras\LocationController@destroy` | `Location` | - | - | `auth`, `role:sarpras` | PASS (Ditolak jika lokasi masih menampung item/unit aset) |
| Manajemen Pengguna | Sarpras | `GET /sarpras/users` (`sarpras.users.index`) | `Sarpras\UserController@index` | `User` | `sarpras.users.index` | - | `auth`, `role:sarpras` | PASS |
| Tambah Staf Baru | Sarpras | `POST /sarpras/users` (`sarpras.users.store`) | `Sarpras\UserController@store` | `User` | - | `name`, `username` (unique), `email` (unique), `password`, `role`, `department` | `auth`, `role:sarpras` | PASS |
| Perbarui Data Staf | Sarpras | `PUT /sarpras/users/{user}` (`sarpras.users.update`) | `Sarpras\UserController@update` | `User` | - | `name`, `username`, `email`, `department` | `auth`, `role:sarpras` | PASS (Perlindungan akun admin inti) |
| Reset Password Pengguna | Sarpras | `POST /sarpras/users/{user}/reset-password` (`sarpras.users.reset_password`) | `Sarpras\UserController@resetPassword` | `User` | - | - | `auth`, `role:sarpras` | PASS (Generate sandi sementara, wajib ubah password saat login) |
| Toggle Status Aktif/Nonaktif | Sarpras | `POST /sarpras/users/{user}/toggle` (`sarpras.users.toggle`) | `Sarpras\UserController@toggleStatus` | `User` | - | - | `auth`, `role:sarpras` | PASS (Mencegah deaktivasi diri sendiri / akun utama) |
| Log Riwayat Aktivitas | Sarpras | `GET /sarpras/activity-logs` (`sarpras.activity_logs.index`) | `Sarpras\ActivityLogController@index` | `ActivityLog` | `sarpras.activity_logs.index` | `action`, `search` | `auth`, `role:sarpras` | PASS (Append-only audit trail) |

---

## 9. Modul Manajemen Dokumen & BAST

| Nama Fitur | Role Akses | HTTP Method & Route | Controller & Method | Model & Tabel | View / Komponen | Validasi Input | Middleware / Guard | Status & Test Terkait |
|---|---|---|---|---|---|---|---|---|
| Daftar Dokumen & BAST | Auth (Semua Role) | `GET /documents` (`documents.index`) | `DocumentController@index` | `Document` | `documents.index` | `category`, `search` | `auth`, `role`, `password.changed` | PASS (Kajur melihat sesuai jurusannya) |
| Unggah Dokumen Baru | Sarpras & Kajur | `POST /documents` (`documents.store`) | `DocumentController@store` | `Document` | - | `title`, `category`, `department`, `file` (pdf,jpg,png, max:10MB) | `auth`, `role`, `password.changed` | PASS (Disimpan pada disk privat `storage/app/private/documents`) |
| Download Dokumen Aman | Auth (Pemilik Hak) | `GET /documents/{document}/download` (`documents.download`) | `DocumentController@download` | `Document` | - | - | `auth`, `role`, `password.changed` | PASS (Streaming privat dengan pencegahan path traversal) |
| Hapus Dokumen | Sarpras & Kajur | `DELETE /documents/{document}` (`documents.destroy`) | `DocumentController@destroy` | `Document` | - | - | `auth`, `role`, `password.changed` | PASS (Hanya boleh hapus milik sendiri/otoritas Sarpras) |

---

## 10. Modul Laporan Rekapitulasi & Ekspor

| Nama Fitur | Role Akses | HTTP Method & Route | Controller & Method | Model & Tabel | View / Komponen | Validasi Input | Middleware / Guard | Status & Test Terkait |
|---|---|---|---|---|---|---|---|---|
| Halaman Laporan & Monitoring | Auth (Semua Role) | `GET /reports` (`reports.index`) | `ReportController@index` | `Item`, `IncomingItem`, `OutgoingItem`, `Distribution`, `Submission`, `Loan`, `ConditionHistory` | `reports.index` | `type`, `start_date`, `end_date`, `department`, `status`, `print` | `auth`, `role`, `password.changed` | PASS (Mendukung mode cetak bebas navbar/sidebar) |
| Ekspor Laporan PDF | Auth (Semua Role) | `GET /reports/pdf` (`reports.pdf`) | `ReportController@exportPdf` | Sama seperti index | `reports.pdf` | Sama seperti index | `auth`, `role`, `password.changed` | PASS (Menggunakan dompdf, scoped per-role) |
| Ekspor Laporan Excel | Auth (Semua Role) | `GET /reports/excel` (`reports.excel`) | `ReportController@exportExcel` | Sama seperti index | - | Sama seperti index | `auth`, `role`, `password.changed` | PASS (Menggunakan PhpSpreadsheet, proteksi formula injection) |

